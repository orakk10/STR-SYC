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

try {
    $conn->beginTransaction();

    $activeStudents = $conn->query("
        SELECT u.id, u.lrn, u.username, u.full_name, u.section_id, s.strand_id
        FROM users u
        INNER JOIN sections s ON s.id = u.section_id
        WHERE u.role = 'student' AND s.grade_level = 12
        FOR UPDATE
    ")->fetchAll(PDO::FETCH_ASSOC);

    $archiveStmt = $conn->prepare("
        INSERT INTO archived_students
            (original_id, lrn, username, full_name, strand_id, section_id, batch_year)
        SELECT :original_id, :lrn, :username, :full_name, :strand_id, :section_id, :batch_year
        WHERE NOT EXISTS (
            SELECT 1 FROM archived_students WHERE original_id = :existing_original_id
        )
    ");
    foreach ($activeStudents as $student) {
        $archiveStmt->execute([
            'original_id' => $student['id'],
            'lrn' => $student['lrn'],
            'username' => $student['username'],
            'full_name' => $student['full_name'],
            'strand_id' => $student['strand_id'],
            'section_id' => $student['section_id'],
            'batch_year' => date('Y'),
            'existing_original_id' => $student['id']
        ]);
    }

    $updateStudents = $conn->prepare("
        UPDATE users u
        INNER JOIN sections s ON s.id = u.section_id
        SET u.role = 'alumni', u.section_id = NULL
        WHERE u.role = 'student' AND s.grade_level = 12
    ");
    $updateStudents->execute();

    $log = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
        VALUES (:user_id, :action, 'archived_students', :ip_address)
    ");
    $log->execute([
        'user_id' => (int)$_SESSION['user_id'],
        'action' => 'Archived ' . count($activeStudents) . ' Grade 12 students as alumni for batch ' . date('Y') . '.',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    $conn->commit();
    header('Location: admin_archive.php?status=success');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Student archive failed: ' . $e->getMessage());
    header('Location: admin_archive.php?status=error');
    exit();
}
