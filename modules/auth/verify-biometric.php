<?php
// modules/auth/verify-biometric.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Session Check (Supports both pending 2FA login & active user checks)
$user_id = $_SESSION['user_id'] ?? $_SESSION['pending_user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in again.']);
    exit;
}

$user_id = intval($user_id);

$input = json_decode(file_get_contents('php://input'), true);
$credential_id = $input['id'] ?? '';
$client_data = $input['response']['clientDataJSON'] ?? $input['clientDataJSON'] ?? '';
$signature = $input['response']['signature'] ?? $input['signature'] ?? '';

if (empty($credential_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing credential payload.']);
    exit;
}

// Optional: Validate session challenge
if (empty($_SESSION['biometric_auth_challenge'])) {
    echo json_encode(['success' => false, 'message' => 'Biometric challenge expired or invalid. Please try again.']);
    exit;
}

$conn = getDBConnection();

try {
    // 2. Fetch biometric credential and user role
    $stmt = $conn->prepare("
        SELECT ubc.id, ubc.counter, u.role 
        FROM user_biometric_credentials ubc
        JOIN users u ON u.id = ubc.user_id
        WHERE ubc.user_id = :user_id AND ubc.credential_id = :cred_id 
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $user_id, 'cred_id' => $credential_id]);
    $cred = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cred) {
        // 3. Increment signature counter to prevent replay attacks
        $updateStmt = $conn->prepare("UPDATE user_biometric_credentials SET counter = counter + 1, last_used_at = NOW() WHERE id = :id");
        $updateStmt->execute(['id' => $cred['id']]);

        // 4. Elevate pending session to fully authenticated active session
        $_SESSION['user_id'] = $user_id;
        if (!empty($_SESSION['pending_role'])) {
            $_SESSION['role'] = $_SESSION['pending_role'];
        } else if (!empty($cred['role'])) {
            $_SESSION['role'] = $cred['role'];
        }

        // Clear temporary pending 2FA flags & biometric challenge session
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_role']);
        unset($_SESSION['pending_security_pref']);
        unset($_SESSION['biometric_auth_challenge']);

        // Determine redirect route based on role
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
            'success' => true, 
            'message' => 'Fingerprint authentication successful.',
            'redirect' => $redirect
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Unrecognized biometric credential.'
        ]);
    }
} catch (PDOException $e) {
    error_log("Biometric Verification DB Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database exception encountered during biometric verification.'
    ]);
} catch (Exception $e) {
    error_log("Biometric Verification Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Verification error: ' . $e->getMessage()
    ]);
}
exit;