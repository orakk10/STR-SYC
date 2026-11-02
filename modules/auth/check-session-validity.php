<?php
// Prevent browser caching of the validity check
header("Cache-Control: no-cache, no-store, must-revalidate");
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

// If unauthenticated, signal invalid session
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'reason' => 'unauthenticated']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$client_session_id = session_id();

// Immediately release session write lock so other requests aren't blocked on InfinityFree
session_write_close();

try {
    $conn = getDBConnection();
    
    // Fetch the stored session ID for this user from DB
    $stmt = $conn->prepare("SELECT COALESCE(current_session_id, '') AS db_session FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $db_session = $stmt->fetchColumn();

    // If DB session doesn't match this browser's session ID -> MISMATCH DETECTED
    if (!empty($db_session) && $db_session !== $client_session_id) {
        // Clear session on mismatch
        session_start();
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        echo json_encode([
            'valid' => false, 
            'reason' => 'device_mismatch',
            'debug' => [
                'db' => $db_session,
                'client' => $client_session_id
            ]
        ]);
        exit;
    }

    echo json_encode(['valid' => true]);
} catch (Exception $e) {
    // Fail safe on temporary DB hiccup
    echo json_encode(['valid' => true]);
}
exit;