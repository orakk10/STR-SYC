<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

require_once __DIR__ . '/../faculty/sf9-reportcard.php';
