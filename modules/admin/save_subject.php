<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

// 2. Request Method Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: curriculum_guide.php?error=invalid_request');
    exit();
}

// 3. Extract and Sanitize Inputs
$subject_id   = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$strand_id    = isset($_POST['strand_id']) ? (int) $_POST['strand_id'] : 0;
$type         = trim($_POST['type'] ?? 'Core');
$grade_level  = isset($_POST['grade_level']) ? (int) $_POST['grade_level'] : 0;
$term         = trim($_POST['term'] ?? '');
$subject_code = trim($_POST['subject_code'] ?? '');
$subject_name = trim($_POST['subject_name'] ?? '');

// Fallback check if the frontend still submits 'semester' instead of 'term'
if (empty($term) && isset($_POST['semester'])) {
    $term = trim($_POST['semester']);
}

// 4. Validate Inputs
if ($strand_id <= 0 || $grade_level <= 0 || $term === '' || $subject_code === '' || $subject_name === '') {
    header('Location: curriculum_guide.php?error=missing_fields');
    exit();
}

// 5. Insert or Update Database Record
if ($subject_id > 0) {
    // Update existing subject
    $stmt = $conn->prepare('UPDATE subjects SET strand_id = ?, type = ?, grade_level = ?, term = ?, subject_code = ?, subject_name = ? WHERE id = ?');
    $stmt->execute([$strand_id, $type, $grade_level, $term, $subject_code, $subject_name, $subject_id]);
} else {
    // Insert new subject
    $stmt = $conn->prepare('INSERT INTO subjects (strand_id, type, grade_level, term, subject_code, subject_name) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$strand_id, $type, $grade_level, $term, $subject_code, $subject_name]);
}

header('Location: curriculum_guide.php?msg=success');
exit();