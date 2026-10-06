<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$studentIds = $_POST['student_ids'] ?? [];
if (!is_array($studentIds)) {
    http_response_code(400);
    exit('Invalid student selection.');
}

foreach ($studentIds as $studentId) {
    if (!is_string($studentId) || !ctype_digit($studentId) || (int) $studentId < 1) {
        http_response_code(400);
        exit('Invalid student selection.');
    }
}

$studentIds = array_values(array_unique(array_map('intval', $studentIds)));
$students = [];

if ($studentIds) {
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $query = "SELECT u.id, u.full_name, s.grade_level, s.section_name
              FROM users u
              LEFT JOIN sections s ON u.section_id = s.id
              WHERE u.role = 'student' AND u.id IN ($placeholders)";
    $statement = $conn->prepare($query);
    $statement->execute($studentIds);

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $student) {
        $students[$student['id']] = $student;
    }
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Master_Student_List.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Student Name', 'Grade', 'Section Name']);

foreach ($studentIds as $studentId) {
    if (!isset($students[$studentId])) {
        continue;
    }

    $student = $students[$studentId];
    fputcsv($output, [
        $student['full_name'],
        $student['grade_level'] ?? '',
        $student['section_name'] ?? 'Unassigned',
    ]);
}

fclose($output);
exit();