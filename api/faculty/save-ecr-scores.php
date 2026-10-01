<?php
// api/faculty/save-ecr-scores.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/DepEdTransmutation.php';

// Check authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['assignment_id'], $data['scores']) || !is_array($data['scores'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing payload structure.']);
    exit();
}

$assignmentId = (int)$data['assignment_id'];

try {
    $db = getDBConnection();
    $db->beginTransaction();

    // Check if grades are locked
    $stmtLock = $db->prepare("
        SELECT is_locked FROM ecr_term_summaries 
        WHERE assignment_id = :assignment_id LIMIT 1
    ");
    $stmtLock->execute(['assignment_id' => $assignmentId]);
    $isLocked = $stmtLock->fetchColumn();

    if ($isLocked == 1 && $_SESSION['role'] !== 'admin') {
        http_response_code(423);
        echo json_encode(['status' => 'error', 'message' => 'Grades are locked and cannot be updated.']);
        exit();
    }

    // 1. Batch Upsert Raw Scores
    $stmtScore = $db->prepare("
        INSERT INTO ecr_student_scores (component_id, student_id, score)
        VALUES (:component_id, :student_id, :score)
        ON DUPLICATE KEY UPDATE score = VALUES(score)
    ");

    $savedScoresCount = 0;
    foreach ($data['scores'] as $item) {
        if (!isset($item['component_id'], $item['student_id'], $item['score'])) {
            continue;
        }

        $stmtScore->execute([
            'component_id' => (int)$item['component_id'],
            'student_id'   => (int)$item['student_id'],
            'score'        => (float)$item['score']
        ]);
        $savedScoresCount++;
    }

    // 2. Process Term Summaries (If initial grades are included in the payload)
    if (isset($data['summaries']) && is_array($data['summaries'])) {
        $stmtSummary = $db->prepare("
            INSERT INTO ecr_term_summaries (
                student_id, assignment_id, term1_transmuted, term2_transmuted, term3_transmuted, 
                final_grade, letter_grade, remarks, is_locked, submitted_at
            ) VALUES (
                :student_id, :assignment_id, :t1, :t2, :t3, 
                :final_grade, :letter_grade, :remarks, 0, NOW()
            )
            ON DUPLICATE KEY UPDATE
                term1_transmuted = VALUES(term1_transmuted),
                term2_transmuted = VALUES(term2_transmuted),
                term3_transmuted = VALUES(term3_transmuted),
                final_grade      = VALUES(final_grade),
                letter_grade     = VALUES(letter_grade),
                remarks          = VALUES(remarks),
                submitted_at     = NOW()
        ");

        foreach ($data['summaries'] as $summary) {
            if (!isset($summary['student_id'])) continue;

            $studentId = (int)$summary['student_id'];
            
            $t1Initial = isset($summary['term1_initial']) ? (float)$summary['term1_initial'] : null;
            $t2Initial = isset($summary['term2_initial']) ? (float)$summary['term2_initial'] : null;
            $t3Initial = isset($summary['term3_initial']) ? (float)$summary['term3_initial'] : null;

            $t1 = ($t1Initial !== null) ? DepEdTransmutation::transmute($t1Initial) : null;
            $t2 = ($t2Initial !== null) ? DepEdTransmutation::transmute($t2Initial) : null;
            $t3 = ($t3Initial !== null) ? DepEdTransmutation::transmute($t3Initial) : null;

            $validTerms = array_filter([$t1, $t2, $t3], fn($v) => $v !== null);
            
            $finalGrade  = null;
            $letterGrade = null;
            $remarks     = null;

            if (count($validTerms) > 0) {
                $finalGrade  = round(array_sum($validTerms) / count($validTerms), 2);
                $letterGrade = DepEdTransmutation::getLetterGrade($finalGrade);
                $remarks     = DepEdTransmutation::getRemark($finalGrade);
            }

            $stmtSummary->execute([
                'student_id'    => $studentId,
                'assignment_id' => $assignmentId,
                't1'            => $t1,
                't2'            => $t2,
                't3'            => $t3,
                'final_grade'   => $finalGrade,
                'letter_grade'  => $letterGrade,
                'remarks'       => $remarks
            ]);
        }
    }

    // 3. Log Activity
    $stmtLog = $db->prepare("
        INSERT INTO activity_logs (user_id, action, ip_address)
        VALUES (:uid, :action, :ip)
    ");
    $stmtLog->execute([
        'uid'    => $_SESSION['user_id'],
        'action' => "Saved scores and summaries for assignment_id: {$assignmentId}",
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully saved {$savedScoresCount} student score entries and updated term summaries."
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}