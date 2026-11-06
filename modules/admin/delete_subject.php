<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$curriculum_version = $_GET['version'] ?? 'Strengthened';
$redirect_url = 'curriculum_guide.php?version=' . urlencode($curriculum_version);

if ($id > 0) {
    try {
        $conn->beginTransaction();

        // Fetch subject details prior to deletion for logging metadata
        $fetchSub = $conn->prepare('SELECT * FROM subjects WHERE id = ?');
        $fetchSub->execute([$id]);
        $deletedSubject = $fetchSub->fetch(PDO::FETCH_ASSOC);

        $conn->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);

        if ($deletedSubject) {
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
                VALUES (:user_id, :action, 'subjects', :ip_address, :details)
            ");
            $logStmt->execute([
                'user_id'    => (int) $_SESSION['user_id'],
                'action'     => 'DELETE_SUBJECT',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'details'    => json_encode([
                    'subject_id'         => $id,
                    'subject_code'       => $deletedSubject['subject_code'],
                    'subject_name'       => $deletedSubject['subject_name'],
                    'grade_level'        => $deletedSubject['grade_level'],
                    'term'               => $deletedSubject['term'],
                    'curriculum_version' => $deletedSubject['curriculum_version']
                ])
            ]);
        }

        $conn->commit();
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Subject deletion failed: ' . $e->getMessage());
        header('Location: ' . $redirect_url . '&error=delete_failed');
        exit();
    }
} else {
    header('Location: ' . $redirect_url . '&error=invalid_id');
    exit();
}