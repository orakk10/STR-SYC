<?php
// modules/auth/save-security-preference.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Session Authorization Check
if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Support both standard POST forms and JSON fetch payloads
$jsonInput = json_decode(file_get_contents('php://input'), true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$pref    = $input['security_preference'] ?? 'none';
$pin     = $input['pin'] ?? '';
$pattern = $input['pattern'] ?? '';

$allowed_prefs = ['biometric', 'pin', 'pattern', 'none'];
if (!in_array($pref, $allowed_prefs)) {
    echo json_encode(['success' => false, 'message' => 'Invalid preference selection.']);
    exit;
}

try {
    // 2. Fetch existing hashes to preserve or overwrite cleanly
    $stmtSelect = $conn->prepare("SELECT security_pin_hash, security_pattern_hash FROM users WHERE id = :id LIMIT 1");
    $stmtSelect->execute(['id' => $user_id]);
    $existing = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    $pin_hash = !empty($pin) ? password_hash($pin, PASSWORD_BCRYPT) : ($existing['security_pin_hash'] ?? null);
    $pattern_hash = !empty($pattern) ? password_hash($pattern, PASSWORD_BCRYPT) : ($existing['security_pattern_hash'] ?? null);

    // 3. Update security preference and hashes
    $stmt = $conn->prepare("
        UPDATE users 
        SET security_preference = :pref,
            security_pin_hash = :pin_hash,
            security_pattern_hash = :pattern_hash
        WHERE id = :id
    ");

    $stmt->execute([
        'pref' => $pref,
        'pin_hash' => $pin_hash,
        'pattern_hash' => $pattern_hash,
        'id' => $user_id
    ]);

    // 4. Map redirect destination based on session user role
    $role = $_SESSION['role'] ?? 'student';
    $redirect_url = '../student/student_dashboard.php';

    if (in_array($role, ['super_admin', 'admin'])) {
        $redirect_url = '../admin/admin_dashboard.php';
    } elseif (in_array($role, ['adviser', 'faculty'])) {
        $redirect_url = '../faculty/faculty_dashboard.php';
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Security settings saved successfully.',
        'redirect' => $redirect_url
    ]);
} catch (PDOException $e) {
    error_log("Save Security Preference DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
} catch (Exception $e) {
    error_log("Save Security Preference Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
exit;