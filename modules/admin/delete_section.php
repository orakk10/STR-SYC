<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$academic_year = $_GET['academic_year'] ?? '2026-2027';
$curriculum_version = $_GET['curriculum_version'] ?? 'Strengthened';
$filters = http_build_query([
    'academic_year' => $academic_year,
    'curriculum_version' => $curriculum_version,
]);

if ($id > 0) {
    try {
        $conn->beginTransaction();

        // Fetch section details prior to deletion for logging metadata
        $fetchSec = $conn->prepare("SELECT * FROM sections WHERE id = ?");
        $fetchSec->execute([$id]);
        $deletedSection = $fetchSec->fetch(PDO::FETCH_ASSOC);

        $conn->prepare('DELETE FROM sections WHERE id = ?')->execute([$id]);

        if ($deletedSection) {
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
                VALUES (:user_id, :action, 'sections', :ip_address, :details)
            ");
            $logStmt->execute([
                'user_id'    => (int) $_SESSION['user_id'],
                'action'     => 'DELETE_SECTION',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'details'    => json_encode([
                    'section_id'         => $id,
                    'section_name'       => $deletedSection['section_name'],
                    'grade_level'        => $deletedSection['grade_level'],
                    'academic_year'      => $deletedSection['academic_year'],
                    'curriculum_version' => $deletedSection['curriculum_version']
                ])
            ]);
        }

        $conn->commit();
        header('Location: manage_sections.php?' . $filters . '&msg=deleted');
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Section deletion failed: ' . $e->getMessage());
        header('Location: manage_sections.php?' . $filters . '&error=delete_failed');
        exit();
    }
} else {
    header('Location: manage_sections.php?' . $filters . '&error=invalid_id');
    exit();
}