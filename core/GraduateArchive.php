<?php

function getGraduationBatchYear(string $academicYear): int
{
    if (!preg_match('/^\s*(\d{4})(?:\s*[-–]\s*(\d{4}))?\s*$/u', $academicYear, $matches)) {
        throw new RuntimeException('The section academic year is not a valid graduation year.');
    }

    $batchYear = (int) ($matches[2] ?? $matches[1]);
    if ($batchYear < 1901 || $batchYear > 2155) {
        throw new RuntimeException('The section academic year is outside the supported batch-year range.');
    }

    return $batchYear;
}

function archiveGraduateRecord(PDO $pdo, array $student): void
{
    $originalId = (int) $student['id'];
    $batchYear = getGraduationBatchYear((string) $student['academic_year']);
    $archiveValues = [
        'lrn' => $student['lrn'],
        'username' => $student['username'],
        'full_name' => $student['full_name'],
        'strand_id' => $student['strand_id'],
        'section_id' => $student['section_id'],
        'batch_year' => $batchYear,
    ];

    $existingStmt = $pdo->prepare("
        SELECT id
        FROM archived_students
        WHERE original_id = :original_id
        ORDER BY id
        LIMIT 1
        FOR UPDATE
    ");
    $existingStmt->execute(['original_id' => $originalId]);
    $archiveId = $existingStmt->fetchColumn();

    if ($archiveId !== false) {
        $updateStmt = $pdo->prepare("
            UPDATE archived_students
            SET lrn = :lrn, username = :username, full_name = :full_name,
                strand_id = :strand_id, section_id = :section_id, batch_year = :batch_year
            WHERE id = :archive_id
        ");
        $updateStmt->execute($archiveValues + ['archive_id' => (int) $archiveId]);
        return;
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO archived_students
            (original_id, lrn, username, full_name, strand_id, section_id, batch_year)
        VALUES
            (:original_id, :lrn, :username, :full_name, :strand_id, :section_id, :batch_year)
    ");
    $insertStmt->execute($archiveValues + ['original_id' => $originalId]);
}
