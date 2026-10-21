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
    $query = "SELECT u.id, u.lrn, u.full_name, s.grade_level, s.academic_year, s.curriculum_version, s.section_name,
                     CASE WHEN s.id IS NULL THEN '' ELSE COALESCE(st.strand_name, 'General / Multi-Cluster') END AS strand_name,
                     st.strand_code,
                     sp.birthdate, sp.gender, sp.address, sp.contact_no, sp.guardian_name,
                     sp.guardian_contact, sp.specialization
              FROM users u
              LEFT JOIN sections s ON u.section_id = s.id
              LEFT JOIN strands st ON s.strand_id = st.id
              LEFT JOIN student_profiles sp ON sp.user_id = u.id
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
fputcsv($output, [
    'LRN', 'Student Name', 'Academic Year', 'Curriculum Version', 'Grade Level', 'Strand', 'Strand Code', 'Section Name',
    'Birthdate', 'Gender', 'Address', 'Contact No', 'Guardian Name',
    'Guardian Contact', 'Specialization'
]);

foreach ($studentIds as $studentId) {
    if (!isset($students[$studentId])) {
        continue;
    }

    $student = $students[$studentId];
    fputcsv($output, [
        $student['lrn'],
        $student['full_name'],
        $student['academic_year'] ?? '',
        $student['curriculum_version'] ?? '',
        $student['grade_level'] ?? '',
        $student['strand_name'] ?? '',
        $student['strand_code'] ?? '',
        $student['section_name'] ?? 'Unassigned',
        $student['birthdate'] ?: 'N/A',
        $student['gender'] ?: 'N/A',
        $student['address'] ?: 'N/A',
        $student['contact_no'] ?: 'N/A',
        $student['guardian_name'] ?: 'N/A',
        $student['guardian_contact'] ?: 'N/A',
        $student['specialization'] ?: 'N/A',
    ]);
}

fclose($output);
exit();