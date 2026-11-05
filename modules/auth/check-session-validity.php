<?php
// modules/auth/check-session-validity.php

// Prevent any proxy or browser caching
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

// Handle secure HTTPS session cookie scope across reverse proxies (ngrok / Cloudflare / InfinityFree)
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

// If unauthenticated locally, exit early
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'reason' => 'unauthenticated']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$client_session_id = session_id();

// Immediately release session write lock to avoid shared hosting locking queues during polling
session_write_close();

try {
    $conn = getDBConnection();
    
    // Fetch the account state and stored active session ID
    $stmt = $conn->prepare("SELECT role, COALESCE(current_session_id, '') AS db_session FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] === 'archived') {
        @session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        @session_destroy();

        echo json_encode(['valid' => false, 'reason' => 'account_archived']);
        exit;
    }

    $db_session = $user['db_session'];

    // If DB session is completely empty, initialize it with current active session
    if (empty($db_session)) {
        $updateStmt = $conn->prepare("UPDATE users SET current_session_id = :session_id WHERE id = :id");
        $updateStmt->execute(['session_id' => $client_session_id, 'id' => $user_id]);
        echo json_encode(['valid' => true, 'status' => 'initialized']);
        exit;
    }

    // Mismatch check: Account has logged in on another device or browser session
    if ($db_session !== $client_session_id) {
        // Safely wipe local session data
        @session_start();
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();

        echo json_encode([
            'valid' => false, 
            'reason' => 'device_mismatch'
        ]);
        exit;
    }

    echo json_encode(['valid' => true]);
} catch (Exception $e) {
    // Fail safe to prevent breaking page navigation if database hiccups temporarily
    echo json_encode(['valid' => true]);
}
exit;