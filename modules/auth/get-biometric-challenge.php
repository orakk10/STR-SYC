<?php
// modules/auth/get-biometric-challenge.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);

try {
    // Retrieve stored WebAuthn credential ID for current user
    $stmt = $conn->prepare("SELECT credential_id FROM user_biometric_credentials WHERE user_id = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $user_id]);
    $cred = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cred) {
        echo json_encode([
            'success' => false, 
            'message' => 'No biometric credentials registered. Please configure fingerprint in settings first.'
        ]);
        exit;
    }

    $challenge = random_bytes(32);
    $_SESSION['biometric_auth_challenge'] = base64_encode($challenge);

    echo json_encode([
        'success' => true,
        'challenge' => $_SESSION['biometric_auth_challenge'],
        'credentialId' => $cred['credential_id']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;