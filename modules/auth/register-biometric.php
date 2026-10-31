<?php
// modules/auth/register-biometric.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Session Authorization Check
if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in again.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 2. Action: Generate WebAuthn Registration Options (Challenge)
if ($action === 'get_options') {
    $challenge = random_bytes(32);
    $_SESSION['biometric_register_challenge'] = base64_encode($challenge);

    $stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'challenge' => $_SESSION['biometric_register_challenge'],
        'user' => [
            'id' => base64_encode(strval($user_id)),
            'name' => $user['username'] ?? "user_$user_id",
            'displayName' => $user['full_name'] ?? 'STRAND-SYNC User'
        ]
    ]);
    exit;
}

// 3. Action: Store Biometric Credential Payload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $credential_id = $input['id'] ?? '';
    $public_key = $input['rawId'] ?? $input['response']['publicKey'] ?? '';

    if (empty($credential_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid credential payload received.']);
        exit;
    }

    try {
        // Save or update credential ID for user
        $stmt = $conn->prepare("
            INSERT INTO user_biometric_credentials (user_id, credential_id, public_key, created_at)
            VALUES (:user_id, :cred_id, :pub_key, NOW())
            ON DUPLICATE KEY UPDATE 
                public_key = VALUES(public_key),
                created_at = NOW()
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'cred_id' => $credential_id,
            'pub_key' => $public_key
        ]);

        // Update user security preference to biometric
        $updateStmt = $conn->prepare("UPDATE users SET security_preference = 'biometric' WHERE id = :id");
        $updateStmt->execute(['id' => $user_id]);

        // Determine dashboard redirect based on role
        $role = strtolower($_SESSION['role'] ?? 'student');
        $redirect_url = '../student/student_dashboard.php';

        if (in_array($role, ['super_admin', 'admin', 'super admin'])) {
            $redirect_url = '../admin/admin_dashboard.php';
        } elseif (in_array($role, ['adviser', 'faculty'])) {
            $redirect_url = '../faculty/faculty_dashboard.php';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Fingerprint registered and enabled successfully!',
            'redirect' => $redirect_url
        ]);
    } catch (PDOException $e) {
        error_log("Biometric Registration DB Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error during registration.']);
    } catch (Exception $e) {
        error_log("Biometric Registration Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}