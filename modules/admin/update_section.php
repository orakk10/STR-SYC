<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_sections.php?error=invalid_request');
    exit();
}

$id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$section_name = trim($_POST['section_name'] ?? '');
$grade_level = isset($_POST['grade_level']) ? (int) $_POST['grade_level'] : 0;
$strand_id = isset($_POST['strand_id']) ? (int) $_POST['strand_id'] : 0;
$adviser_id = isset($_POST['adviser_id']) && $_POST['adviser_id'] !== '' ? (int) $_POST['adviser_id'] : null;

if ($id <= 0 || $section_name === '' || $grade_level <= 0 || $strand_id <= 0) {
    header('Location: manage_sections.php?error=missing_data');
    exit();
}

$stmt = $conn->prepare('UPDATE sections SET section_name = ?, grade_level = ?, strand_id = ?, adviser_id = ? WHERE id = ?');
$stmt->execute([$section_name, $grade_level, $strand_id, $adviser_id, $id]);

header('Location: manage_sections.php?success=updated');
exit();
