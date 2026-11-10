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
      AND NOT EXISTS (
          SELECT 1 FROM resource_archives ra
          WHERE ra.resource_type = 'section' AND ra.resource_id = sec.id
      )
      AND (
          sec.strand_id IS NULL OR NOT EXISTS (
              SELECT 1 FROM resource_archives ra
              WHERE ra.resource_type = 'strand' AND ra.resource_id = sec.strand_id
          )
      )
      AND NOT EXISTS (
          SELECT 1 FROM resource_archives ra
          WHERE ra.resource_type = 'assignment' AND ra.resource_id = sa.id
      )
      AND NOT EXISTS (
          SELECT 1 FROM resource_archives ra
          WHERE ra.resource_type = 'subject' AND ra.resource_id = sub.id
      )
      AND (
          sub.strand_id IS NULL OR NOT EXISTS (
              SELECT 1 FROM resource_archives ra
              WHERE ra.resource_type = 'strand' AND ra.resource_id = sub.strand_id
          )
      )
    ORDER BY sub.subject_name ASC
");
$stmt->execute([$section_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($subjects);
exit();
