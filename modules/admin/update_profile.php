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
$gender = trim($_POST['gender'] ?? 'N/A');
if ($gender === '') {
    $gender = 'N/A';
}
$birthdate = $_POST['birthdate'] ?? null;
$contact_no = trim($_POST['contact_no'] ?? '');
$address = trim($_POST['address'] ?? '');
$guardian_name = trim($_POST['guardian_name'] ?? '');
$guardian_contact = trim($_POST['guardian_contact'] ?? '');
$specialization = trim($_POST['specialization'] ?? '');

$user_stmt = $conn->prepare('SELECT role, username, full_name FROM users WHERE id = ? LIMIT 1');
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
if (!$user_info) {
    header('Location: manage_users.php?msg=error');
    exit();
}
$user_role = $user_info['role'];

$is_teacher = in_array($user_role, ['adviser', 'faculty'], true);

try {
    $conn->beginTransaction();
    if ($is_teacher) {
        if ($section_id !== null) {
            $section_exists = $conn->prepare('SELECT id FROM sections WHERE id = ? LIMIT 1');
            $section_exists->execute([$section_id]);
            if (!$section_exists->fetchColumn()) {
                throw new PDOException('Selected advisory section does not exist.');
            }
        }
        $conn->prepare('UPDATE users SET section_id = NULL WHERE id = ?')->execute([$user_id]);
        $conn->prepare('UPDATE sections SET adviser_id = NULL WHERE adviser_id = ?')->execute([$user_id]);
        if ($section_id !== null) {
            $conn->prepare('UPDATE sections SET adviser_id = ? WHERE id = ?')->execute([$user_id, $section_id]);
        }
    } else {
        $conn->prepare('UPDATE users SET section_id = ? WHERE id = ?')->execute([$user_role === 'student' ? $section_id : null, $user_id]);
    }

    $conn->prepare('
        INSERT INTO student_profiles (user_id, gender, birthdate, contact_no, address, guardian_name, guardian_contact, specialization) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            gender = VALUES(gender), 
            birthdate = VALUES(birthdate), 
            contact_no = VALUES(contact_no), 
            address = VALUES(address), 
            guardian_name = VALUES(guardian_name), 
            guardian_contact = VALUES(guardian_contact), 
            specialization = VALUES(specialization)
    ')->execute([$user_id, $gender, $birthdate, $contact_no, $address, $guardian_name, $guardian_contact, $specialization]);

    // Log the profile update action utilizing the structured details column
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
        VALUES (:user_id, :action, 'student_profiles', :ip_address, :details)
    ");
    $logStmt->execute([
        'user_id'    => (int) $_SESSION['user_id'],
        'action'     => 'UPDATE_USER_PROFILE',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'details'    => json_encode([
            'target_user_id' => $user_id,
            'username'       => $user_info['username'],
            'full_name'      => $user_info['full_name'],
            'role'           => $user_role,
            'section_id'     => $section_id
        ])
    ]);

    $conn->commit();
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Failed to update user profile: ' . $e->getMessage());
    header('Location: manage_users.php?msg=error');
    exit();
}

header('Location: manage_users.php?msg=success');
exit();