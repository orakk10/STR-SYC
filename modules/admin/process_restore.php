<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: admin_archive.php?status=error');
    exit();
}

$student = $conn->prepare('SELECT * FROM archived_students WHERE id = ? LIMIT 1');
$student->execute([$id]);
$row = $student->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    header('Location: admin_archive.php?status=error');
    exit();
}

$insert = $conn->prepare('INSERT INTO users (username, full_name, role, section_id, password, reset_requested, require_reset) VALUES (?, ?, ?, ?, ?, 0, 1)');
$insert->execute([
    $row['username'] ?: ('LRN_' . $row['original_id']),
    $row['full_name'],
    'student',
    $row['section_id'],
    password_hash('strand1234', PASSWORD_DEFAULT)
]);

$conn->prepare('DELETE FROM archived_students WHERE id = ?')->execute([$id]);
header('Location: admin_archive.php?status=restored');
exit();
