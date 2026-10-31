<?php
// modules/auth/get-biometric-challenge.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Authorization check (Support both active sessions and pending 2FA challenge sessions)
$user_id = $_SESSION['user_id'] ?? $_SESSION['pending_user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($user_id);

try {
    // 2. Fetch active registered WebAuthn credential ID for user
    $stmt = $conn->prepare("SELECT credential_id FROM user_biometric_credentials WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1");
    $stmt->execute(['user_id' => $user_id]);
    $cred = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cred) {
        echo json_encode([
            'success' => false, 
            'message' => 'No biometric credentials registered. Please configure fingerprint in settings first.'
        ]);
        exit;
    }

    // 3. Generate secure cryptographic challenge
    $challenge = random_bytes(32);
    $_SESSION['biometric_auth_challenge'] = base64_encode($challenge);

    // 4. Return challenge and allowed credential ID to client JS
    echo json_encode([
        'success' => true,
        'challenge' => $_SESSION['biometric_auth_challenge'],
        'credentialId' => $cred['credential_id']
    ]);
} catch (PDOException $e) {
    error_log("Get Biometric Challenge DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database exception encountered.']);
} catch (Exception $e) {
    error_log("Get Biometric Challenge Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
exit;