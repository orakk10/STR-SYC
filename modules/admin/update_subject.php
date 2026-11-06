<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: curriculum_guide.php?error=invalid_request');
    exit();
}

$id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$strand_id = isset($_POST['strand_id']) ? (int) $_POST['strand_id'] : 0;
$type = trim($_POST['type'] ?? 'Core');
$grade_level = isset($_POST['grade_level']) ? (int) $_POST['grade_level'] : 0;
$term = trim($_POST['term'] ?? '');
$subject_code = trim($_POST['subject_code'] ?? '');
$subject_name = trim($_POST['subject_name'] ?? '');

if (
    $id <= 0 ||
    $strand_id <= 0 ||
    !in_array($type, ['Core', 'Applied', 'Specialized', 'Elective'], true) ||
    !in_array($grade_level, [11, 12], true) ||
    $term === '' ||
    $subject_code === '' ||
    $subject_name === ''
) {
    header('Location: curriculum_guide.php?error=missing_fields');
    exit();
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare('UPDATE subjects SET strand_id = ?, type = ?, grade_level = ?, term = ?, subject_code = ?, subject_name = ? WHERE id = ?');
    $stmt->execute([$strand_id, $type, $grade_level, $term, $subject_code, $subject_name, $id]);

    $existsStmt = $conn->prepare('SELECT curriculum_version FROM subjects WHERE id = ?');
    $existsStmt->execute([$id]);
    $curr_version = $existsStmt->fetchColumn();
    if ($curr_version === false) {
        throw new Exception('subject_not_found');
    }

    // Log the subject update utilizing the structured details column
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address, details)
        VALUES (:user_id, :action, 'subjects', :ip_address, :details)
    ");
    $logStmt->execute([
        'user_id'    => (int) $_SESSION['user_id'],
        'action'     => 'UPDATE_SUBJECT',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'details'    => json_encode([
            'subject_id'         => $id,
            'strand_id'          => $strand_id,
            'subject_code'       => $subject_code,
            'subject_name'       => $subject_name,
            'grade_level'        => $grade_level,
            'term'               => $term,
            'type'               => $type,
            'curriculum_version' => $curr_version
        ])
    ]);

    $conn->commit();
    $redirect_version = is_string($curr_version) ? $curr_version : 'Strengthened';
    header('Location: curriculum_guide.php?version=' . urlencode($redirect_version) . '&msg=success');
    exit();
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Subject update failed: ' . $e->getMessage());
    $err = $e->getMessage() === 'subject_not_found' ? 'subject_not_found' : 'update_failed';
    header('Location: curriculum_guide.php?error=' . $err);
    exit();
}