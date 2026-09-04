<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$section_id = isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0;
if ($section_id <= 0) {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare('SELECT s.id, s.subject_name, s.subject_code FROM subjects s JOIN sections sec ON sec.strand_id = s.strand_id WHERE sec.id = ? ORDER BY s.subject_name ASC');
$stmt->execute([$section_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($subjects);
exit();
