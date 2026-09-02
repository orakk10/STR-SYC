<?php
session_start();
require_once 'db_config.php';

// Access Control: Only admins can trigger a reset
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit("Unauthorized");
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    
    // 1. Create the hashed version of the default password
    $new_password = password_hash("strand1234", PASSWORD_DEFAULT);
    
    // 2. Update the user:
    // - Set the new hashed password
    // - Clear the reset_requested flag (0)
    // - Set require_reset to 1 (forces them to change it on login)
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_requested = 0, require_reset = 1 WHERE id = ?");
    $stmt->bind_param("si", $new_password, $user_id);
    
    if ($stmt->execute()) {
        header("Location: manage_users.php?msg=success");
    } else {
        header("Location: manage_users.php?msg=error");
    }
    $stmt->close();
}
$conn->close();
?>