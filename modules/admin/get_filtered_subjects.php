<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$section_id = isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0;
if ($section_id <= 0) {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare("
    SELECT DISTINCT sub.id, sub.subject_name, sub.subject_code
    FROM sections sec
    INNER JOIN subject_assignments sa
        ON sa.section_id = sec.id
        AND sa.academic_year = sec.academic_year
    INNER JOIN subjects sub ON sub.id = sa.subject_id
    WHERE sec.id = ?
    ORDER BY sub.subject_name ASC
");
$stmt->execute([$section_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($subjects);
exit();
