<?php
// modules/auth/verify-pin.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);

$type = $_POST['type'] ?? 'pin';
$pin = $_POST['pin'] ?? $_POST['credential'] ?? '';
$pattern = $_POST['pattern'] ?? $_POST['credential'] ?? '';

if (($type === 'pin' && empty($pin)) || ($type === 'pattern' && empty($pattern))) {
    echo json_encode(['success' => false, 'message' => 'Missing security parameters.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT security_pin_hash, security_pattern_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User record not found.']);
        exit;
    }

    if ($type === 'pin') {
        if (password_verify($pin, $user['security_pin_hash'])) {
            echo json_encode(['success' => true, 'message' => 'PIN verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect PIN.']);
        }
    } else if ($type === 'pattern') {
        if (password_verify($pattern, $user['security_pattern_hash'])) {
            echo json_encode(['success' => true, 'message' => 'Pattern verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect Pattern.']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Verification failed: ' . $e->getMessage()]);
}