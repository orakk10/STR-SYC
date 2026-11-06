<?php
// api/faculty/save-ecr-input.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/EcrTermLock.php';
require_once __DIR__ . '/../../core/EcrSubjectTerm.php';
require_once __DIR__ . '/../../core/ActivityLogger.php'; // <-- Include ActivityLogger

// Check authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (
    !$data
    || !isset($data['assignment_id'], $data['components'])
    || !is_array($data['components'])
    || $data['components'] === []
) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing components payload.']);
    exit();
}

$firstComponent = $data['components'][0] ?? null;
$termName = is_array($firstComponent) ? ($firstComponent['term'] ?? '') : '';
if (!in_array($termName, ['Term 1', 'Term 2', 'Term 3'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid term.']);
    exit();
}
$components = [];
foreach ($data['components'] as $component) {
    if (!is_array($component)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'A component has an invalid payload.']);
        exit();
    }

    $taskNumber = filter_var($component['task_number'] ?? null, FILTER_VALIDATE_INT);
    $highestPossibleScore = $component['highest_possible_score'] ?? null;
    if (
        ($component['term'] ?? null) !== $termName
        || !in_array($component['category'] ?? null, ['WW', 'PT', 'QA'], true)
        || $taskNumber === false || $taskNumber < 1
        || !is_numeric($highestPossibleScore)
        || !is_finite((float)$highestPossibleScore)
        || (float)$highestPossibleScore < 1
        || (float)$highestPossibleScore > 100
    ) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'A component has an invalid term, category, task number, or HPS value. HPS must be between 1 and 100.']);
        exit();
    }

    $components[] = [
        'term' => $termName,
        'category' => $component['category'],
        'task_number' => $taskNumber,
        'highest_possible_score' => (float)$highestPossibleScore
    ];
}

try {
    $db = getDBConnection();
    $db->beginTransaction();

    $stmtAssignment = $db->prepare("
        SELECT id FROM subject_assignments
        WHERE id = :assignment_id
        FOR UPDATE
    ");
    $stmtAssignment->execute(['assignment_id' => (int)$data['assignment_id']]);
    if (!$stmtAssignment->fetchColumn()) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Subject assignment was not found.']);
        exit();
    }

    // Check if the term summary is locked
    $stmtLock = $db->prepare("
        SELECT summaries.is_locked
        FROM ecr_term_summaries summaries
        JOIN subject_assignments assignments ON assignments.id = summaries.assignment_id
        JOIN users students
            ON students.id = summaries.student_id
            AND students.section_id = assignments.section_id
            AND students.role = 'student'
        WHERE summaries.assignment_id = :assignment_id
            AND summaries.is_locked = 1
        LIMIT 1
    ");
    $stmtLock->execute(['assignment_id' => (int)$data['assignment_id']]);
    $isLocked = $stmtLock->fetchColumn();

    if ($isLocked == 1 && $_SESSION['role'] !== 'admin') {
        $db->rollBack();
        http_response_code(423);
        echo json_encode(['status' => 'error', 'message' => 'Grade modifications are locked for this subject assignment.']);
        exit();
    }

    $stmtAssignmentLock = $db->prepare("
        SELECT id FROM subject_assignments
        WHERE id = :assignment_id
        FOR UPDATE
    ");
    $stmtAssignmentLock->execute(['assignment_id' => (int)$data['assignment_id']]);
    if (!$stmtAssignmentLock->fetchColumn()) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Subject assignment was not found.']);
        exit();
    }

    if (!isEcrAssignmentAvailableForTerm($db, (int)$data['assignment_id'], $termName)) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => "{$termName} is not configured for this subject in the curriculum and its assessment setup is read-only."
        ]);
        exit();
    }

    if (isEcrTermLocked($db, (int)$data['assignment_id'], $termName)) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => "{$termName} has already been saved and its setup is read-only."
        ]);
        exit();
    }

    $stmtFindComponent = $db->prepare("
        SELECT id
        FROM ecr_assessment_components
        WHERE assignment_id = :assignment_id
            AND term = :term
            AND category = :category
            AND task_number = :task_number
        LIMIT 1
    ");
    $stmtUpdateComponent = $db->prepare("
        UPDATE ecr_assessment_components
        SET highest_possible_score = :hps
        WHERE assignment_id = :assignment_id
            AND term = :term
            AND category = :category
            AND task_number = :task_number
    ");
    $stmtInsertComponent = $db->prepare("
        INSERT INTO ecr_assessment_components
            (assignment_id, term, category, task_number, highest_possible_score)
        VALUES (:assignment_id, :term, :category, :task_number, :hps)
    ");

    $count = 0;
    foreach ($components as $comp) {
        $params = [
            'assignment_id' => (int)$data['assignment_id'],
            'term'          => $comp['term'],
            'category'      => $comp['category'],
            'task_number'   => $comp['task_number']
        ];
        $stmtFindComponent->execute($params);
        if ($stmtFindComponent->fetchColumn()) {
            $stmtUpdateComponent->execute($params + ['hps' => $comp['highest_possible_score']]);
        } else {
            $stmtInsertComponent->execute($params + ['hps' => $comp['highest_possible_score']]);
        }
        $count++;
    }

    $db->commit();

    // RECORD ACTIVITY LOG WITH JSON DETAILS
    ActivityLogger::log(
        $db,
        (int)$_SESSION['user_id'],
        'SAVE_ECR_INPUT',
        'ecr_assessment_components',
        [
            'assignment_id'   => (int)$data['assignment_id'],
            'term'            => $termName,
            'components_saved'=> $count,
            'ip'              => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    );

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully saved {$count} assessment component configurations."
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}