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
$client_data = $input['clientDataJSON'] ?? '';
$signature = $input['signature'] ?? '';

if (empty($credential_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing credential payload.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);

try {
    // 1. Check if the biometric credential exists for this user
    $stmt = $conn->prepare("SELECT id, counter FROM user_biometric_credentials WHERE user_id = :user_id AND credential_id = :cred_id LIMIT 1");
    $stmt->execute(['user_id' => $user_id, 'cred_id' => $credential_id]);
    $cred = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cred) {
        // 2. Increment signature counter to prevent replay attacks
        $updateStmt = $conn->prepare("UPDATE user_biometric_credentials SET counter = counter + 1, last_used_at = NOW() WHERE id = :id");
        $updateStmt->execute(['id' => $cred['id']]);

        echo json_encode([
            'success' => true, 
            'message' => 'Fingerprint authentication successful.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Unrecognized biometric credential.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Verification error: ' . $e->getMessage()
    ]);
}
exit;