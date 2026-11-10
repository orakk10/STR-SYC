<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$conn = getDBConnection();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$archivedResourcesStatement = $conn->query(
    "SELECT ra.id, ra.resource_type, ra.resource_id, ra.archived_at,
            archiver.full_name AS archived_by_name,
            CASE ra.resource_type
                WHEN 'strand' THEN strand.strand_name
                WHEN 'subject' THEN subject.subject_name
                WHEN 'section' THEN section.section_name
                WHEN 'assignment' THEN CONCAT('Grade ', assignment_section.grade_level, ' ', assignment_section.section_name, ' - ', assignment_subject.subject_name)
                WHEN 'schedule' THEN CONCAT(schedule_section.section_name, ' - ', schedule_subject.subject_name, ' (', schedule.day_of_week, ' ', TIME_FORMAT(schedule.start_time, '%H:%i'), ')')
            END AS resource_name
     FROM resource_archives ra
     LEFT JOIN users archiver ON archiver.id = ra.archived_by
     LEFT JOIN strands strand ON ra.resource_type = 'strand' AND strand.id = ra.resource_id
     LEFT JOIN subjects subject ON ra.resource_type = 'subject' AND subject.id = ra.resource_id
     LEFT JOIN sections section ON ra.resource_type = 'section' AND section.id = ra.resource_id
     LEFT JOIN subject_assignments assignment ON ra.resource_type = 'assignment' AND assignment.id = ra.resource_id
     LEFT JOIN sections assignment_section ON assignment_section.id = assignment.section_id
     LEFT JOIN subjects assignment_subject ON assignment_subject.id = assignment.subject_id
     LEFT JOIN schedules schedule ON ra.resource_type = 'schedule' AND schedule.id = ra.resource_id
     LEFT JOIN sections schedule_section ON schedule_section.id = schedule.section_id
     LEFT JOIN subjects schedule_subject ON schedule_subject.id = schedule.subject_id
     ORDER BY ra.archived_at DESC, ra.id DESC"
);
$archivedResources = $archivedResourcesStatement->fetchAll();

$statusMessages = [
    'resource_restored' => ['success', 'Archived item restored successfully.'],
    'resource_deleted' => ['success', 'Archived item and its dependent records permanently deleted.'],
    'resource_action_failed' => ['error', 'The archive action could not be completed. No changes were made.'],
];
$notice = $statusMessages[$_GET['status'] ?? ''] ?? null;
$pageTitle = 'Resource Archive';
$activePage = 'resources';
require __DIR__ . '/_super_admin_header.php';
?>
<header class="page-header">
    <h1>Resource Archive</h1>
    <p>Review archived strands, subjects, sections, teaching assignments, and schedules. Restoring makes the item active again; permanent deletion also removes dependent records.</p>
</header>

<?php if ($notice): ?>
    <div class="alert alert-<?= htmlspecialchars($notice[0], ENT_QUOTES, 'UTF-8') ?> notice" role="status">
        <?= htmlspecialchars($notice[1], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="table-container panel" aria-labelledby="resource-archive-heading">
    <h2 id="resource-archive-heading">Archived Academic Resources (<?= count($archivedResources) ?>)</h2>
    <div class="admin-table-wrap">
        <table>
            <thead>
                <tr><th>Type</th><th>Item</th><th>Archived</th><th>Archived by</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($archivedResources): ?>
                    <?php foreach ($archivedResources as $resource): ?>
                        <tr>
                            <td data-label="Type"><?= htmlspecialchars(ucfirst($resource['resource_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Item"><?= htmlspecialchars($resource['resource_name'] ?? 'Related record unavailable', ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Archived"><?= htmlspecialchars($resource['archived_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Archived by"><?= htmlspecialchars($resource['archived_by_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Actions">
                                <form method="POST" action="restore_resource.php" data-confirm-title="Confirm Restore" data-confirm-label="Restore" data-confirm-message="Restore this archived item?">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="archive_id" value="<?= (int) $resource['id'] ?>">
                                    <button type="submit" class="btn-edit">Restore</button>
                                </form>
                                <form method="POST" action="permanently_delete_resource.php" data-confirm-title="Confirm Permanent Deletion" data-confirm-label="Permanently Delete" data-confirm-variant="danger" data-confirm-message="Permanently delete this archived item and its dependent records? This cannot be undone.">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="archive_id" value="<?= (int) $resource['id'] ?>">
                                    <button type="submit" class="btn-delete delete-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty-state">No academic resources are waiting for review.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_super_admin_footer.php'; ?>
