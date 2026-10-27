<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/GraduateArchive.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: admin_archive.php?status=error');
    exit();
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();

    $activeStudents = $conn->query("
        SELECT u.id, u.lrn, u.username, u.full_name, u.section_id,
               s.strand_id, s.academic_year
        FROM users u
        INNER JOIN sections s ON s.id = u.section_id
        WHERE u.role = 'student' AND s.grade_level = 12
        ORDER BY u.id
        FOR UPDATE
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($activeStudents)) {
        $conn->rollBack();
        header('Location: admin_archive.php?status=no_students');
        exit();
    }

    foreach ($activeStudents as $student) {
        archiveGraduateRecord($conn, $student);
    }

    $studentIds = array_map('intval', array_column($activeStudents, 'id'));
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $updateStudents = $conn->prepare("
        UPDATE users
        SET role = 'alumni', section_id = NULL
        WHERE role = 'student' AND id IN ($placeholders)
    ");
    $updateStudents->execute($studentIds);

    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
        VALUES (:user_id, :action, 'archived_students', :ip_address)
    ");
    $logStmt->execute([
        'user_id' => (int) $_SESSION['user_id'],
        'action' => 'Archived ' . count($activeStudents)
            . ' Grade 12 students as alumni using their section academic years.',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ]);

    $conn->commit();
    header('Location: admin_archive.php?status=archived');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Student archive failed: ' . $e->getMessage());
    header('Location: admin_archive.php?status=error');
    exit();
}
