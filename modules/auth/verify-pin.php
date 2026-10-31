<?php
// modules/auth/verify-pin.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Session Check (Supports both pending 2FA login & active user setting checks)
$user_id = $_SESSION['user_id'] ?? $_SESSION['pending_user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in again.']);
    exit;
}

$user_id = intval($user_id);

// Parse both raw JSON inputs and standard POST form fields
$jsonInput = json_decode(file_get_contents('php://input'), true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$conn = getDBConnection();

$type = $input['type'] ?? 'pin';
$pin = $input['pin'] ?? $input['credential'] ?? $input['value'] ?? '';
$pattern = $input['pattern'] ?? $input['credential'] ?? $input['value'] ?? '';

// 2. Validate Inputs
if (($type === 'pin' && empty($pin)) || ($type === 'pattern' && empty($pattern))) {
    echo json_encode(['success' => false, 'message' => 'Missing security parameters.']);
    exit;
}

try {
    // 3. Fetch security credentials & role
    $stmt = $conn->prepare("SELECT role, security_pin_hash, security_pattern_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User record not found.']);
        exit;
    }

    $is_verified = false;

    // 4. Verify PIN
    if ($type === 'pin') {
        if (empty($user['security_pin_hash'])) {
            echo json_encode(['success' => false, 'message' => 'No security PIN configured for this account.']);
            exit;
        }

        if (password_verify($pin, $user['security_pin_hash'])) {
            $is_verified = true;
        }
    } 
    // 5. Verify Pattern
    else if ($type === 'pattern') {
        if (empty($user['security_pattern_hash'])) {
            echo json_encode(['success' => false, 'message' => 'No security pattern configured for this account.']);
            exit;
        }

        if (password_verify($pattern, $user['security_pattern_hash'])) {
            $is_verified = true;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid security verification type.']);
        exit;
    }

    if ($is_verified) {
        // Elevate pending session to fully authenticated active session
        $_SESSION['user_id'] = $user_id;
        if (!empty($_SESSION['pending_role'])) {
            $_SESSION['role'] = $_SESSION['pending_role'];
        } else if (!empty($user['role'])) {
            $_SESSION['role'] = $user['role'];
        }

        // Clear temporary pending 2FA flags
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_role']);
        unset($_SESSION['pending_security_pref']);

        // Determine redirect route based on role
        $role = strtolower($_SESSION['role'] ?? '');
        $redirect = '../../index.php';

        if ($role === 'student') {
            $redirect = '../student/student_dashboard.php';
        } else if ($role === 'faculty') {
            $redirect = '../faculty/faculty_dashboard.php';
        } else if ($role === 'admin') {
            $redirect = '../admin/admin_dashboard.php';
        }

        echo json_encode([
            'success' => true, 
            'message' => ucfirst($type) . ' verified successfully.',
            'redirect' => $redirect
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect ' . strtoupper($type) . '.']);
    }

} catch (PDOException $e) {
    error_log("PIN/Pattern Verification DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database exception encountered.']);
} catch (Exception $e) {
    error_log("PIN/Pattern Verification Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Verification failed: ' . $e->getMessage()]);
}
exit;