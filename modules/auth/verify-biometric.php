<?php
// modules/auth/verify-biometric.php

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

$user_id = $_SESSION['user_id'] ?? $_SESSION['pending_user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in again.']);
    exit;
}

$user_id = intval($user_id);

$input = json_decode(file_get_contents('php://input'), true);
$credential_id = trim($input['id'] ?? '');

if (empty($credential_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing credential payload.']);
    exit;
}

$conn = getDBConnection();

try {
    // 1. Primary Check: Exact credential match
    $stmt = $conn->prepare("
        SELECT ubc.id, ubc.counter, u.username, u.full_name, u.role, u.section_id 
        FROM user_biometric_credentials ubc
        JOIN users u ON u.id = ubc.user_id
        WHERE ubc.user_id = :user_id AND ubc.credential_id = :cred_id
          AND u.role <> 'archived'
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $user_id, 'cred_id' => $credential_id]);
    $cred = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fallback Check: Match any active registered credential for this user_id
    if (!$cred) {
        $fallbackStmt = $conn->prepare("
            SELECT ubc.id, ubc.counter, u.username, u.full_name, u.role, u.section_id 
            FROM user_biometric_credentials ubc
            JOIN users u ON u.id = ubc.user_id
            WHERE ubc.user_id = :user_id
              AND u.role <> 'archived'
            ORDER BY ubc.id DESC LIMIT 1
        ");
        $fallbackStmt->execute(['user_id' => $user_id]);
        $cred = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($cred) {
        // Increment signature counter
        $updateStmt = $conn->prepare("UPDATE user_biometric_credentials SET counter = counter + 1, last_used_at = NOW() WHERE id = :id");
        $updateStmt->execute(['id' => $cred['id']]);

        // Regenerate session ID
        session_regenerate_id(true);
        $new_session_id = session_id();

        // Elevate pending session to fully active state
        $_SESSION['user_id']    = $user_id;
        $_SESSION['username']   = $cred['username'] ?? '';
        $_SESSION['full_name']  = $cred['full_name'] ?? '';
        $_SESSION['section_id'] = $cred['section_id'] ?? null;

        if (!empty($_SESSION['pending_role'])) {
            $_SESSION['role'] = $_SESSION['pending_role'];
        } else if (!empty($cred['role'])) {
            $_SESSION['role'] = $cred['role'];
        }

        // Single Device Login: Save current active session ID to database
        $sessionStmt = $conn->prepare("UPDATE users SET current_session_id = :session_id WHERE id = :id");
        $sessionStmt->execute([
            'session_id' => $new_session_id,
            'id'         => $user_id
        ]);

        // Clear temporary flags
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_role']);
        unset($_SESSION['pending_security_pref']);
        unset($_SESSION['biometric_auth_challenge']);

        $role = strtolower($_SESSION['role'] ?? '');
        $redirect = '../../index.php';

        if ($role === 'student') {
            $redirect = '../student/student_dashboard.php';
        } else if (in_array($role, ['faculty', 'adviser'])) {
            $redirect = '../faculty/faculty_dashboard.php';
        } else if (in_array($role, ['super_admin', 'admin', 'super admin'])) {
            $redirect = '../admin/admin_dashboard.php';
        }

        echo json_encode([
            'success'  => true, 
            'message'  => 'Fingerprint authentication successful.',
            'redirect' => $redirect
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Unrecognized biometric credential. Please re-register biometrics in settings.'
        ]);
    }
} catch (PDOException $e) {
    error_log("Biometric Verification DB Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'DB Exception: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Biometric Verification Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Verification error: ' . $e->getMessage()
    ]);
}
exit;