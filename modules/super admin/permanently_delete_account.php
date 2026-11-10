<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dashboardUrl = 'account_archive.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

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

    $archiveStatement = $conn->prepare(
        "SELECT aa.original_role, u.role
         FROM account_archives aa
         INNER JOIN users u ON u.id = aa.user_id
         WHERE aa.user_id = :user_id
         FOR UPDATE"
    );
    $archiveStatement->execute(['user_id' => $userId]);
    $archivedAccount = $archiveStatement->fetch();
    $validRoles = ['admin', 'adviser', 'student', 'faculty', 'alumni'];
    if (
        !$archivedAccount ||
        $archivedAccount['role'] !== 'archived' ||
        !in_array($archivedAccount['original_role'], $validRoles, true)
    ) {
        throw new RuntimeException('The account is not available for permanent deletion.');
    }

    $logStatement = $conn->prepare(
        "INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
         VALUES (:user_id, :action, 'account_archives', :ip_address)"
    );
    $logStatement->execute([
        'user_id' => (int) $_SESSION['user_id'],
        'action' => "Permanently deleted archived account {$userId}",
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $deleteStatement = $conn->prepare("DELETE FROM users WHERE id = :user_id AND role = 'archived'");
    $deleteStatement->execute(['user_id' => $userId]);
    if ($deleteStatement->rowCount() !== 1) {
        throw new RuntimeException('The archived account could not be deleted.');
    }

    $conn->commit();
    header('Location: ' . $dashboardUrl . '?status=purged');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Permanent account deletion failed: ' . $e->getMessage());
    header('Location: ' . $dashboardUrl . '?status=archive_action_failed');
    exit();
}
