<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/GraduateArchive.php';

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    respond(403, ['status' => 'error', 'message' => 'Access denied. Administrator privileges required.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['status' => 'error', 'message' => 'Only POST requests are allowed.']);
}

$data = json_decode(file_get_contents('php://input'), true);
$sectionId = is_array($data) && isset($data['section_id'])
    ? filter_var($data['section_id'], FILTER_VALIDATE_INT)
    : false;
if (!$sectionId || $sectionId < 1) {
    respond(400, ['status' => 'error', 'message' => 'A valid section_id is required.']);
}

$db = null;
try {
    $db = getDBConnection();
    $db->beginTransaction();

    $sectionStmt = $db->prepare("
        SELECT id, grade_level
        FROM sections
        WHERE id = :section_id
        FOR UPDATE
    ");
    $sectionStmt->execute(['section_id' => $sectionId]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        $db->rollBack();
        respond(404, ['status' => 'error', 'message' => 'Section was not found.']);
    }
    if ((int) $section['grade_level'] !== 12) {
        $db->rollBack();
        respond(409, ['status' => 'error', 'message' => 'Only Grade 12 students can be archived.']);
    }

    $studentsStmt = $db->prepare("
        SELECT u.id, u.lrn, u.username, u.full_name, u.section_id,
               s.strand_id, s.academic_year
        FROM users u
        INNER JOIN sections s ON s.id = u.section_id
        WHERE u.section_id = :section_id
          AND u.role = 'student'
          AND s.grade_level = 12
        ORDER BY u.id
        FOR UPDATE
    ");
    $studentsStmt->execute(['section_id' => $sectionId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $student) {
        archiveGraduateRecord($db, $student);
    }

    if (!empty($students)) {
        $studentIds = array_map('intval', array_column($students, 'id'));
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $updateStmt = $db->prepare("
            UPDATE users
            SET role = 'alumni', section_id = NULL
            WHERE role = 'student' AND id IN ($placeholders)
        ");
        $updateStmt->execute($studentIds);
    }

    $logStmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
        VALUES (:user_id, :action, 'archived_students', :ip_address)
    ");
    $logStmt->execute([
        'user_id' => (int) $_SESSION['user_id'],
        'action' => 'Archived ' . count($students)
            . ' Grade 12 students from section ' . $sectionId
            . ' as alumni using the section academic year.',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ]);

    $db->commit();
    respond(200, [
        'status' => 'success',
        'archived_count' => count($students),
        'message' => 'Archived ' . count($students) . ' Grade 12 students using their section academic year.',
    ]);
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Batch graduate archive failed: ' . $e->getMessage());
    respond(500, ['status' => 'error', 'message' => 'Unable to archive the Grade 12 section. Please try again.']);
}
