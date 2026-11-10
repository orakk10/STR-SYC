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

$archivedAccountsStatement = $conn->query(
    "SELECT aa.user_id, aa.original_role, aa.archived_at,
            u.full_name, u.username, u.lrn, archived_by.full_name AS archived_by_name
     FROM account_archives aa
     INNER JOIN users u ON u.id = aa.user_id
     LEFT JOIN users archived_by ON archived_by.id = aa.archived_by
     ORDER BY aa.archived_at DESC, u.full_name ASC"
);
$archivedAccounts = $archivedAccountsStatement->fetchAll();

$statusMessages = [
    'restored' => ['success', 'Account restored successfully.'],
    'purged' => ['success', 'Account permanently deleted.'],
    'archive_action_failed' => ['error', 'The account archive action could not be completed. No changes were made.'],
];
$notice = $statusMessages[$_GET['status'] ?? ''] ?? null;
$pageTitle = 'Account Archive';
$activePage = 'accounts';
require __DIR__ . '/_super_admin_header.php';
?>
<header class="page-header">
    <h1>Account Archive</h1>
    <p>Review archived accounts. Restore an account to its original role or permanently delete it and its related records.</p>
</header>

<?php if ($notice): ?>
    <div class="alert alert-<?= htmlspecialchars($notice[0], ENT_QUOTES, 'UTF-8') ?> notice" role="status">
        <?= htmlspecialchars($notice[1], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="table-container panel" aria-labelledby="account-archive-heading">
    <h2 id="account-archive-heading">Archived Accounts (<?= count($archivedAccounts) ?>)</h2>
    <div class="admin-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Full name</th>
                    <th>Username / LRN</th>
                    <th>Original role</th>
                    <th>Archived</th>
                    <th>Archived by</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($archivedAccounts): ?>
                    <?php foreach ($archivedAccounts as $account): ?>
                        <tr>
                            <td data-label="Full name"><?= htmlspecialchars($account['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Username / LRN"><?= htmlspecialchars($account['lrn'] ?: $account['username'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Original role"><?= htmlspecialchars(ucfirst($account['original_role']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Archived"><?= htmlspecialchars($account['archived_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Archived by"><?= htmlspecialchars($account['archived_by_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Actions">
                                <form method="POST" action="../admin/restore_account.php" onsubmit="return confirm('Restore this account and allow the user to sign in again?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>">
                                    <button type="submit" class="btn-edit">Restore</button>
                                </form>
                                <form method="POST" action="permanently_delete_account.php" onsubmit="return confirm('Permanently delete this account and its related records? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>">
                                    <button type="submit" class="btn-delete delete-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="empty-state">No accounts are waiting for review.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_super_admin_footer.php'; ?>
