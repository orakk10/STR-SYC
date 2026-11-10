<?php
session_start();

if (($_SESSION['role'] ?? '') === 'super_admin') {
    header('Location: ../super%20admin/account_archive.php');
    exit();
}

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: manage_users.php');
    exit();
}

header('Location: ../../manifest/login.php');
exit();
