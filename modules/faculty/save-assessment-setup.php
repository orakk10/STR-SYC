<?php
session_start();

// 1. Path Resolution & Database Connection
require_once __DIR__ . '/../../config/database.php';

// Access Control Check
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../manifest/login.php");
    exit();
}

$pdo = getDBConnection();

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$term          = trim($_POST['term'] ?? 'Term 1');
$new_ww_count  = (int)($_POST['ww_count'] ?? 8);
$new_pt_count  = (int)($_POST['pt_count'] ?? 6);
$new_qa_count  = (int)($_POST['qa_count'] ?? 1);
$term_match    = [];

if ($assignment_id <= 0 || !preg_match('/^Term ([1-3])$/', $term, $term_match)) {
    header("Location: ecr-inputdata.php");
    exit();
}
$term_number = (int)$term_match[1];
$term_column = "term{$term_number}_transmuted";

try {
    $pdo->beginTransaction();

    $assignment_lock_stmt = $pdo->prepare("
        SELECT id FROM subject_assignments WHERE id = :assignment_id FOR UPDATE
    ");
    $assignment_lock_stmt->execute(['assignment_id' => $assignment_id]);
    if (!$assignment_lock_stmt->fetchColumn()) {
        $pdo->rollBack();
        header("Location: ecr-inputdata.php?error=assignment_not_found");
        exit();
    }

    $saved_scores_stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ecr_student_scores scores
        JOIN ecr_assessment_components components ON components.id = scores.component_id
        WHERE components.assignment_id = :assignment_id AND components.term = :term
    ");
    $saved_scores_stmt->execute([
        'assignment_id' => $assignment_id,
        'term' => $term
    ]);
    $has_saved_scores = (int)$saved_scores_stmt->fetchColumn() > 0;

    $saved_summary_stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ecr_term_summaries
        WHERE assignment_id = :assignment_id AND {$term_column} IS NOT NULL
    ");
    $saved_summary_stmt->execute(['assignment_id' => $assignment_id]);
    $has_saved_summary = (int)$saved_summary_stmt->fetchColumn() > 0;

    if ($has_saved_scores || $has_saved_summary) {
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
        // Fetch current tasks for category
        $stmt = $pdo->prepare("
            SELECT task_number FROM ecr_assessment_components 
            WHERE assignment_id = :assignment_id AND term = :term AND category = :category
            ORDER BY task_number ASC
        ");
        $stmt->execute([
            'assignment_id' => $assignment_id,
            'term' => $term,
            'category' => $category
        ]);
        $existing_tasks = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $current_count = count($existing_tasks);

        if ($current_count < $target_count) {
            // Add missing columns
            $insert_stmt = $pdo->prepare("
                INSERT INTO ecr_assessment_components (assignment_id, term, category, task_number, highest_possible_score)
                VALUES (:assignment_id, :term, :category, :task_number, 100.00)
            ");
            for ($i = $current_count + 1; $i <= $target_count; $i++) {
                $insert_stmt->execute([
                    'assignment_id' => $assignment_id,
                    'term' => $term,
                    'category' => $category,
                    'task_number' => $i
                ]);
            }
        } elseif ($current_count > $target_count) {
            // Remove excess columns from the end
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
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}

// Extract numeric term for clean URL redirect
$term_num = filter_var($term, FILTER_SANITIZE_NUMBER_INT);
header("Location: ecr-inputdata.php?assignment_id={$assignment_id}&term={$term_num}");
exit();