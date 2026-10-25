<?php
// modules/auth/save-security-preference.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$pref    = $_POST['security_preference'] ?? 'none';
$pin     = $_POST['pin'] ?? '';
$pattern = $_POST['pattern'] ?? '';

$allowed_prefs = ['biometric', 'pin', 'pattern', 'none'];
if (!in_array($pref, $allowed_prefs)) {
    echo json_encode(['success' => false, 'message' => 'Invalid preference selection.']);
    exit;
}

try {
    $pin_hash = !empty($pin) ? password_hash($pin, PASSWORD_BCRYPT) : null;
    $pattern_hash = !empty($pattern) ? password_hash($pattern, PASSWORD_BCRYPT) : null;

    $stmt = $conn->prepare("
        UPDATE users 
        SET security_preference = :pref,
            security_pin_hash = COALESCE(:pin_hash, security_pin_hash),
            security_pattern_hash = COALESCE(:pattern_hash, security_pattern_hash)
        WHERE id = :id
    ");

    $stmt->execute([
        'pref' => $pref,
        'pin_hash' => $pin_hash,
        'pattern_hash' => $pattern_hash,
        'id' => $user_id
    ]);

    // Map redirects to your actual directory structure
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
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $e->getMessage()]);
}