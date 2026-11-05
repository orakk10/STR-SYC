<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$conn = getDBConnection();
$archiveList = $conn->query("
    SELECT aa.user_id, aa.original_role, aa.archived_at, u.username, u.lrn, u.full_name
    FROM account_archives aa
    INNER JOIN users u ON u.id = aa.user_id
    ORDER BY aa.archived_at DESC, u.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Archive | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        main.content { padding: 30px; }
        .archive-description { color: #64748b; margin-top: 5px; }
        .archive-table-wrap { overflow-x: auto; background: white; padding: 20px; border-radius: 12px; }
        .archive-table-wrap table { width: 100%; border-collapse: collapse; }
        .archive-table-wrap th, .archive-table-wrap td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .restore-button { background: #2563eb; color: white; border: 0; border-radius: 6px; padding: 8px 12px; cursor: pointer; }
        .empty-row { text-align: center !important; color: #64748b; padding: 30px !important; }
        @media (max-width: 768px) { main.content { margin-left: 0 !important; padding: 20px 15px; } }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="sidebar-header"><h3>STRAND-SYNC</h3></div>
            <ul class="menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_strands.php">Manage Strands</a></li>
                <li><a href="curriculum_guide.php">Curriculum Guide</a></li>
                <li><a href="manage_sections.php">Manage Sections</a></li>
                <li><a href="manage_assignments.php">Teaching Assignments</a></li>
                <li><a href="admin_schedule.php">Manage Schedules</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="account_archive.php" class="active">Account Archive</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>
        <main class="content">
            <header class="content-header">
                <h1>Account Archive</h1>
                <p class="archive-description">Removed accounts are kept here separately from graduate records. Restoring an account returns its original role and access.</p>
            </header>

            <?php if (($_GET['status'] ?? '') === 'restored'): ?>
                <div role="status" class="alert alert-success">Account restored successfully.</div>
            <?php elseif (($_GET['status'] ?? '') === 'error'): ?>
                <div role="alert" class="alert alert-error">The account could not be restored. No changes were made.</div>
            <?php endif; ?>

            <section class="archive-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Username / LRN</th>
                            <th>Original Role</th>
                            <th>Archived</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($archiveList): ?>
                            <?php foreach ($archiveList as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['lrn'] ?: $row['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(ucfirst($row['original_role']), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['archived_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <form action="restore_account.php" method="POST" onsubmit="return confirm('Restore this account and allow the user to sign in again?')">
                                            <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="restore-button">Restore</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td class="empty-row" colspan="5">No archived accounts.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>
</html>
