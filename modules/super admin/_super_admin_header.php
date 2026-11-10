<?php
$pageTitle = $pageTitle ?? 'Super Admin';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body { margin: 0; }
        .dashboard-wrapper { min-height: 100vh; }
        .sidebar {
            left: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .sidebar-close {
            display: none;
            border: 0;
            background: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
        }
        .content {
            width: calc(100% - 260px);
            max-width: none;
            min-width: 0;
            height: 100vh;
            margin-left: 260px;
            padding: 30px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 14px;
            left: 14px;
            z-index: 1000;
            padding: 8px 12px;
            border: 0;
            border-radius: 8px;
            background: var(--primary-color);
            color: var(--white);
            font-size: 1.25rem;
            cursor: pointer;
        }
        .page-header { margin-bottom: 24px; }
        .page-header h1 { color: #0f172a; margin-bottom: 4px; }
        .page-header p { color: #64748b; }
        .panel { min-width: 0; padding: 1.5rem; }
        .panel h2 { color: #0f172a; font-size: 1.15rem; margin-bottom: 18px; }
        .notice { margin-bottom: 20px; }
        .admin-table-wrap { width: 100%; min-width: 0; overflow-x: auto; }
        .admin-table-wrap table { min-width: 0; }
        .admin-table-wrap th,
        .admin-table-wrap td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .admin-table-wrap th { color: #475569; font-size: 0.85rem; }
        .delete-button { margin-top: 8px; }
        .empty-state { color: #64748b; padding: 16px 0; }
        .form-field { margin-bottom: 16px; }
        .form-field label { display: block; font-weight: 600; margin-bottom: 6px; }
        .create-button { width: 100%; }
        .edit-dialog {
            position: fixed;
            inset: auto;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(480px, calc(100vw - 32px));
            max-width: 100%;
            max-height: min(720px, calc(100vh - 32px));
            overflow-y: auto;
            margin: 0;
            border: 0;
            border-radius: 1rem;
            color: var(--text-main);
            background: var(--white);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .edit-dialog::backdrop { background: rgb(15 23 42 / 0.58); }
        .edit-dialog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }
        .edit-dialog-header h2 { margin: 0; color: #0f172a; font-size: 1.2rem; }
        .close-dialog-button {
            flex: 0 0 auto;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 8px;
            background: #f1f5f9;
            color: #334155;
            font-size: 1.3rem;
            cursor: pointer;
        }
        .dialog-actions { display: flex; gap: 10px; margin-top: 20px; }
        .dialog-actions .create-button { flex: 1; }
        .cancel-dialog-button {
            padding: 0.6rem 1.2rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            background: var(--white);
            color: var(--text-main);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 20px;
        }
        .dashboard-card {
            display: block;
            padding: 24px;
            border-radius: 12px;
            background: var(--white);
            box-shadow: var(--shadow);
            color: var(--text-main);
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 18px -8px rgb(15 23 42 / 0.25);
        }
        .dashboard-card h2 { margin: 0 0 8px; color: #0f172a; font-size: 1.1rem; }
        .dashboard-card .number { color: var(--primary-color); font-size: 2rem; font-weight: 800; }
        .dashboard-card p { margin: 8px 0 0; color: #64748b; }
        @media (max-width: 1024px) {
            body { overflow: auto; }
            .sidebar { left: -260px; }
            .sidebar.active { left: 0; }
            .sidebar.active .sidebar-close { display: block; }
            .mobile-toggle { display: block; }
            .mobile-toggle.hidden { visibility: hidden; opacity: 0; pointer-events: none; }
            .content {
                width: 100%;
                height: auto;
                min-height: 100vh;
                margin-left: 0;
                padding: 68px 20px 24px;
                overflow: visible;
            }
        }
        @media (max-width: 640px) {
            .content { padding: 68px 16px 20px; }
            .page-header { margin-bottom: 18px; }
            .page-header h1 { font-size: 1.5rem; }
            .page-header p { font-size: 0.95rem; }
            .panel { padding: 18px; }
            .admin-table-wrap { overflow: visible; }
            .admin-table-wrap table,
            .admin-table-wrap tbody { display: block; width: 100%; }
            .admin-table-wrap thead {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }
            .admin-table-wrap tr {
                display: block;
                margin-bottom: 14px;
                padding: 8px 14px;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
            }
            .admin-table-wrap td {
                display: grid;
                grid-template-columns: minmax(82px, 0.45fr) minmax(0, 1fr);
                gap: 12px;
                padding: 10px 0;
                overflow-wrap: anywhere;
                border-bottom: 1px solid #f1f5f9;
            }
            .admin-table-wrap td::before {
                content: attr(data-label);
                color: #64748b;
                font-size: 0.8rem;
                font-weight: 700;
            }
            .admin-table-wrap td:last-child { display: block; border-bottom: 0; }
            .admin-table-wrap td:last-child::before { display: block; margin-bottom: 8px; }
            .btn-edit, .delete-button { min-height: 40px; }
            .delete-button { margin-top: 0; }
            .edit-dialog { padding: 20px; }
        }
        @media (max-width: 360px) {
            .content { padding: 68px 12px 16px; }
            .panel { padding: 14px; }
            .admin-table-wrap td { grid-template-columns: 1fr; gap: 4px; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" id="mobileBurger" type="button" aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false" onclick="toggleSidebar()">☰</button>
    <div class="dashboard-wrapper">
        <nav class="sidebar" id="sidebar" aria-label="Super admin navigation">
            <div class="sidebar-header">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" type="button" aria-label="Close navigation menu" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="super_admin_dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a></li>
                <li><a href="admin_management.php" class="<?= $activePage === 'admin' ? 'active' : '' ?>" <?= $activePage === 'admin' ? 'aria-current="page"' : '' ?>>Admin Management</a></li>
                <li><a href="account_archive.php" class="<?= $activePage === 'accounts' ? 'active' : '' ?>" <?= $activePage === 'accounts' ? 'aria-current="page"' : '' ?>>Account Archive</a></li>
                <li><a href="resource_archive.php" class="<?= $activePage === 'resources' ? 'active' : '' ?>" <?= $activePage === 'resources' ? 'aria-current="page"' : '' ?>>Resource Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>
        <main class="content">
