<?php
// modules/auth/force-logout.php

// 1. Handle secure HTTPS session cookie scope across reverse proxies (ngrok / Cloudflare / InfinityFree)
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

// 2. Clear current_session_id in database if user session exists
if ($user_id) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE users SET current_session_id = NULL WHERE id = :id");
        $stmt->execute(['id' => intval($user_id)]);
    } catch (Exception $e) {
        error_log("Force logout DB error: " . $e->getMessage());
    }
}

// 3. Completely purge PHP session variables and session cookie
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

session_destroy();

// 4. Return success JSON payload for client-side handling
echo json_encode([
    'status' => 'success',
    'message' => 'Session invalidated successfully'
]);
exit;