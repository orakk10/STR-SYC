<?php
// api/adviser/get-roster-summary.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';

// Authorization check: Adviser or Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['adviser', 'admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Adviser privileges required.']);
    exit();
}

$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : ($_SESSION['section_id'] ?? null);

if (!$sectionId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing section_id parameter.']);
    exit();
}

try {
    $db = getDBConnection();

    // Fetch roster grouped by gender
    $stmtRoster = $db->prepare("
        SELECT 
            u.id AS student_id,
            u.lrn,
            u.full_name,
            sp.gender
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student'
        ORDER BY sp.gender DESC, u.full_name ASC
    ");
    $stmtRoster->execute(['section_id' => $sectionId]);
    $students = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

    // Fetch term summaries for all students in this section
    $stmtGrades = $db->prepare("
        SELECT 
            ets.student_id,
            sub.subject_code,
            sub.subject_name,
            ets.term1_transmuted,
            ets.term2_transmuted,
            ets.term3_transmuted,
            ets.final_grade,
            ets.letter_grade,
            ets.remarks
        FROM ecr_term_summaries ets
        INNER JOIN subject_assignments sa ON ets.assignment_id = sa.id
        INNER JOIN subjects sub ON sa.subject_id = sub.id
        WHERE sa.section_id = :section_id
    ");
    $stmtGrades->execute(['section_id' => $sectionId]);
    $rawGrades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

    // Map grades per student
    $gradesByStudent = [];
    foreach ($rawGrades as $g) {
        $sid = $g['student_id'];
        if (!isset($gradesByStudent[$sid])) {
            $gradesByStudent[$sid] = [];
        }
        $gradesByStudent[$sid][] = $g;
    }

    // Separate into Male/Female rosters
    $maleRoster = [];
    $femaleRoster = [];

    foreach ($students as $s) {
        $s['grades'] = $gradesByStudent[$s['student_id']] ?? [];
        if (strcasecmp($s['gender'] ?? '', 'Female') === 0) {
            $femaleRoster[] = $s;
        } else {
            $maleRoster[] = $s;
        }
    }

    echo json_encode([
        'status' => 'success',
        'section_id' => $sectionId,
        'data' => [
            'male_students'   => $maleRoster,
            'female_students' => $femaleRoster
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}