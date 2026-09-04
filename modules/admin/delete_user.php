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
    header('Location: manage_users.php?msg=error');
    exit();
}

if ((int) $_SESSION['user_id'] === $id) {
    header('Location: manage_users.php?msg=error_self_delete');
    exit();
}

$conn->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
header('Location: manage_users.php?msg=success');
exit();
