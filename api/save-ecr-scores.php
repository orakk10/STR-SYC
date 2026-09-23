<?php
session_start();

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Access Control: Only Faculty/Advisers can submit scores
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
    exit();
}

$pdo = getDBConnection();

// Receive JSON payload from ecr-grid.js
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input payload.']);
    exit();
}

$section_id = intval($data['section_id'] ?? 0);
$subject_id = intval($data['subject_id'] ?? 0);
$term       = intval($data['term'] ?? 1);
$scores     = $data['scores'] ?? []; // Array of {student_id, category, item_no, score}
$summaries  = $data['summaries'] ?? []; // Array of {student_id, initial_grade, transmuted_grade}

try {
    $pdo->beginTransaction();

    // 1. Save / Update Individual Assessment Scores
    $score_stmt = $pdo->prepare("
        INSERT INTO ecr_student_scores (student_id, subject_id, term, category, item_no, score)
        VALUES (:student_id, :subject_id, :term, :category, :item_no, :score)
        ON DUPLICATE KEY UPDATE score = VALUES(score), updated_at = NOW()
    ");

    foreach ($scores as $item) {
        $score_stmt->execute([
            'student_id' => intval($item['student_id']),
            'subject_id' => $subject_id,
            'term'       => $term,
            'category'   => $item['category'], // 'ww', 'pt', or 'qa'
            'item_no'    => intval($item['item_no']),
            'score'      => floatval($item['score'])
        ]);
    }

    // 2. Save / Update Term Summary Grades (Initial & Transmuted)
    $summary_stmt = $pdo->prepare("
        INSERT INTO ecr_term_summaries (student_id, subject_id, term, initial_grade, transmuted_grade)
        VALUES (:student_id, :subject_id, :term, :initial_grade, :transmuted_grade)
        ON DUPLICATE KEY UPDATE 
            initial_grade = VALUES(initial_grade), 
            transmuted_grade = VALUES(transmuted_grade),
            updated_at = NOW()
    ");

    foreach ($summaries as $summary) {
        $summary_stmt->execute([
            'student_id'       => intval($summary['student_id']),
            'subject_id'       => $subject_id,
            'term'             => $term,
            'initial_grade'    => floatval($summary['initial_grade']),
            'transmuted_grade' => floatval($summary['transmuted_grade'])
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Term ' . $term . ' scores saved successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("ECR Score Persistence Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save scores to database.']);
}