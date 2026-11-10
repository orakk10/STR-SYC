<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dashboardUrl = 'resource_archive.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : ''
    )
) {
    header('Location: ' . $dashboardUrl . '?status=resource_action_failed');
    exit();
}

$archiveId = filter_input(INPUT_POST, 'archive_id', FILTER_VALIDATE_INT);
if (!$archiveId || $archiveId < 1) {
    header('Location: ' . $dashboardUrl . '?status=resource_action_failed');
    exit();
}

$conn = getDBConnection();
try {
    $conn->beginTransaction();
    $archiveStmt = $conn->prepare(
        'SELECT resource_type, resource_id FROM resource_archives WHERE id = ? FOR UPDATE'
    );
    $archiveStmt->execute([$archiveId]);
    $archive = $archiveStmt->fetch(PDO::FETCH_ASSOC);
    if (!$archive) {
        throw new RuntimeException('The archived item could not be found.');
    }

    $tables = [
        'strand' => 'strands',
        'subject' => 'subjects',
        'section' => 'sections',
        'assignment' => 'subject_assignments',
        'schedule' => 'schedules',
    ];
    $table = $tables[$archive['resource_type']] ?? null;
    if ($table === null) {
        throw new RuntimeException('The archived item type is invalid.');
    }
    $resourceStmt = $conn->prepare("SELECT id FROM {$table} WHERE id = ? FOR UPDATE");
    $resourceStmt->execute([(int) $archive['resource_id']]);
    if (!$resourceStmt->fetchColumn()) {
        throw new RuntimeException('The original item no longer exists.');
    }

    $conn->prepare('DELETE FROM resource_archives WHERE id = ?')->execute([$archiveId]);
    $conn->prepare(
        'INSERT INTO activity_logs (user_id, action, affected_table, ip_address) VALUES (?, ?, ?, ?)'
    )->execute([
        (int) $_SESSION['user_id'],
        "Restored archived {$archive['resource_type']} {$archive['resource_id']}",
        $table,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $conn->commit();
    header('Location: ' . $dashboardUrl . '?status=resource_restored');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Resource restore failed: ' . $e->getMessage());
    header('Location: ' . $dashboardUrl . '?status=resource_action_failed');
    exit();
}
