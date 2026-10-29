<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (
    !isset($_SESSION['user_id'])
    || !in_array(strtolower($_SESSION['role'] ?? ''), ['faculty', 'adviser'], true)
) {
    header('Location: ../../manifest/login.php');
    exit();
}

$conn = getDBConnection();
$user_id = (int) $_SESSION['user_id'];
$message = '';
$message_type = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $message = 'Your session token is invalid. Refresh the page and try again.';
        $message_type = 'error';
    } elseif ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $message = 'All password fields are required.';
        $message_type = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = 'Your new password must be at least 8 characters long.';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'The new password and confirmation do not match.';
        $message_type = 'error';
    } else {
        $password_query = $conn->prepare('SELECT password FROM users WHERE id = ? AND role IN (?, ?)');
        $password_query->execute([$user_id, 'faculty', 'adviser']);
        $user = $password_query->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {
            $message = 'Your current password does not match our records.';
            $message_type = 'error';
        } else {
            $password_update = $conn->prepare('UPDATE users SET password = ?, require_reset = 0 WHERE id = ?');
            $password_update->execute([
                password_hash($new_password, PASSWORD_DEFAULT),
                $user_id,
            ]);

            header('Location: faculty_dashboard.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .password-page {
            box-sizing: border-box;
            max-width: 640px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        .password-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .form-group label {
            color: #475569;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .form-group input {
            box-sizing: border-box;
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1rem;
        }

        .notice, .message {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 8px;
        }

        .notice {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
        }

        .message-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }

        .message-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .back-link {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="password-page">
        <section class="password-card">
            <h1>Change Password</h1>

            <?php if ($message !== ''): ?>
                <div class="message message-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php
            $reset_query = $conn->prepare('SELECT require_reset FROM users WHERE id = ?');
            $reset_query->execute([$user_id]);
            $require_reset = (bool) $reset_query->fetchColumn();
            ?>

            <?php if ($require_reset): ?>
                <div class="notice" role="alert">
                    Your account is using a temporary password. Change it now to secure your account.
                </div>
            <?php endif; ?>

            <form method="POST" action="change_password.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="actions">
                    <button type="submit" class="btn-action">Save New Password</button>
                    <a class="back-link" href="faculty_dashboard.php">Back to dashboard</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
