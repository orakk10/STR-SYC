<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$conn = getDBConnection();
$adminCount = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$accountArchiveCount = (int) $conn->query('SELECT COUNT(*) FROM account_archives')->fetchColumn();
$resourceArchiveCount = (int) $conn->query('SELECT COUNT(*) FROM resource_archives')->fetchColumn();

$pageTitle = 'Super Admin Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/_super_admin_header.php';
?>
<header class="page-header">
    <h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Super Admin')[0], ENT_QUOTES, 'UTF-8') ?></h1>
    <p>Manage administrator accounts and review archived accounts or academic resources.</p>
</header>

<section class="dashboard-cards" aria-label="Super admin management overview">
    <a class="dashboard-card" href="admin_management.php">
        <h2>Admin Management</h2>
        <div class="number"><?= $adminCount ?></div>
        <p>Manage current admin accounts</p>
    </a>
    <a class="dashboard-card" href="account_archive.php">
        <h2>Account Archive</h2>
        <div class="number"><?= $accountArchiveCount ?></div>
        <p>Accounts waiting for review</p>
    </a>
    <a class="dashboard-card" href="resource_archive.php">
        <h2>Resource Archive</h2>
        <div class="number"><?= $resourceArchiveCount ?></div>
        <p>Academic resources waiting for review</p>
    </a>
</section>
<?php require __DIR__ . '/_super_admin_footer.php'; ?>
