<?php
// api/faculty/save-ecr-scores.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/DepEdTransmutation.php';
require_once __DIR__ . '/../../core/EcrDraftStore.php';
require_once __DIR__ . '/../../core/EcrTermLock.php';
require_once __DIR__ . '/../../core/EcrSubjectTerm.php';

// Check authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['assignment_id'], $data['term'], $data['scores']) || !is_array($data['scores'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing payload structure.']);
    exit();
}

$assignmentId = (int)$data['assignment_id'];
$termMatch = [];
if (!preg_match('/^Term ([1-3])$/', (string)$data['term'], $termMatch)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid term.']);
    exit();
}
$termNumber = (int)$termMatch[1];
$termName = "Term {$termNumber}";
$termColumn = "term{$termNumber}_transmuted";

try {
    $db = getDBConnection();
    ensureEcrDraftTable($db);
    $db->beginTransaction();

    // Check if grades are locked
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
    $stmtLock->execute(['assignment_id' => $assignmentId]);
    $isLocked = $stmtLock->fetchColumn();

    if ($isLocked == 1 && $_SESSION['role'] !== 'admin') {
        $db->rollBack();
        http_response_code(423);
        echo json_encode(['status' => 'error', 'message' => 'Grades are locked and cannot be updated.']);
        exit();
    }

    // Serialize summary writes for this assignment, including first-time inserts.
    $stmtAssignmentLock = $db->prepare("
        SELECT id FROM subject_assignments
        WHERE id = :assignment_id
        FOR UPDATE
    ");
    $stmtAssignmentLock->execute(['assignment_id' => $assignmentId]);
    if (!$stmtAssignmentLock->fetchColumn()) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Subject assignment was not found.']);
        exit();
    }

    if (!isEcrAssignmentAvailableForTerm($db, $assignmentId, $termName)) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => "{$termName} is not configured for this subject in the curriculum and cannot accept grades."
        ]);
        exit();
    }

    if (isEcrTermLocked($db, $assignmentId, $termName)) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => "{$termName} has already been saved and is now read-only."
        ]);
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
        $stmtExistingSummaries = $db->prepare("
            SELECT id, term1_transmuted, term2_transmuted, term3_transmuted
            FROM ecr_term_summaries
            WHERE student_id = :student_id AND assignment_id = :assignment_id
            ORDER BY id ASC
            FOR UPDATE
        ");
        $stmtUpdateSummary = $db->prepare("
            UPDATE ecr_term_summaries
            SET term1_transmuted = :t1,
                term2_transmuted = :t2,
                term3_transmuted = :t3,
                final_grade = :final_grade,
                letter_grade = :letter_grade,
                remarks = :remarks,
                submitted_at = NOW()
            WHERE id = :id
        ");
        $stmtDeleteDuplicateSummaries = $db->prepare("
            DELETE FROM ecr_term_summaries
            WHERE student_id = :student_id
              AND assignment_id = :assignment_id
              AND id <> :keep_id
        ");
        $stmtInsertSummary = $db->prepare("
            INSERT INTO ecr_term_summaries (
                student_id, assignment_id, term1_transmuted, term2_transmuted, term3_transmuted,
                final_grade, letter_grade, remarks, is_locked, submitted_at
            ) VALUES (
                :student_id, :assignment_id, :t1, :t2, :t3,
                :final_grade, :letter_grade, :remarks, 0, NOW()
            )
        ");

        foreach ($data['summaries'] as $summary) {
            if (!isset($summary['student_id'])) continue;

            $studentId = (int)$summary['student_id'];

            $stmtExistingSummaries->execute([
                'student_id' => $studentId,
                'assignment_id' => $assignmentId
            ]);
            $existingSummaries = $stmtExistingSummaries->fetchAll(PDO::FETCH_ASSOC);

            $terms = [
                'term1_transmuted' => null,
                'term2_transmuted' => null,
                'term3_transmuted' => null
            ];
            foreach ($existingSummaries as $existingSummary) {
                foreach ($terms as $column => $value) {
                    if ($terms[$column] === null && $existingSummary[$column] !== null) {
                        $terms[$column] = (float)$existingSummary[$column];
                    }
                }
            }

            foreach ([1, 2, 3] as $termNumber) {
                $initialKey = "term{$termNumber}_initial";
                $termColumn = "term{$termNumber}_transmuted";
                if (isset($summary[$initialKey])) {
                    $terms[$termColumn] = DepEdTransmutation::transmute((float)$summary[$initialKey]);
                }
            }

            $t1 = $terms['term1_transmuted'];
            $t2 = $terms['term2_transmuted'];
            $t3 = $terms['term3_transmuted'];

            $validTerms = array_filter([$t1, $t2, $t3], fn($v) => $v !== null);
            
            $finalGrade  = null;
            $letterGrade = null;
            $remarks     = null;

            if (count($validTerms) > 0) {
                $finalGrade  = round(array_sum($validTerms) / count($validTerms), 2);
                $letterGrade = DepEdTransmutation::getLetterGrade($finalGrade);
                $remarks     = DepEdTransmutation::getRemark($finalGrade);
            }

            $summaryValues = [
                't1' => $t1,
                't2' => $t2,
                't3' => $t3,
                'final_grade' => $finalGrade,
                'letter_grade' => $letterGrade,
                'remarks' => $remarks
            ];

            if ($existingSummaries) {
                $keepId = (int)$existingSummaries[0]['id'];
                $stmtUpdateSummary->execute($summaryValues + ['id' => $keepId]);
                $stmtDeleteDuplicateSummaries->execute([
                    'student_id' => $studentId,
                    'assignment_id' => $assignmentId,
                    'keep_id' => $keepId
                ]);
            } else {
                $stmtInsertSummary->execute($summaryValues + [
                    'student_id' => $studentId,
                    'assignment_id' => $assignmentId
                ]);
            }
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

    $stmtDeleteDraft = $db->prepare("
        DELETE FROM ecr_score_drafts
        WHERE user_id = :user_id AND assignment_id = :assignment_id AND term = :term
    ");
    $stmtDeleteDraft->execute([
        'user_id' => (int)$_SESSION['user_id'],
        'assignment_id' => $assignmentId,
        'term' => $termName
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