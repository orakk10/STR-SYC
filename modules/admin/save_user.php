<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_users.php?msg=error');
    exit();
}

$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$role = trim($_POST['role'] ?? 'student');
$section_id = isset($_POST['section_id']) && $_POST['section_id'] !== '' ? (int) $_POST['section_id'] : null;
$password = $_POST['password'] ?? 'strand1234';

if ($full_name === '' || $username === '') {
    header('Location: manage_users.php?msg=error');
    exit();
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (full_name, username, role, section_id, password, reset_requested, require_reset) VALUES (?, ?, ?, ?, ?, 0, 1)');
$stmt->execute([$full_name, $username, $role, $section_id, $hashed]);

header('Location: manage_users.php?msg=success');
exit();
