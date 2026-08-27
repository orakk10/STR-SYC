<?php
// api/save-ecr-input.php
// require_once '../config/database.php';
// require_once '../core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect Input Information
    $section = $_POST['section'] ?? '';
    $subject_name = $_POST['subject_name'] ?? '';
    $male_students = array_filter($_POST['male_students'] ?? []);
    $female_students = array_filter($_POST['female_students'] ?? []);

    // Database operation logic (saves student names & metadata)
    // ... insert/update queries into database ...

    // Redirect to Term 1 Gradebook
    header("Location: ../modules/faculty/ecr-view.php?term=1&status=success");
    exit();
}
?>