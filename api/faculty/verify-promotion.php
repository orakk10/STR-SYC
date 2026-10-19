<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/PromotionVerification.php';

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

if (
    !isset($_SESSION['user_id'], $_SESSION['role'])
    || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'], true)
) {
    respond(403, ['status' => 'error', 'message' => 'Unauthorized access.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['status' => 'error', 'message' => 'Only POST requests are allowed.']);
}

$data = json_decode(file_get_contents('php://input'), true);
if (
    !is_array($data)
    || !isset($data['assignment_id'], $data['student_id'], $data['verified'], $data['csrf_token'])
    || !is_bool($data['verified'])
    || !is_string($data['csrf_token'])
) {
    respond(400, ['status' => 'error', 'message' => 'Invalid or missing verification data.']);
}

if (
    empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $data['csrf_token'])
) {
    respond(403, ['status' => 'error', 'message' => 'Your session has expired. Refresh the page and try again.']);
}

$assignmentId = filter_var($data['assignment_id'], FILTER_VALIDATE_INT);
$studentId = filter_var($data['student_id'], FILTER_VALIDATE_INT);
if (!$assignmentId || $assignmentId < 1 || !$studentId || $studentId < 1) {
    respond(400, ['status' => 'error', 'message' => 'Invalid assignment or student.']);
}

try {
    $pdo = getDBConnection();
    ensurePromotionVerificationTable($pdo);

    $assignmentStmt = $pdo->prepare("
        SELECT faculty_id, section_id
        FROM subject_assignments
        WHERE id = :assignment_id
    ");
    $assignmentStmt->execute(['assignment_id' => $assignmentId]);
    $assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$assignment
        || (
            $_SESSION['role'] === 'faculty'
            && (int)$assignment['faculty_id'] !== (int)$_SESSION['user_id']
        )
    ) {
        respond(404, ['status' => 'error', 'message' => 'Subject assignment was not found.']);
    }

    $studentStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = :student_id
          AND section_id = :section_id
          AND role = 'student'
    ");
    $studentStmt->execute([
        'student_id' => $studentId,
        'section_id' => (int)$assignment['section_id'],
    ]);
    if (!$studentStmt->fetchColumn()) {
        respond(404, ['status' => 'error', 'message' => 'Student was not found in this section.']);
    }

    if ($data['verified']) {
        $verificationStmt = $pdo->prepare("
            INSERT INTO ecr_promotion_verifications_by_assignment (student_id, assignment_id, verified_by)
            VALUES (:student_id, :assignment_id, :verified_by)
            ON DUPLICATE KEY UPDATE
                verified_by = VALUES(verified_by),
                verified_at = CURRENT_TIMESTAMP
        ");
        $verificationStmt->execute([
            'student_id' => $studentId,
            'assignment_id' => $assignmentId,
            'verified_by' => (int)$_SESSION['user_id'],
        ]);
    } else {
        $verificationStmt = $pdo->prepare("
            DELETE FROM ecr_promotion_verifications_by_assignment
            WHERE student_id = :student_id AND assignment_id = :assignment_id
        ");
        $verificationStmt->execute([
            'student_id' => $studentId,
            'assignment_id' => $assignmentId,
        ]);
    }

    respond(200, ['status' => 'success']);
} catch (PDOException $e) {
    error_log('Promotion verification failed: ' . $e->getMessage());
    respond(500, ['status' => 'error', 'message' => 'Unable to save promotion verification. Please try again.']);
}
