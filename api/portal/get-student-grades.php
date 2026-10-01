<?php
// api/portal/get-student-grades.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit();
}

// Students/Alumni view their own account; Privileged roles can query target students
$studentId = $_SESSION['user_id'];
if (in_array($_SESSION['role'], ['admin', 'adviser', 'faculty']) && isset($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];
}

try {
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT 
            sub.subject_code,
            sub.subject_name,
            sub.type AS subject_type,
            ets.term1_transmuted,
            ets.term2_transmuted,
            ets.term3_transmuted,
            ets.final_grade,
            ets.letter_grade,
            ets.remarks,
            ets.is_locked
        FROM ecr_term_summaries ets
        INNER JOIN subject_assignments sa ON ets.assignment_id = sa.id
        INNER JOIN subjects sub ON sa.subject_id = sub.id
        WHERE ets.student_id = :student_id
        ORDER BY sub.subject_code ASC
    ");

    $stmt->execute(['student_id' => $studentId]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'student_id' => $studentId,
        'data' => $grades
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}