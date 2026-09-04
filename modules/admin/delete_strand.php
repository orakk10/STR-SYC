<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $conn->prepare('DELETE FROM strands WHERE id = ?')->execute([$id]);
    header('Location: manage_strands.php?success=deleted');
} else {
    header('Location: manage_strands.php?error=invalid_id');
}
exit();
