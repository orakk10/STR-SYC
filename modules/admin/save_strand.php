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

$strand_id = isset($_POST['strand_id']) ? (int) $_POST['strand_id'] : 0;
$strand_name = trim($_POST['strand_name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($strand_name === '') {
    header('Location: manage_strands.php?error=missing_name');
    exit();
}

try {
    $conn->beginTransaction();

    if ($strand_id > 0) {
        $stmt = $conn->prepare('UPDATE strands SET strand_name = ?, description = ? WHERE id = ?');
        $stmt->execute([$strand_name, $description, $strand_id]);
        $action = 'UPDATE_STRAND';
        $msg = 'updated';
    } else {
        $stmt = $conn->prepare('INSERT INTO strands (strand_name, description) VALUES (?, ?)');
        $stmt->execute([$strand_name, $description]);
        $strand_id = (int) $conn->lastInsertId();
        $action = 'CREATE_STRAND';
        $msg = 'created';
    }

    // Log the strand creation or update with structured JSON details
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
        VALUES (:user_id, :action, 'strands', :ip_address, :details)
    ");
    $logStmt->execute([
        'user_id'    => (int) $_SESSION['user_id'],
        'action'     => $action,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'details'    => json_encode([
            'strand_id'   => $strand_id,
            'strand_name' => $strand_name,
            'description' => $description
        ])
    ]);

    $conn->commit();
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Strand save failed: ' . $e->getMessage());
    header('Location: manage_strands.php?error=save_failed');
    exit();
}

header('Location: manage_strands.php?success=' . urlencode($msg));
exit();