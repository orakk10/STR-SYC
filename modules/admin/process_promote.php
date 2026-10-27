<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/PromotionVerification.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: admin_archive.php?status=error');
    exit();
}

$sourceSectionId = filter_input(INPUT_POST, 'source_section_id', FILTER_VALIDATE_INT);
$destinationSectionId = filter_input(INPUT_POST, 'destination_section_id', FILTER_VALIDATE_INT);
if (
    !$sourceSectionId || $sourceSectionId < 1
    || !$destinationSectionId || $destinationSectionId < 1
    || $sourceSectionId === $destinationSectionId
) {
    header('Location: admin_archive.php?status=error');
    exit();
}

$conn = getDBConnection();

try {
    ensurePromotionVerificationTable($conn);
    $conn->beginTransaction();

    $sectionStmt = $conn->prepare("
        SELECT id, grade_level, strand_id, curriculum_version
        FROM sections
        WHERE id IN (:source_id, :destination_id)
        ORDER BY id
        FOR UPDATE
    ");
    $sectionStmt->execute([
        'source_id' => $sourceSectionId,
        'destination_id' => $destinationSectionId,
    ]);
    $sections = [];
    foreach ($sectionStmt->fetchAll(PDO::FETCH_ASSOC) as $section) {
        $sections[(int) $section['id']] = $section;
    }

    $source = $sections[$sourceSectionId] ?? null;
    $destination = $sections[$destinationSectionId] ?? null;
    if (
        !$source
        || !$destination
        || (int) $source['grade_level'] !== 11
        || (int) $destination['grade_level'] !== 12
        || (int) ($source['strand_id'] ?? 0) !== (int) ($destination['strand_id'] ?? 0)
        || $source['curriculum_version'] !== $destination['curriculum_version']
    ) {
        $conn->rollBack();
        header('Location: admin_archive.php?status=error');
        exit();
    }

    $studentsStmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE section_id = :source_id AND role = 'student'
        ORDER BY id
        FOR UPDATE
    ");
    $studentsStmt->execute(['source_id' => $sourceSectionId]);
    $studentIds = array_map('intval', $studentsStmt->fetchAll(PDO::FETCH_COLUMN));
    if (empty($studentIds)) {
        $conn->rollBack();
        header('Location: admin_archive.php?status=no_students');
        exit();
    }

    $assignmentsStmt = $conn->prepare("
        SELECT id
        FROM subject_assignments
        WHERE section_id = :section_id
        ORDER BY id
        FOR UPDATE
    ");
    $assignmentsStmt->execute(['section_id' => $sourceSectionId]);
    $assignmentIds = array_map('intval', $assignmentsStmt->fetchAll(PDO::FETCH_COLUMN));

    $verifiedStudents = [];
    if (!empty($assignmentIds)) {
        $verificationStmt = $conn->prepare("
            SELECT student_id
            FROM ecr_promotion_verifications_by_assignment
            WHERE assignment_id IN (" . implode(',', array_fill(0, count($assignmentIds), '?')) . ")
              AND student_id IN (" . implode(',', array_fill(0, count($studentIds), '?')) . ")
            GROUP BY student_id
            HAVING COUNT(DISTINCT assignment_id) = ?
        ");
        $verificationStmt->execute(array_merge($assignmentIds, $studentIds, [count($assignmentIds)]));
        $verifiedStudents = array_map('intval', $verificationStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if (!empty($verifiedStudents)) {
        $studentPlaceholders = implode(',', array_fill(0, count($verifiedStudents), '?'));
        $promoteStmt = $conn->prepare("
            UPDATE users
            SET section_id = ?
            WHERE section_id = ? AND role = 'student' AND id IN ($studentPlaceholders)
        ");
        $promoteStmt->execute(array_merge(
            [$destinationSectionId, $sourceSectionId],
            $verifiedStudents
        ));
        $promotedCount = $promoteStmt->rowCount();
    } else {
        $promotedCount = 0;
    }

    $remainingCount = count($studentIds) - $promotedCount;
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
        VALUES (:user_id, :action, 'users', :ip_address)
    ");
    $logStmt->execute([
        'user_id' => (int) $_SESSION['user_id'],
        'action' => 'Promoted ' . $promotedCount . ' verified Grade 11 students from section '
            . $sourceSectionId . ' to Grade 12 section ' . $destinationSectionId
            . '; ' . $remainingCount . ' students remained pending verification.',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ]);

    $conn->commit();
    header('Location: admin_archive.php?status=promoted');
    exit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Student promotion failed: ' . $e->getMessage());
    header('Location: admin_archive.php?status=error');
    exit();
}