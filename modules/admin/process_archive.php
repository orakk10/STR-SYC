<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_archive.php?status=error');
    exit();
}

$stmt = $conn->query("SELECT u.id, u.username, u.full_name, u.section_id, s.strand_id FROM users u LEFT JOIN sections s ON s.id = u.section_id WHERE u.role = 'student'");
while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $check = $conn->prepare('SELECT id FROM archived_students WHERE original_id = ? LIMIT 1');
    $check->execute([$student['id']]);
    if ($check->fetchColumn() === false) {
        $insert = $conn->prepare('INSERT INTO archived_students (original_id, lrn, username, full_name, strand_id, section_id, batch_year) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $student['id'],
            $student['username'],
            $student['username'],
            $student['full_name'],
            $student['strand_id'],
            $student['section_id'],
            date('Y')
        ]);
    }
}

$conn->prepare("DELETE FROM users WHERE role = 'student'")->execute();
header('Location: admin_archive.php?status=success');
exit();
