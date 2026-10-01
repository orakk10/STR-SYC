<?php
// modules/admin/save_user.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Authorization check: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();

    $full_name  = trim($_POST['full_name'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $role       = trim($_POST['role'] ?? 'student');
    $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
    $raw_pass   = $_POST['password'] ?? 'strand1234';

    // Faculty and Admin roles do not assign to a student section
    if ($role === 'admin' || $role === 'faculty') {
        $section_id = null;
    }

    if (empty($full_name) || empty($username) || empty($raw_pass)) {
        header("Location: manage_users.php?msg=error_fields");
        exit();
    }

    try {
        $conn->beginTransaction();

        // 1. Check if username/LRN already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        $checkStmt->execute(['username' => $username]);
        if ($checkStmt->fetch()) {
            $conn->rollBack();
            header("Location: manage_users.php?msg=error_exists");
            exit();
        }

        // 2. Hash Password and Insert User
        $hashed_password = password_hash($raw_pass, PASSWORD_BCRYPT);

        $userStmt = $conn->prepare("
            INSERT INTO users (lrn, username, password, full_name, role, section_id, require_reset) 
            VALUES (:lrn, :username, :password, :full_name, :role, :section_id, 1)
        ");

        $userStmt->execute([
            'lrn'        => ($role === 'student') ? $username : null,
            'username'   => $username,
            'password'   => $hashed_password,
            'full_name'  => $full_name,
            'role'       => $role,
            'section_id' => $section_id
        ]);

        $new_user_id = $conn->lastInsertId();

        // 3. Initialize Default Profile Entry
        $profileStmt = $conn->prepare("
            INSERT INTO student_profiles (user_id, gender) 
            VALUES (:user_id, 'Male')
        ");
        $profileStmt->execute(['user_id' => $new_user_id]);

        // 4. Audit Logging
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, affected_table, ip_address) 
            VALUES (:uid, :action, 'users', :ip)
        ");
        $logStmt->execute([
            'uid'    => $_SESSION['user_id'],
            'action' => "Created new user: {$username} ({$role})",
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        $conn->commit();
        header("Location: manage_users.php?msg=success");
        exit();

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        header("Location: manage_users.php?msg=error");
        exit();
    }
} else {
    header("Location: manage_users.php");
    exit();
}