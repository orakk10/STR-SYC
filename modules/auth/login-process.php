<?php
// modules/auth/login-process.php
session_start();
require_once __DIR__ . '/../../config/database.php';

$pdo = getDBConnection();
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = "Please enter both username and password.";
    header("Location: ../../manifest/login.php");
    exit();
}

// 1. Authenticate primary credentials
$stmt = $pdo->prepare("SELECT id, password, role, security_preference FROM users WHERE username = :username LIMIT 1");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    // Clear any previous error messages
    unset($_SESSION['error']);

    $sec_pref = $user['security_preference'] ?? 'none';

    if ($sec_pref !== 'none' && !empty($sec_pref)) {
        // Store temporary pending state for 2FA challenge
        $_SESSION['pending_user_id'] = $user['id'];
        $_SESSION['pending_role'] = $user['role'];
        $_SESSION['pending_security_pref'] = $sec_pref;
        
        // Redirect to one-time secondary security challenge page
        header("Location: 2fa-challenge.php");
        exit();
    }

    // No secondary security configured — complete login directly
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    // Clear lingering 2FA pending keys
    unset($_SESSION['pending_user_id']);
    unset($_SESSION['pending_role']);
    unset($_SESSION['pending_security_pref']);

    // Map role-based redirect destination
    $role = strtolower($user['role'] ?? '');
    $redirect_url = '../../index.php';

    if ($role === 'student') {
        $redirect_url = '../student/student_dashboard.php';
    } elseif (in_array($role, ['adviser', 'faculty'])) {
        $redirect_url = '../faculty/faculty_dashboard.php';
    } elseif (in_array($role, ['super_admin', 'admin', 'super admin'])) {
        $redirect_url = '../admin/admin_dashboard.php';
    }

    header("Location: " . $redirect_url);
    exit();
} else {
    $_SESSION['error'] = "Invalid username or password.";
    header("Location: ../../manifest/login.php");
    exit();
}