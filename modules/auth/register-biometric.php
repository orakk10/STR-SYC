<?php
// modules/auth/register-biometric.php

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in again.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_options') {
    try {
        $challenge = random_bytes(32);
        $_SESSION['biometric_register_challenge'] = base64_encode($challenge);

        $stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'challenge' => $_SESSION['biometric_register_challenge'],
            'user' => [
                'id' => base64_encode(strval($user_id)),
                'name' => $user['username'] ?? "user_$user_id",
                'displayName' => $user['full_name'] ?? 'STRAND-SYNC User'
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to generate registration challenge.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $credential_id = trim($input['id'] ?? '');
    $raw_id = trim($input['rawId'] ?? '');
    $public_key = !empty($raw_id) ? $raw_id : $credential_id;

    if (empty($credential_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid credential payload received.']);
        exit;
    }

    try {
        // Delete old credentials for this user to avoid stale mismatches
        $delStmt = $conn->prepare("DELETE FROM user_biometric_credentials WHERE user_id = :user_id");
        $delStmt->execute(['user_id' => $user_id]);

        // Insert fresh credential ID
        $stmt = $conn->prepare("
            INSERT INTO user_biometric_credentials (user_id, credential_id, public_key, created_at)
            VALUES (:user_id, :cred_id, :pub_key, NOW())
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'cred_id' => $credential_id,
            'pub_key' => $public_key
        ]);

        // Enable biometric preference for user
        $updateStmt = $conn->prepare("UPDATE users SET security_preference = 'biometric' WHERE id = :id");
        $updateStmt->execute(['id' => $user_id]);

        $role = strtolower($_SESSION['role'] ?? 'student');
        $redirect_url = '../student/student_dashboard.php';

        if (in_array($role, ['super_admin', 'admin', 'super admin'])) {
            $redirect_url = '../admin/admin_dashboard.php';
        } elseif (in_array($role, ['adviser', 'faculty'])) {
            $redirect_url = '../faculty/faculty_dashboard.php';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Fingerprint registered and enabled successfully!',
            'redirect' => $redirect_url
        ]);
    } catch (PDOException $e) {
        error_log("Biometric Registration DB Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error during registration: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Biometric Registration Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}