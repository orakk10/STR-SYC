<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', ''); 
    ini_set('session.gc_maxlifetime', 86400);
    session_set_cookie_params(86400);

    session_start();
}

$conn = new mysqli("localhost", "root", "", "strand_sync_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>