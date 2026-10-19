<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/EcrDraftStore.php';
require_once __DIR__ . '/../../core/EcrTermLock.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (
    !is_array($data)
    || !isset($data['assignment_id'], $data['term'], $data['scores'])
    || !is_array($data['scores'])
) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing draft payload.']);
    exit();
}

$assignmentId = filter_var($data['assignment_id'], FILTER_VALIDATE_INT);
$termMatch = [];
if (!$assignmentId || $assignmentId < 1 || !preg_match('/^Term ([1-3])$/', (string)$data['term'], $termMatch)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid assignment or term.']);
    exit();
}
$termName = 'Term ' . $termMatch[1];

try {
    $db = getDBConnection();
    ensureEcrDraftTable($db);
    $db->beginTransaction();

    $assignmentStmt = $db->prepare("
        SELECT id, faculty_id, section_id
        FROM subject_assignments
        WHERE id = :assignment_id
        FOR UPDATE
    ");
    $assignmentStmt->execute(['assignment_id' => $assignmentId]);
    $assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$assignment || (
        $_SESSION['role'] !== 'admin'
        && (int)$assignment['faculty_id'] !== (int)$_SESSION['user_id']
    )) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Subject assignment was not found.']);
        exit();
    }

    if (isEcrTermLocked($db, $assignmentId, $termName)) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => "{$termName} has already been saved and is read-only."]);
        exit();
    }

    $componentsStmt = $db->prepare("
        SELECT id
        FROM ecr_assessment_components
        WHERE assignment_id = :assignment_id AND term = :term
    ");
    $componentsStmt->execute(['assignment_id' => $assignmentId, 'term' => $termName]);
    $componentIds = array_fill_keys(array_map('intval', $componentsStmt->fetchAll(PDO::FETCH_COLUMN)), true);

    $studentsStmt = $db->prepare("
        SELECT id
        FROM users
        WHERE section_id = :section_id AND role = 'student'
    ");
    $studentsStmt->execute(['section_id' => (int)$assignment['section_id']]);
    $studentIds = array_fill_keys(array_map('intval', $studentsStmt->fetchAll(PDO::FETCH_COLUMN)), true);

    $scores = [];
    foreach ($data['scores'] as $item) {
        if (!is_array($item) || !isset($item['component_id'], $item['student_id'], $item['score'])) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Each draft score must include a component, student, and score.']);
            exit();
        }

        $componentId = filter_var($item['component_id'], FILTER_VALIDATE_INT);
        $studentId = filter_var($item['student_id'], FILTER_VALIDATE_INT);
        if (
            !$componentId || !$studentId
            || !isset($componentIds[$componentId])
            || !isset($studentIds[$studentId])
            || !is_numeric($item['score'])
            || !is_finite((float)$item['score'])
            || (float)$item['score'] < 0
            || (float)$item['score'] > 100
        ) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'A draft score contains an invalid component, student, or value.']);
            exit();
        }

        $scores[] = [
            'component_id' => $componentId,
            'student_id' => $studentId,
            'score' => (string)$item['score']
        ];
    }

    $draftJson = json_encode(['scores' => $scores]);
    if ($draftJson === false) {
        $db->rollBack();
        throw new RuntimeException('Unable to encode the ECR draft.');
    }

    $saveStmt = $db->prepare("
        INSERT INTO ecr_score_drafts (user_id, assignment_id, term, draft_data)
        VALUES (:user_id, :assignment_id, :term, :draft_data)
        ON DUPLICATE KEY UPDATE draft_data = VALUES(draft_data), updated_at = CURRENT_TIMESTAMP
    ");
    $saveStmt->execute([
        'user_id' => (int)$_SESSION['user_id'],
        'assignment_id' => $assignmentId,
        'term' => $termName,
        'draft_data' => $draftJson
    ]);
    $db->commit();

    echo json_encode(['status' => 'success', 'message' => 'Draft saved successfully.']);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('ECR draft save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save the draft. Please try again.']);
} catch (RuntimeException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('ECR draft save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save the draft. Please try again.']);
}
