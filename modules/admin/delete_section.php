<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$filters = http_build_query([
    'academic_year' => $_GET['academic_year'] ?? '2026-2027',
    'curriculum_version' => $_GET['curriculum_version'] ?? 'Strengthened',
]);
header('Location: manage_sections.php?' . $filters . '&error=archive_failed');
exit();
