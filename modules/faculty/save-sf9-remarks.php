<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$conn = getDBConnection();

$student_id    = intval($_POST['student_id'] ?? 0);
$academic_year = trim($_POST['academic_year'] ?? '');
$term          = trim($_POST['term'] ?? '');
$comment       = trim($_POST['comment'] ?? '');
$user_id       = $_SESSION['user_id'] ?? null;

if ($student_id <= 0 || empty($academic_year) || !in_array($term, ['Term 1', 'Term 2', 'Term 3'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO sf9_adviser_remarks (student_id, academic_year, term, comment, updated_by)
        VALUES (:student_id, :sy, :term, :comment, :user_id)
        ON DUPLICATE KEY UPDATE 
            comment = VALUES(comment),
            updated_by = VALUES(updated_by)
    ");
    
    $stmt->execute([
        'student_id' => $student_id,
        'sy'         => $academic_year,
        'term'       => $term,
        'comment'    => $comment,
        'user_id'    => $user_id
    ]);

    echo json_encode(['success' => true, 'message' => 'Remark saved successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}