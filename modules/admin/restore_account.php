<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$dashboardUrl = '../super%20admin/account_archive.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $dashboardUrl . '?status=archive_action_failed');
    exit();
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$csrfToken = $_POST['csrf_token'] ?? '';
if (!$userId || $userId < 1 || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: ' . $dashboardUrl . '?status=archive_action_failed');
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

    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
        VALUES (:user_id, :action, 'account_archives', :ip_address)
    ");
    $logStmt->execute([
        'user_id'    => (int) $_SESSION['user_id'],
        'action'     => "Restored account {$userId} (role: {$originalRole}).",
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ]);

    $conn->commit();
    header('Location: ' . $dashboardUrl . '?status=restored');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Account restore failed: ' . $e->getMessage());
    header('Location: ' . $dashboardUrl . '?status=archive_action_failed');
    exit();
}