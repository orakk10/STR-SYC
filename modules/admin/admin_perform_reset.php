<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// Access Control: Only admins can trigger a reset
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    exit("Unauthorized");
}

if (isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];

    if ($user_id > 0) {
        // Create the hashed version of the default password
        $new_password = password_hash('strand1234', PASSWORD_DEFAULT);

        // Update the user:
        // - Set the new hashed password
        // - Clear the reset_requested flag (0)
        // - Set require_reset to 1 (forces them to change it on login)
        $stmt = $conn->prepare("UPDATE users SET password = :password, reset_requested = 0, require_reset = 1 WHERE id = :id");
        $result = $stmt->execute([
            ':password' => $new_password,
            ':id' => $user_id,
        ]);

        if ($result) {
            header("Location: manage_users.php?msg=success");
        } else {
            header("Location: manage_users.php?msg=error");
        }
        exit();
    }
}

header("Location: manage_users.php?msg=error");
exit();
?>