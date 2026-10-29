<?php
// modules/auth/verify-pin.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Session Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Parse both raw JSON inputs and standard POST form fields
$jsonInput = json_decode(file_get_contents('php://input'), true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);

$type = $input['type'] ?? 'pin';
$pin = $input['pin'] ?? $input['credential'] ?? '';
$pattern = $input['pattern'] ?? $input['credential'] ?? '';

// 2. Validate Inputs
if (($type === 'pin' && empty($pin)) || ($type === 'pattern' && empty($pattern))) {
    echo json_encode(['success' => false, 'message' => 'Missing security parameters.']);
    exit;
}

try {
    // 3. Fetch security credentials
    $stmt = $conn->prepare("SELECT security_pin_hash, security_pattern_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User record not found.']);
        exit;
    }

    // 4. Verify PIN
    if ($type === 'pin') {
        if (empty($user['security_pin_hash'])) {
            echo json_encode(['success' => false, 'message' => 'No security PIN configured for this account.']);
            exit;
        }

        if (password_verify($pin, $user['security_pin_hash'])) {
            echo json_encode(['success' => true, 'message' => 'PIN verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect PIN.']);
        }
    } 
    // 5. Verify Pattern
    else if ($type === 'pattern') {
        if (empty($user['security_pattern_hash'])) {
            echo json_encode(['success' => false, 'message' => 'No security pattern configured for this account.']);
            exit;
        }

        if (password_verify($pattern, $user['security_pattern_hash'])) {
            echo json_encode(['success' => true, 'message' => 'Pattern verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect Pattern.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid security verification type.']);
    }

} catch (PDOException $e) {
    error_log("PIN/Pattern Verification DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database exception encountered.']);
} catch (Exception $e) {
    error_log("PIN/Pattern Verification Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Verification failed: ' . $e->getMessage()]);
}
exit;