<?php
// modules/auth/verify-2fa.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Session Authorization Check
if (!isset($_SESSION['pending_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No pending authentication session found. Please log in again.']);
    exit();
}

// Support both standard POST forms and JSON fetch payloads
$jsonInput = json_decode(file_get_contents('php://input'), true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$pdo = getDBConnection();
$user_id = intval($_SESSION['pending_user_id']);
$type = $input['type'] ?? '';
$value = $input['value'] ?? $input['pin'] ?? $input['pattern'] ?? '';

if (empty($type) || empty($value)) {
    echo json_encode(['success' => false, 'message' => 'Missing verification parameters.']);
    exit();
}

try {
    // 2. Fetch security hashes and user role
    $stmt = $pdo->prepare("SELECT role, security_pin_hash, security_pattern_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User record not found.']);
        exit();
    }

    $isValid = false;

    // 3. Verify PIN or Pattern credential
    if ($type === 'pin' && !empty($user['security_pin_hash'])) {
        if (password_verify($value, $user['security_pin_hash'])) {
            $isValid = true;
        }
    } elseif ($type === 'pattern' && !empty($user['security_pattern_hash'])) {
        if (password_verify($value, $user['security_pattern_hash'])) {
            $isValid = true;
        }
    }

    if ($isValid) {
        // 4. Elevate session to active authenticated state
        $_SESSION['user_id'] = $user_id;
        if (!empty($_SESSION['pending_role'])) {
            $_SESSION['role'] = $_SESSION['pending_role'];
        } else if (!empty($user['role'])) {
            $_SESSION['role'] = $user['role'];
        }

        // Clear temporary 2FA pending session flags
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_role']);
        unset($_SESSION['pending_security_pref']);

        // Determine dashboard redirect destination based on role
        $role = strtolower($_SESSION['role'] ?? '');
        $redirect = '../../index.php';

        if ($role === 'student') {
            $redirect = '../student/student_dashboard.php';
        } else if (in_array($role, ['adviser', 'faculty'])) {
            $redirect = '../faculty/faculty_dashboard.php';
        } else if (in_array($role, ['super_admin', 'admin', 'super admin'])) {
            $redirect = '../admin/admin_dashboard.php';
        }

        echo json_encode([
            'success' => true, 
            'message' => ucfirst($type) . ' verified successfully.',
            'redirect' => $redirect
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Verification failed. Incorrect ' . strtoupper($type) . '.']);
    }
} catch (PDOException $e) {
    error_log("2FA Verification DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database exception encountered during verification.']);
} catch (Exception $e) {
    error_log("2FA Verification Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
exit();