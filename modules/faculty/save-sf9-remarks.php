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
$term1         = trim($_POST['term1_comment'] ?? '');
$term2         = trim($_POST['term2_comment'] ?? '');
$term3         = trim($_POST['term3_comment'] ?? '');
$user_id       = $_SESSION['user_id'] ?? null;

if ($student_id <= 0 || empty($academic_year)) {
    echo json_encode(['success' => false, 'message' => 'Missing student ID or academic year.']);
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO sf9_adviser_remarks (student_id, academic_year, term, comment, updated_by)
        VALUES (:student_id, :sy, :term, :comment, :user_id)
        ON DUPLICATE KEY UPDATE 
            comment = VALUES(comment),
            updated_by = VALUES(updated_by)
    ");

    $terms = [
        'Term 1' => $term1,
        'Term 2' => $term2,
        'Term 3' => $term3
    ];

    foreach ($terms as $term => $comment) {
        $stmt->execute([
            'student_id' => $student_id,
            'sy'         => $academic_year,
            'term'       => $term,
            'comment'    => $comment,
            'user_id'    => $user_id
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Adviser remarks updated successfully.']);
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}