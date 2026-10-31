<?php
// modules/auth/get-user-security-preference.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Session Authorization Check (Supports active and pending 2FA challenge states)
$user_id = $_SESSION['user_id'] ?? $_SESSION['pending_user_id'] ?? null;

if (!$user_id || intval($user_id) <= 0) {
    echo json_encode([
        'success' => false, 
        'security_preference' => 'none',
        'message' => 'Unauthorized or invalid session.'
    ]);
    exit;
}

$conn = getDBConnection();
$user_id = intval($user_id);

try {
    // 2. Fetch security preference setting for the session user
    $stmt = $conn->prepare("SELECT security_preference FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default to 'none' if the database field is NULL or empty
    $preference = !empty($user['security_preference']) ? $user['security_preference'] : 'none';

    echo json_encode([
        'success' => true,
        'security_preference' => $preference
    ]);
} catch (PDOException $e) {
    error_log("Get Security Preference DB Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'security_preference' => 'none',
        'message' => 'Database error encountered.'
    ]);
} catch (Exception $e) {
    error_log("Get Security Preference Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'security_preference' => 'none',
        'message' => 'Server error encountered.'
    ]);
}
exit;