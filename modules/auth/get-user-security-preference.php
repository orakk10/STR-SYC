<?php
// modules/auth/get-user-security-preference.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
    echo json_encode(['success' => false, 'security_preference' => 'none']);
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);

try {
    $stmt = $conn->prepare("SELECT security_preference FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'security_preference' => $user['security_preference'] ?? 'none'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'security_preference' => 'none']);
}