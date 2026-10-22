<?php

function isCurriculumSubjectAvailableForEcrTerm(?string $curriculumVersion, ?string $curriculumTerm, string $ecrTerm): bool
{
    $termMatch = [];
    if (!preg_match('/^Term ([1-3])$/', $ecrTerm, $termMatch)) {
        return false;
    }

    $termNumber = (int)$termMatch[1];
    $curriculumTerm = trim((string)$curriculumTerm);

    if ($curriculumTerm === 'Full Year') {
        return $curriculumVersion === 'Strengthened';
    }

    $termMap = [
        'Strengthened' => [
            '1st' => 1,
            '2nd' => 2,
            '3rd' => 3,
            '1st Sem' => 1,
            '2nd Sem' => 2
        ],
        'Legacy' => [
            '1st Sem' => 1,
            '2nd Sem' => 2
        ]
    ];

    return isset($termMap[$curriculumVersion][$curriculumTerm])
        && $termMap[$curriculumVersion][$curriculumTerm] === $termNumber;
}

function isEcrAssignmentAvailableForTerm(PDO $pdo, int $assignmentId, string $ecrTerm): bool
{
    if ($assignmentId < 1) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT sub.curriculum_version, sub.`term`
        FROM subject_assignments sa
        JOIN subjects sub ON sub.id = sa.subject_id
        WHERE sa.id = :assignment_id
    ");
    $stmt->execute(['assignment_id' => $assignmentId]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        return false;
    }

    return isCurriculumSubjectAvailableForEcrTerm(
        $subject['curriculum_version'] ?? null,
        $subject['term'] ?? null,
        $ecrTerm
    );
}
