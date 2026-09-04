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
$grade_level = isset($_POST['grade_level']) ? (int) $_POST['grade_level'] : 0;
$semester = trim($_POST['semester'] ?? '');
$subject_code = trim($_POST['subject_code'] ?? '');
$subject_name = trim($_POST['subject_name'] ?? '');

if ($id <= 0 || $strand_id <= 0 || $grade_level <= 0 || $semester === '' || $subject_code === '' || $subject_name === '') {
    header('Location: curriculum_guide.php?error=missing_fields');
    exit();
}

$stmt = $conn->prepare('UPDATE subjects SET strand_id = ?, grade_level = ?, semester = ?, subject_code = ?, subject_name = ? WHERE id = ?');
$stmt->execute([$strand_id, $grade_level, $semester, $subject_code, $subject_name, $id]);

header('Location: curriculum_guide.php');
exit();
