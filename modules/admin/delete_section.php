<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$academic_year = $_GET['academic_year'] ?? '2026-2027';
$curriculum_version = $_GET['curriculum_version'] ?? 'Strengthened';
$filters = http_build_query([
    'academic_year' => $academic_year,
    'curriculum_version' => $curriculum_version,
]);

if ($id > 0) {
    $conn->prepare('DELETE FROM sections WHERE id = ?')->execute([$id]);
    header('Location: manage_sections.php?' . $filters . '&msg=deleted');
} else {
    header('Location: manage_sections.php?' . $filters . '&error=invalid_id');
}
exit();
