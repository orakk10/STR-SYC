<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: manage_users.php?msg=error');
    exit();
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$csrfToken = $_POST['csrf_token'] ?? '';
if (!$id || $id < 1 || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: manage_users.php?msg=error');
    exit();
}

if ((int) $_SESSION['user_id'] === $id) {
    header('Location: manage_users.php?msg=error_self_archive');
    exit();
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();

    $userStmt = $conn->prepare("SELECT role FROM users WHERE id = ? FOR UPDATE");
    $userStmt->execute([$id]);
    $role = $userStmt->fetchColumn();
    if ($role === false || in_array($role, ['super_admin', 'archived'], true)) {
        throw new RuntimeException('The selected account cannot be archived.');
    }

    $archiveStmt = $conn->prepare("
        INSERT INTO account_archives (user_id, original_role, archived_by)
        VALUES (:user_id, :original_role, :archived_by)
    ");
    $archiveStmt->execute([
        'user_id' => $id,
        'original_role' => $role,
        'archived_by' => (int) $_SESSION['user_id'],
    ]);

    $updateStmt = $conn->prepare("UPDATE users SET role = 'archived' WHERE id = ?");
    $updateStmt->execute([$id]);

    // Log the account archiving action utilizing the structured details column
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
        VALUES (:user_id, :action, 'account_archives', :ip_address, :details)
    ");
    $logStmt->execute([
        'user_id'    => (int) $_SESSION['user_id'],
        'action'     => 'ARCHIVE_USER',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'details'    => json_encode([
            'archived_user_id' => $id,
            'original_role'    => $role
        ])
    ]);

    $conn->commit();
    header('Location: manage_users.php?msg=account_archived');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Account archive failed: ' . $e->getMessage());
    header('Location: manage_users.php?msg=error');
    exit();
}