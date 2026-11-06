<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    try {
        $conn->beginTransaction();

        // Fetch strand details prior to deletion for logging metadata
        $fetchStrand = $conn->prepare('SELECT * FROM strands WHERE id = ?');
        $fetchStrand->execute([$id]);
        $deletedStrand = $fetchStrand->fetch(PDO::FETCH_ASSOC);

        $conn->prepare('DELETE FROM strands WHERE id = ?')->execute([$id]);

        if ($deletedStrand) {
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
                VALUES (:user_id, :action, 'strands', :ip_address, :details)
            ");
            $logStmt->execute([
                'user_id'    => (int) $_SESSION['user_id'],
                'action'     => 'DELETE_STRAND',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'details'    => json_encode([
                    'strand_id'   => $id,
                    'strand_name' => $deletedStrand['strand_name'],
                    'description' => $deletedStrand['description']
                ])
            ]);
        }

        $conn->commit();
        header('Location: manage_strands.php?success=deleted');
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Strand deletion failed: ' . $e->getMessage());
        header('Location: manage_strands.php?error=delete_failed');
        exit();
    }
} else {
    header('Location: manage_strands.php?error=invalid_id');
    exit();
}