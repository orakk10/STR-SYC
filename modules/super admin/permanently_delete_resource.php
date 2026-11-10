<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

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
    header('Location: resource_archive.php?status=resource_action_failed');
    exit();
}

$archiveId = filter_input(INPUT_POST, 'archive_id', FILTER_VALIDATE_INT);
if (!$archiveId || $archiveId < 1) {
    header('Location: resource_archive.php?status=resource_action_failed');
    exit();
}

$conn = getDBConnection();
$tables = [
    'strand' => 'strands',
    'subject' => 'subjects',
    'section' => 'sections',
    'assignment' => 'subject_assignments',
    'schedule' => 'schedules',
];

try {
    $conn->beginTransaction();
    $archiveStmt = $conn->prepare(
        'SELECT resource_type, resource_id FROM resource_archives WHERE id = ? FOR UPDATE'
    );
    $archiveStmt->execute([$archiveId]);
    $archive = $archiveStmt->fetch(PDO::FETCH_ASSOC);
    if (!$archive || !isset($tables[$archive['resource_type']])) {
        throw new RuntimeException('The archived item could not be found.');
    }

    $type = $archive['resource_type'];
    $resourceId = (int) $archive['resource_id'];
    $table = $tables[$type];
    $resourceStmt = $conn->prepare("SELECT id FROM {$table} WHERE id = ? FOR UPDATE");
    $resourceStmt->execute([$resourceId]);
    if (!$resourceStmt->fetchColumn()) {
        throw new RuntimeException('The original item no longer exists.');
    }

    $dependentArchiveDeletes = [
        'strand' => [
            ["DELETE FROM resource_archives WHERE resource_type = 'section' AND resource_id IN (SELECT id FROM sections WHERE strand_id = ?)", [$resourceId]],
            ["DELETE FROM resource_archives WHERE resource_type = 'subject' AND resource_id IN (SELECT id FROM subjects WHERE strand_id = ?)", [$resourceId]],
            ["DELETE FROM resource_archives WHERE resource_type = 'assignment' AND resource_id IN (SELECT id FROM subject_assignments WHERE section_id IN (SELECT id FROM sections WHERE strand_id = ?) OR subject_id IN (SELECT id FROM subjects WHERE strand_id = ?))", [$resourceId, $resourceId]],
            ["DELETE FROM resource_archives WHERE resource_type = 'schedule' AND resource_id IN (SELECT id FROM schedules WHERE section_id IN (SELECT id FROM sections WHERE strand_id = ?) OR subject_id IN (SELECT id FROM subjects WHERE strand_id = ?))", [$resourceId, $resourceId]],
        ],
        'section' => [
            ["DELETE FROM resource_archives WHERE resource_type = 'assignment' AND resource_id IN (SELECT id FROM subject_assignments WHERE section_id = ?)", [$resourceId]],
            ["DELETE FROM resource_archives WHERE resource_type = 'schedule' AND resource_id IN (SELECT id FROM schedules WHERE section_id = ?)", [$resourceId]],
        ],
        'subject' => [
            ["DELETE FROM resource_archives WHERE resource_type = 'assignment' AND resource_id IN (SELECT id FROM subject_assignments WHERE subject_id = ?)", [$resourceId]],
            ["DELETE FROM resource_archives WHERE resource_type = 'schedule' AND resource_id IN (SELECT id FROM schedules WHERE subject_id = ?)", [$resourceId]],
        ],
        'assignment' => [],
        'schedule' => [],
    ];
    foreach ($dependentArchiveDeletes[$type] as [$deleteSql, $params]) {
        $conn->prepare($deleteSql)->execute($params);
    }

    $conn->prepare('DELETE FROM resource_archives WHERE id = ?')->execute([$archiveId]);
    $conn->prepare(
        'INSERT INTO activity_logs (user_id, action, affected_table, ip_address) VALUES (?, ?, ?, ?)'
    )->execute([
        (int) $_SESSION['user_id'],
        "Permanently deleted archived {$type} {$resourceId}",
        $table,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $conn->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$resourceId]);
    $conn->commit();
    header('Location: resource_archive.php?status=resource_deleted');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Permanent resource deletion failed: ' . $e->getMessage());
    header('Location: resource_archive.php?status=resource_action_failed');
    exit();
}
