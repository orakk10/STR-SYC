<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$resourceTypes = [
    'strand' => ['table' => 'strands', 'title' => 'strand'],
    'subject' => ['table' => 'subjects', 'title' => 'subject'],
    'section' => ['table' => 'sections', 'title' => 'section'],
    'assignment' => ['table' => 'subject_assignments', 'title' => 'teaching assignment'],
    'schedule' => ['table' => 'schedules', 'title' => 'schedule'],
];
$resourceType = is_string($_POST['resource_type'] ?? null) ? $_POST['resource_type'] : '';
$resourceId = filter_input(INPUT_POST, 'resource_id', FILTER_VALIDATE_INT);
$csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

function archiveRedirect(string $type, string $status): never
{
    $routes = [
        'strand' => ['manage_strands.php', []],
        'subject' => ['curriculum_guide.php', ['version' => ['Legacy', 'Strengthened']]],
        'section' => ['manage_sections.php', [
            'academic_year' => ['2025-2026', '2026-2027'],
            'curriculum_version' => ['Legacy', 'Strengthened'],
        ]],
        'assignment' => ['manage_assignments.php', [
            'academic_year' => ['2025-2026', '2026-2027'],
            'semester' => ['1st Term', '2nd Term', '3rd Term', '1st Sem', '2nd Sem', 'Full Year', 'All'],
            'filter_section' => [],
        ]],
        'schedule' => ['admin_schedule.php', ['filter_section' => []]],
    ];
    [$route, $filters] = $routes[$type] ?? ['manage_users.php', []];
    $query = [];
    foreach ($filters as $key => $allowedValues) {
        $value = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
        if ($key === 'filter_section') {
            if ($value !== '' && ctype_digit((string) $value)) {
                $query[$key] = $value;
            }
        } elseif (in_array($value, $allowedValues, true)) {
            $query[$key] = $value;
        }
    }
    $query['msg'] = $status;
    header('Location: ' . $route . '?' . http_build_query($query));
    exit();
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    !isset($resourceTypes[$resourceType]) ||
    !$resourceId ||
    $resourceId < 1 ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    archiveRedirect(isset($resourceTypes[$resourceType]) ? $resourceType : 'strand', 'archive_failed');
}

$conn = getDBConnection();
try {
    $conn->beginTransaction();
    $table = $resourceTypes[$resourceType]['table'];
    $resourceStmt = $conn->prepare("SELECT id FROM {$table} WHERE id = ? FOR UPDATE");
    $resourceStmt->execute([$resourceId]);
    if (!$resourceStmt->fetchColumn()) {
        throw new RuntimeException('The selected item no longer exists.');
    }

    $archiveStmt = $conn->prepare(
        'INSERT INTO resource_archives (resource_type, resource_id, archived_by) VALUES (?, ?, ?)'
    );
    $archiveStmt->execute([$resourceType, $resourceId, (int) $_SESSION['user_id']]);

    $logStmt = $conn->prepare(
        'INSERT INTO activity_logs (user_id, action, affected_table, ip_address) VALUES (?, ?, ?, ?)'
    );
    $logStmt->execute([
        (int) $_SESSION['user_id'],
        'Archived ' . $resourceTypes[$resourceType]['title'] . ' ' . $resourceId,
        $table,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $conn->commit();
    archiveRedirect($resourceType, 'archived');
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Resource archive failed: ' . $e->getMessage());
    archiveRedirect($resourceType, 'archive_failed');
}
