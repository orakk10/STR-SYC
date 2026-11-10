<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$version = in_array($_GET['version'] ?? '', ['Legacy', 'Strengthened'], true)
    ? $_GET['version']
    : 'Strengthened';
header('Location: curriculum_guide.php?version=' . urlencode($version) . '&error=archive_failed');
exit();
