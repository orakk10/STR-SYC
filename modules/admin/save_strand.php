<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_strands.php?error=invalid_request');
    exit();
}

$strand_id = isset($_POST['strand_id']) ? (int) $_POST['strand_id'] : 0;
$strand_name = trim($_POST['strand_name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($strand_name === '') {
    header('Location: manage_strands.php?error=missing_name');
    exit();
}

if ($strand_id > 0) {
    $stmt = $conn->prepare('UPDATE strands SET strand_name = ?, description = ? WHERE id = ?');
    $stmt->execute([$strand_name, $description, $strand_id]);
    $msg = 'updated';
} else {
    $stmt = $conn->prepare('INSERT INTO strands (strand_name, description) VALUES (?, ?)');
    $stmt->execute([$strand_name, $description]);
    $msg = 'created';
}

header('Location: manage_strands.php?success=' . urlencode($msg));
exit();
