<?php
// api/admin/batch-archive-graduates.php
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

if (!isset($data['section_id'], $data['batch_year'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing section_id or batch_year parameter.']);
    exit();
}

$sectionId = (int)$data['section_id'];
$batchYear = (int)$data['batch_year'];

$db = null;

try {
    $db = getDBConnection();
    $db->beginTransaction();

    // 1. Copy active students from section into archived_students table
    $stmtArchive = $db->prepare("
        INSERT INTO archived_students (original_id, lrn, username, full_name, strand_id, section_id, batch_year, archived_at)
        SELECT 
            u.id, u.lrn, u.username, u.full_name, s.strand_id, u.section_id, :batch_year, NOW()
        FROM users u
        INNER JOIN sections s ON u.section_id = s.id
        WHERE u.section_id = :section_id AND u.role = 'student'
    ");
    $stmtArchive->execute([
        'batch_year' => $batchYear,
        'section_id' => $sectionId
    ]);

    $archivedCount = $stmtArchive->rowCount();

    // 2. Update user roles to 'alumni' and decouple active section_id
    $stmtUpdateUsers = $db->prepare("
        UPDATE users 
        SET role = 'alumni', section_id = NULL 
        WHERE section_id = :section_id AND role = 'student'
    ");
    $stmtUpdateUsers->execute(['section_id' => $sectionId]);

    // 3. Log Activity
    $stmtLog = $db->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
        VALUES (:uid, :action, 'archived_students', :ip)
    ");
    $stmtLog->execute([
        'uid'    => $_SESSION['user_id'],
        'action' => "Archived {$archivedCount} graduating students from section_id: {$sectionId} (Batch {$batchYear}).",
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    $db->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => "Successfully archived {$archivedCount} students for batch year {$batchYear}."
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}