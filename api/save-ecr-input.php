<?php
session_start();

// 1. Correct Path Resolution using __DIR__
require_once __DIR__ . '/../config/database.php';

// Set JSON header for AJAX responses or redirect handling
header('Content-Type: application/json; charset=utf-8');

// 2. Access Control Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
    exit();
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // 3. Extract POST Payload Data
    $section_id  = intval($_POST['section_id'] ?? 0);
    $region      = trim($_POST['region'] ?? '');
    $division    = trim($_POST['division'] ?? '');
    $school_id   = trim($_POST['school_id'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $school_year = trim($_POST['school_year'] ?? '2026-2027');
    
    $teacher_name = trim($_POST['teacher_name'] ?? '');
    $track        = trim($_POST['track'] ?? '');
    $grade_level  = trim($_POST['grade_level'] ?? '');
    $section_name = trim($_POST['section'] ?? '');
    $subject_type = trim($_POST['subject_type'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');

    // 4. Update Section Info in Database (if valid section_id exists)
    if ($section_id > 0) {
        $update_sec = $pdo->prepare("
            UPDATE sections 
            SET section_name = :section_name, grade_level = :grade_level 
            WHERE id = :section_id
        ");
        $update_sec->execute([
            'section_name' => $section_name,
            'grade_level'  => $grade_level,
            'section_id'   => $section_id
        ]);
    }

    // 5. Sync Male Student Roster
    $male_names = $_POST['male_students'] ?? [];
    $male_ids   = $_POST['male_student_ids'] ?? [];

    foreach ($male_names as $index => $name) {
        $name = trim($name);
        $student_id = intval($male_ids[$index] ?? 0);

        if (!empty($name)) {
            if ($student_id > 0) {
                // Update Existing Male Student
                $stmt = $pdo->prepare("UPDATE users SET full_name = :name, gender = 'Male', section_id = :section_id WHERE id = :id AND role = 'student'");
                $stmt->execute(['name' => $name, 'section_id' => $section_id, 'id' => $student_id]);
            } else if ($section_id > 0) {
                // Insert New Male Student
                $stmt = $pdo->prepare("INSERT INTO users (full_name, role, gender, section_id) VALUES (:name, 'student', 'Male', :section_id)");
                $stmt->execute(['name' => $name, 'section_id' => $section_id]);
            }
        }
    }

    // 6. Sync Female Student Roster
    $female_names = $_POST['female_students'] ?? [];
    $female_ids   = $_POST['female_student_ids'] ?? [];

    foreach ($female_names as $index => $name) {
        $name = trim($name);
        $student_id = intval($female_ids[$index] ?? 0);

        if (!empty($name)) {
            if ($student_id > 0) {
                // Update Existing Female Student
                $stmt = $pdo->prepare("UPDATE users SET full_name = :name, gender = 'Female', section_id = :section_id WHERE id = :id AND role = 'student'");
                $stmt->execute(['name' => $name, 'section_id' => $section_id, 'id' => $student_id]);
            } else if ($section_id > 0) {
                // Insert New Female Student
                $stmt = $pdo->prepare("INSERT INTO users (full_name, role, gender, section_id) VALUES (:name, 'student', 'Female', :section_id)");
                $stmt->execute(['name' => $name, 'section_id' => $section_id]);
            }
        }
    }

    // Commit Transaction
    $pdo->commit();

    // 7. Response Redirection
    header("Location: ../modules/faculty/ecr-inputdata.php?status=success");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("ECR Input Save Error: " . $e->getMessage());
    
    header("Location: ../modules/faculty/ecr-inputdata.php?status=error");
    exit();
}