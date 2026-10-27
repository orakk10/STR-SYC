<?php
session_start();

// 1. Path Resolution & Database Connection
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/EcrTermLock.php';

// Access Control Check
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../manifest/login.php");
    exit();
}

$pdo = getDBConnection();

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$term          = trim($_POST['term'] ?? 'Term 1');
$new_ww_count  = filter_var($_POST['ww_count'] ?? null, FILTER_VALIDATE_INT);
$new_pt_count  = filter_var($_POST['pt_count'] ?? null, FILTER_VALIDATE_INT);
$new_qa_count  = filter_var($_POST['qa_count'] ?? null, FILTER_VALIDATE_INT);
$term_match    = [];

if ($assignment_id <= 0 || !preg_match('/^Term ([1-3])$/', $term, $term_match)) {
    header("Location: ecr-inputdata.php");
    exit();
}
$term_number = (int)$term_match[1];

if (
    $new_ww_count === false || $new_ww_count < 1 || $new_ww_count > 10
    || $new_pt_count === false || $new_pt_count < 1 || $new_pt_count > 10
    || $new_qa_count === false || $new_qa_count < 1 || $new_qa_count > 3
) {
    header("Location: ecr-inputdata.php?assignment_id={$assignment_id}&term={$term_number}&error=invalid_count");
    exit();
}

try {
    $pdo->beginTransaction();

    $assignment_lock_stmt = $pdo->prepare("
        SELECT id FROM subject_assignments
        WHERE id = :assignment_id AND faculty_id = :faculty_id
        FOR UPDATE
    ");
    $assignment_lock_stmt->execute([
        'assignment_id' => $assignment_id,
        'faculty_id' => (int)$_SESSION['user_id']
    ]);
    if (!$assignment_lock_stmt->fetchColumn()) {
        $pdo->rollBack();
        header("Location: ecr-inputdata.php?error=assignment_invalid");
        exit();
    }

    if (isEcrTermLocked($pdo, $assignment_id, $term)) {
        $pdo->rollBack();
        header("Location: ecr-inputdata.php?assignment_id={$assignment_id}&term={$term_number}&error=term_locked");
        exit();
    }

    $categories = [
        'WW' => $new_ww_count,
        'PT' => $new_pt_count,
        'QA' => $new_qa_count
    ];

    foreach ($categories as $category => $target_count) {
        $stmt = $pdo->prepare("
            SELECT task_number FROM ecr_assessment_components 
            WHERE assignment_id = :assignment_id AND term = :term AND category = :category
        ");
        $stmt->execute([
            'assignment_id' => $assignment_id,
            'term' => $term,
            'category' => $category
        ]);
        $existing_tasks = array_fill_keys(
            array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
            true
        );

        $insert_stmt = $pdo->prepare("
            INSERT INTO ecr_assessment_components (assignment_id, term, category, task_number, highest_possible_score)
            VALUES (:assignment_id, :term, :category, :task_number, 100.00)
        ");
        for ($task_number = 1; $task_number <= $target_count; $task_number++) {
            if (!isset($existing_tasks[$task_number])) {
                $insert_stmt->execute([
                    'assignment_id' => $assignment_id,
                    'term' => $term,
                    'category' => $category,
                    'task_number' => $task_number
                ]);
            }
        }

        $delete_stmt = $pdo->prepare("
            DELETE FROM ecr_assessment_components
            WHERE assignment_id = :assignment_id AND term = :term AND category = :category AND task_number > :target_count
        ");
        $delete_stmt->execute([
            'assignment_id' => $assignment_id,
            'term' => $term,
            'category' => $category,
            'target_count' => $target_count
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log(sprintf(
        'Assessment setup save failed for assignment %d, %s: %s',
        $assignment_id,
        $term,
        $e->getMessage()
    ));
    header("Location: ecr-inputdata.php?assignment_id={$assignment_id}&term={$term_number}&error=save_failed");
    exit();
}

header("Location: ecr-inputdata.php?assignment_id={$assignment_id}&term={$term_number}&saved=1");
exit();