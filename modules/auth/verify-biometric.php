<?php
// modules/auth/verify-biometric.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$credential_id = $input['id'] ?? '';

if (empty($credential_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing credential payload.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);

try {
    $stmt = $conn->prepare("SELECT id FROM user_biometric_credentials WHERE user_id = :user_id AND credential_id = :cred_id LIMIT 1");
    $stmt->execute(['user_id' => $user_id, 'cred_id' => $credential_id]);
    $cred = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cred) {
        echo json_encode(['success' => true, 'message' => 'Fingerprint authentication successful.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unrecognized biometric credential.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Verification error: ' . $e->getMessage()]);
}
exit;