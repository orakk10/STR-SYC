<?php
// api/faculty/save-ecr-input.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';

// Check authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['assignment_id'], $data['components']) || !is_array($data['components'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing components payload.']);
    exit();
}

try {
    $db = getDBConnection();
    $db->beginTransaction();

    // Check if the term summary is locked
    $stmtLock = $db->prepare("
        SELECT is_locked FROM ecr_term_summaries 
        WHERE assignment_id = :assignment_id LIMIT 1
    ");
    $stmtLock->execute(['assignment_id' => (int)$data['assignment_id']]);
    $isLocked = $stmtLock->fetchColumn();

    if ($isLocked == 1 && $_SESSION['role'] !== 'admin') {
        http_response_code(423);
        echo json_encode(['status' => 'error', 'message' => 'Grade modifications are locked for this subject assignment.']);
        exit();
    }

    $stmtUpsert = $db->prepare("
        INSERT INTO ecr_assessment_components 
            (assignment_id, term, category, task_number, highest_possible_score)
        VALUES 
            (:assignment_id, :term, :category, :task_number, :hps)
        ON DUPLICATE KEY UPDATE 
            highest_possible_score = VALUES(highest_possible_score)
    ");

    $count = 0;
    foreach ($data['components'] as $comp) {
        if (!isset($comp['term'], $comp['category'], $comp['task_number'], $comp['highest_possible_score'])) {
            continue;
        }

        $stmtUpsert->execute([
            'assignment_id' => (int)$data['assignment_id'],
            'term'          => $comp['term'],
            'category'      => $comp['category'],
            'task_number'   => (int)$comp['task_number'],
            'hps'           => (float)$comp['highest_possible_score']
        ]);
        $count++;
    }

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully saved {$count} assessment component configurations."
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}