<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_sections.php?error=invalid_request');
    exit();
}

$id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$section_name = trim($_POST['section_name'] ?? '');
$grade_level = isset($_POST['grade_level']) ? (int) $_POST['grade_level'] : 0;
$strand_id = isset($_POST['strand_id']) && $_POST['strand_id'] !== '' ? (int) $_POST['strand_id'] : null;
$adviser_id = isset($_POST['adviser_id']) && $_POST['adviser_id'] !== '' ? (int) $_POST['adviser_id'] : null;
$academic_year = trim($_POST['academic_year'] ?? '');
$curriculum_version = trim($_POST['curriculum_version'] ?? '');

if (
    $id <= 0
    || $section_name === ''
    || !in_array($grade_level, [11, 12], true)
    || !preg_match('/^\d{4}-\d{4}$/', $academic_year)
    || !in_array($curriculum_version, ['Strengthened', 'Legacy'], true)
    || ($strand_id !== null && $strand_id <= 0)
    || ($adviser_id !== null && $adviser_id <= 0)
) {
    header('Location: manage_sections.php?error=missing_data');
    exit();
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare('
        UPDATE sections
        SET section_name = ?, grade_level = ?, strand_id = ?, adviser_id = ?, academic_year = ?, curriculum_version = ?
        WHERE id = ?
    ');
    $stmt->execute([$section_name, $grade_level, $strand_id, $adviser_id, $academic_year, $curriculum_version, $id]);

    // Log the section update utilizing the structured details column
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
        VALUES (:user_id, :action, 'sections', :ip_address, :details)
    ");
    $logStmt->execute([
        'user_id'    => (int) $_SESSION['user_id'],
        'action'     => 'UPDATE_SECTION',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'details'    => json_encode([
            'section_id'         => $id,
            'section_name'       => $section_name,
            'grade_level'        => $grade_level,
            'strand_id'          => $strand_id,
            'adviser_id'         => $adviser_id,
            'academic_year'      => $academic_year,
            'curriculum_version' => $curriculum_version
        ])
    ]);

    $conn->commit();
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Section update failed: ' . $e->getMessage());
    header('Location: manage_sections.php?error=update_failed');
    exit();
}

$query = http_build_query([
    'academic_year' => $academic_year,
    'curriculum_version' => $curriculum_version,
    'msg' => 'updated',
]);
header('Location: manage_sections.php?' . $query);
exit();