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

$user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
if ($user_id <= 0) {
    header('Location: manage_users.php?msg=error');
    exit();
}

$section_id = isset($_POST['section_id']) && $_POST['section_id'] !== '' ? (int) $_POST['section_id'] : null;
$gender = trim($_POST['gender'] ?? '');
$birthdate = $_POST['birthdate'] ?? null;
$contact_no = trim($_POST['contact_no'] ?? '');
$address = trim($_POST['address'] ?? '');
$guardian_name = trim($_POST['guardian_name'] ?? '');
$guardian_contact = trim($_POST['guardian_contact'] ?? '');
$specialization = trim($_POST['specialization'] ?? '');

$conn->prepare('UPDATE users SET section_id = ? WHERE id = ?')->execute([$section_id, $user_id]);
$conn->prepare('INSERT INTO student_profiles (user_id, gender, birthdate, contact_no, address, guardian_name, guardian_contact, specialization) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE gender = VALUES(gender), birthdate = VALUES(birthdate), contact_no = VALUES(contact_no), address = VALUES(address), guardian_name = VALUES(guardian_name), guardian_contact = VALUES(guardian_contact), specialization = VALUES(specialization)')->execute([$user_id, $gender, $birthdate, $contact_no, $address, $guardian_name, $guardian_contact, $specialization]);

header('Location: manage_users.php?msg=success');
exit();
