<?php
// api/admin/toggle-term-lock.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';

// Authorization check: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Administrator privileges required.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['assignment_id'], $data['is_locked'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing assignment_id or is_locked parameter.']);
    exit();
}

$assignmentId = (int)$data['assignment_id'];
$isLocked     = (int)$data['is_locked'] ? 1 : 0;

try {
    $db = getDBConnection();

    $stmt = $db->prepare("
        UPDATE ecr_term_summaries 
        SET is_locked = :is_locked 
        WHERE assignment_id = :assignment_id
    ");
    $stmt->execute([
        'is_locked'     => $isLocked,
        'assignment_id' => $assignmentId
    ]);

    $statusText = $isLocked ? 'Locked' : 'Unlocked';

    // Log Activity
    $stmtLog = $db->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
        VALUES (:uid, :action, 'ecr_term_summaries', :ip)
    ");
    $stmtLog->execute([
        'uid'    => $_SESSION['user_id'],
        'action' => "{$statusText} term summary editing for assignment_id: {$assignmentId}",
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    echo json_encode([
        'status'  => 'success',
        'message' => "Subject assignment grade entries successfully {$statusText}."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}