<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user id']);
    exit();
}

$stmt = $conn->prepare('SELECT u.*, sp.birthdate, sp.gender, sp.address, sp.contact_no, sp.guardian_name, sp.guardian_contact, sp.specialization FROM users u LEFT JOIN student_profiles sp ON sp.user_id = u.id WHERE u.id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

header('Content-Type: application/json');
echo json_encode($user);
exit();
