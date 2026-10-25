<?php
// modules/auth/register-biometric.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Generate Challenge
if ($action === 'get_options') {
    $challenge = random_bytes(32);
    $_SESSION['biometric_register_challenge'] = base64_encode($challenge);

    $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'challenge' => $_SESSION['biometric_register_challenge'],
        'user' => [
            'id' => base64_encode(strval($user_id)),
            'name' => $user['email'] ?? "user_$user_id",
            'displayName' => $user['full_name'] ?? 'Strand-Sync User'
        ]
    ]);
    exit;
}

// Save Credential
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $credential_id = $input['id'] ?? '';
    $public_key = $input['rawId'] ?? '';

    if (empty($credential_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid credential payload received.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO user_biometric_credentials (user_id, credential_id, public_key, created_at)
            VALUES (:user_id, :cred_id, :pub_key, NOW())
            ON DUPLICATE KEY UPDATE public_key = VALUES(public_key)
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'cred_id' => $credential_id,
            'pub_key' => $public_key
        ]);

        $updateStmt = $conn->prepare("UPDATE users SET security_preference = 'biometric' WHERE id = :id");
        $updateStmt->execute(['id' => $user_id]);

        $role = $_SESSION['role'] ?? 'student';
        $redirect_url = '../student/student_dashboard.php';

        if (in_array($role, ['super_admin', 'admin'])) {
            $redirect_url = '../admin/admin_dashboard.php';
        } elseif (in_array($role, ['adviser', 'faculty'])) {
            $redirect_url = '../faculty/faculty_dashboard.php';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Fingerprint registered and enabled successfully!',
            'redirect' => $redirect_url
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}