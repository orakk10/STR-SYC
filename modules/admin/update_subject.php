<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: curriculum_guide.php?error=invalid_request');
    exit();
}

$id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$strand_id = isset($_POST['strand_id']) ? (int) $_POST['strand_id'] : 0;
$type = trim($_POST['type'] ?? 'Core');
$grade_level = isset($_POST['grade_level']) ? (int) $_POST['grade_level'] : 0;
$term = trim($_POST['term'] ?? '');
$subject_code = trim($_POST['subject_code'] ?? '');
$subject_name = trim($_POST['subject_name'] ?? '');

if (
    $id <= 0 ||
    $strand_id <= 0 ||
    !in_array($type, ['Core', 'Applied', 'Specialized'], true) ||
    !in_array($grade_level, [11, 12], true) ||
    !in_array($term, ['1st', '2nd', '3rd'], true) ||
    $subject_code === '' ||
    $subject_name === ''
) {
    header('Location: curriculum_guide.php?error=missing_fields');
    exit();
}

$stmt = $conn->prepare('UPDATE subjects SET strand_id = ?, type = ?, grade_level = ?, term = ?, subject_code = ?, subject_name = ? WHERE id = ?');
$stmt->execute([$strand_id, $type, $grade_level, $term, $subject_code, $subject_name, $id]);

if ($stmt->rowCount() === 0) {
    $existsStmt = $conn->prepare('SELECT 1 FROM subjects WHERE id = ?');
    $existsStmt->execute([$id]);
    if (!$existsStmt->fetchColumn()) {
        header('Location: curriculum_guide.php?error=subject_not_found');
        exit();
    }
}

header('Location: curriculum_guide.php?msg=success');
exit();
