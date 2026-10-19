<?php

function isEcrTermLocked(PDO $pdo, int $assignmentId, string $term): bool
{
    $termMatch = [];
    if ($assignmentId <= 0 || !preg_match('/^Term ([1-3])$/', $term, $termMatch)) {
        throw new InvalidArgumentException('A valid assignment and term are required to check the ECR lock.');
    }

    $termColumn = 'term' . $termMatch[1] . '_transmuted';
    $stmt = $pdo->prepare("
        SELECT (
            EXISTS (
                SELECT 1
                FROM ecr_student_scores scores
                JOIN ecr_assessment_components components ON components.id = scores.component_id
                JOIN subject_assignments assignments ON assignments.id = components.assignment_id
                JOIN users students
                    ON students.id = scores.student_id
                    AND students.section_id = assignments.section_id
                    AND students.role = 'student'
                WHERE components.assignment_id = :score_assignment_id
                    AND components.term = :score_term
            )
            OR EXISTS (
                SELECT 1
                FROM ecr_term_summaries summaries
                JOIN subject_assignments assignments ON assignments.id = summaries.assignment_id
                JOIN users students
                    ON students.id = summaries.student_id
                    AND students.section_id = assignments.section_id
                    AND students.role = 'student'
                WHERE summaries.assignment_id = :summary_assignment_id
                    AND summaries.{$termColumn} IS NOT NULL
            )
        ) AS is_locked
    ");
    $stmt->execute([
        'score_assignment_id' => $assignmentId,
        'score_term' => $term,
        'summary_assignment_id' => $assignmentId
    ]);

    return (bool)$stmt->fetchColumn();
}
