<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_strands.php?error=invalid_request');
    exit();
}

$id = isset($_POST['strand_id']) ? (int) $_POST['strand_id'] : 0;
$strand_name = trim($_POST['strand_name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($id <= 0 || $strand_name === '') {
    header('Location: manage_strands.php?error=missing_data');
    exit();
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare('UPDATE strands SET strand_name = ?, description = ? WHERE id = ?');
    $stmt->execute([$strand_name, $description, $id]);

    // Log the strand update utilizing the structured details column
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
        VALUES (:user_id, :action, 'strands', :ip_address, :details)
    ");
    $logStmt->execute([
        'user_id'    => (int) $_SESSION['user_id'],
        'action'     => 'UPDATE_STRAND',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'details'    => json_encode([
            'strand_id'   => $id,
            'strand_name' => $strand_name,
            'description' => $description
        ])
    ]);

    $conn->commit();
    header('Location: manage_strands.php?success=updated');
    exit();
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Strand update failed: ' . $e->getMessage());
    header('Location: manage_strands.php?error=update_failed');
    exit();
}