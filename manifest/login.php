<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// Helper function to get role dashboard URL
function getDashboardUrl($role) {
    switch ($role) {
        case 'admin':
            return "../modules/admin/admin_dashboard.php";
        case 'faculty':
        case 'adviser':
            return "../modules/faculty/dashboard.php";
        case 'student':
            return "../modules/student/dashboard.php";
        case 'alumni':
            return "../modules/alumni/dashboard.php";
        default:
            return "../index.php";
    }
}

// 1. Redirect if already authenticated
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header("Location: " . getDashboardUrl($_SESSION['role']));
    exit();
}

$error = '';

// 2. Handle Asynchronous (AJAX) or Native POST Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $db = getDBConnection();
        
        $stmt = $db->prepare("SELECT id, username, password, role, full_name, section_id FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['section_id'] = $user['section_id'];

            // Log user activity
            $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (:uid, 'User Login', :ip)");
            $logStmt->execute([
                'uid' => $user['id'],
                'ip'  => $_SERVER['REMOTE_ADDR']
            ]);

            $redirectUrl = getDashboardUrl($user['role']);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'redirect' => $redirectUrl]);
                exit();
            }

            header("Location: " . $redirectUrl);
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $error]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | STRAND-SYNC</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(rgba(15, 23, 42, 0.75), rgba(15, 23, 42, 0.75)),
                url('../assets/img/school_image.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-logo {
            width: 100%;
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
            display: inline-block;
        }

        .alert-box {
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 15px;
            text-align: center;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .hidden {
            display: none;
        }

        .forgot-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #64748b;
            font-size: 0.85rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: #2563eb;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <a href="../index.php"><img src="../assets/img/logo_strandsync.jpg" alt="STRAND-SYNC Logo" class="login-logo"></a>
                <h1>STRAND-SYNC</h1>
                <p>Academic Management System</p>
            </div>

            <!-- Dynamic Alert Message Box -->
            <div id="alertBox" class="alert-box alert-danger <?php echo empty($error) ? 'hidden' : ''; ?>">
                <?php echo htmlspecialchars($error ?? ''); ?>
            </div>

            <!-- Fixed action attribute -->
            <form id="loginForm" method="POST" action="">
                <div class="input-group">
                    <label for="username">Username / LRN</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" id="togglePassword" class="toggle-btn">Show</button>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="login-btn">Secure Login</button>
            </form>

            <a href="#" id="forgotPasswordLink" class="forgot-link">Forgot Password?</a>

            <div class="login-footer">
                <p>&copy; 2026 Strand-Sync Infrastructure</p>
            </div>
        </div>
    </div>

    <!-- Account Recovery Modal -->
    <div id="resetModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3>Account Recovery</h3>
            <p style="font-size: 0.9rem; color: #475569; margin-bottom: 15px;">Enter your username. The admin will be notified to reset your password.</p>
            <div class="input-group">
                <input type="text" id="resetUsername" placeholder="Enter Username / LRN" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" id="closeResetBtn" style="background: none; border: none; cursor: pointer; color: #64748b;">Cancel</button>
                <button type="button" id="submitResetBtn" style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Notify Admin</button>
            </div>
        </div>
    </div>

    <!-- Fixed script source path -->
    <script src="../assets/js/app.js"></script>
</body>

</html>