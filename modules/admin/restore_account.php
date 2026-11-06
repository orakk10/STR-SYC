<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$returnToManageUsers = ($_POST['return_to'] ?? '') === 'manage_users';
$successRedirect = $returnToManageUsers
    ? 'manage_users.php?msg=account_restored'
    : 'account_archive.php?status=restored';
$errorRedirect = $returnToManageUsers
    ? 'manage_users.php?msg=account_restore_error'
    : 'account_archive.php?status=error';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $errorRedirect);
    exit();
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$csrfToken = $_POST['csrf_token'] ?? '';
if (!$userId || $userId < 1 || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: ' . $errorRedirect);
    exit();
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();

    $archiveStmt = $conn->prepare('SELECT original_role FROM account_archives WHERE user_id = ? FOR UPDATE');
    $archiveStmt->execute([$userId]);
    $originalRole = $archiveStmt->fetchColumn();
    if ($originalRole === false || !in_array($originalRole, ['admin', 'adviser', 'student', 'faculty', 'alumni'], true)) {
        throw new RuntimeException('The archived account could not be found or has an invalid role.');
    }

    $restoreStmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND role = 'archived'");
    $restoreStmt->execute([$originalRole, $userId]);
    if ($restoreStmt->rowCount() !== 1) {
        throw new RuntimeException('The account is no longer available for restoration.');
    }

    $deleteStmt = $conn->prepare('DELETE FROM account_archives WHERE user_id = ?');
    $deleteStmt->execute([$userId]);

    // Log the account restoration utilizing the structured details column
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
        VALUES (:user_id, :action, 'account_archives', :ip_address, :details)
    ");
    $logStmt->execute([
        'user_id'    => (int) $_SESSION['user_id'],
        'action'     => 'RESTORE_ACCOUNT',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'details'    => json_encode([
            'restored_user_id' => $userId,
            'original_role'    => $originalRole
        ])
    ]);

    $conn->commit();
    header('Location: ' . $successRedirect);
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Account restore failed: ' . $e->getMessage());
    header('Location: ' . $errorRedirect);
    exit();
}