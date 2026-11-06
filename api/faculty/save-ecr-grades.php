<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/ActivityLogger.php'; // <-- Include ActivityLogger

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['faculty', 'adviser', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$pdo = getDBConnection();

$data = json_decode(file_get_contents('php://input'), true);

$student_id = $data['student_id'] ?? 0;
$subject_id = $data['subject_id'] ?? 0;
$section_id = $data['section_id'] ?? 0;
$term       = $data['term'] ?? 1; // 1, 2, or 3
$transmuted = $data['transmuted_grade'] ?? null;

if ($student_id && $subject_id && $section_id && in_array($term, [1, 2, 3])) {
    $term_column = "term{$term}_transmuted";

    // Dynamic UPSERT statement
    $sql = "
        INSERT INTO ecr_term_summaries (student_id, subject_id, section_id, {$term_column})
        VALUES (:student_id, :subject_id, :section_id, :transmuted)
        ON DUPLICATE KEY UPDATE {$term_column} = VALUES({$term_column})
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':student_id' => $student_id,
        ':subject_id' => $subject_id,
        ':section_id' => $section_id,
        ':transmuted' => $transmuted
    ]);

    // Recalculate Average & Final Grade
    $calc_stmt = $pdo->prepare("
        SELECT term1_transmuted, term2_transmuted, term3_transmuted 
        FROM ecr_term_summaries 
        WHERE student_id = ? AND subject_id = ? AND section_id = ?
    ");
    $calc_stmt->execute([$student_id, $subject_id, $section_id]);
    $row = $calc_stmt->fetch(PDO::FETCH_ASSOC);

    $final_grade = null;
    $remarks = null;

    if ($row) {
        $terms = array_filter([$row['term1_transmuted'], $row['term2_transmuted'], $row['term3_transmuted']], fn($v) => $v !== null);
        if (!empty($terms)) {
            $final_grade = round(array_sum($terms) / count($terms), 2);
            $remarks = ($final_grade >= 75) ? 'PASSED' : 'FAILED';

            // Update final grade and remarks
            $update_stmt = $pdo->prepare("
                UPDATE ecr_term_summaries 
                SET final_grade = :final_grade, remarks = :remarks 
                WHERE student_id = :student_id AND subject_id = :subject_id AND section_id = :section_id
            ");
            $update_stmt->execute([
                ':final_grade' => $final_grade,
                ':remarks'     => $remarks,
                ':student_id'  => $student_id,
                ':subject_id'  => $subject_id,
                ':section_id'  => $section_id
            ]);
        }
    }

    // RECORD THE ACTIVITY WITH JSON DETAILS
    ActivityLogger::log(
        $pdo,
        (int)$_SESSION['user_id'],
        'SAVE_ECR_GRADES',
        'ecr_term_summaries',
        [
            'student_id'       => $student_id,
            'subject_id'       => $subject_id,
            'section_id'       => $section_id,
            'term'             => $term,
            'transmuted_grade' => $transmuted,
            'final_grade'      => $final_grade,
            'remarks'          => $remarks,
            'ip'               => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    );

    echo json_encode(['status' => 'success']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters provided.']);
}