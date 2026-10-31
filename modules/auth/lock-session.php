<?php
// modules/auth/lock-session.php
session_start();

header('Content-Type: application/json');

// Check if an active session exists
if (isset($_SESSION['user_id'])) {
    // Transition active user credentials to pending state
    $_SESSION['pending_user_id'] = $_SESSION['user_id'];
    
    if (isset($_SESSION['role'])) {
        $_SESSION['pending_role'] = $_SESSION['role'];
    }

    // Demote session by unsetting primary authenticated credentials
    unset($_SESSION['user_id']);
    unset($_SESSION['role']);

    echo json_encode([
        'success' => true,
        'message' => 'Session locked due to inactivity.',
        'redirect' => '2fa-challenge.php'
    ]);
    exit;
}

// If already in a pending state or unauthenticated
if (isset($_SESSION['pending_user_id'])) {
    echo json_encode([
        'success' => true,
        'message' => 'Session is already locked.',
        'redirect' => '2fa-challenge.php'
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'No active session found to lock.',
    'redirect' => '../../manifest/login.php'
]);
exit;