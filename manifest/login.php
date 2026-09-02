<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: {$role}_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | STRAND-SYNC</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
            body {
            background: linear-gradient(rgba(15, 23, 42, 0.75), rgba(15, 23, 42, 0.75)), 
                        url('img/school_image.jpg') no-repeat center center fixed;
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

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .hidden { display: none; }
        .forgot-link { display: block; text-align: center; margin-top: 15px; color: #64748b; font-size: 0.85rem; text-decoration: none; transition: color 0.2s; }
        .forgot-link:hover { color: #2563eb; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <a href="index.php"><img src="img/logo_strandsync.jpg" alt="STRAND-SYNC Logo" class="login-logo"></a>
                <h1>STRAND-SYNC</h1>
                <p>Academic Management System</p>
            </div>
            
            <form id="loginForm">
                <div class="input-group">
                    <label for="username">Username / LRN</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" id="togglePassword" class="toggle-btn"></button>
                    </div>
                </div>

                <button type="submit" class="login-btn">Secure Login</button>
            </form>

            <a href="#" id="forgotPasswordLink" class="forgot-link">Forgot Password?</a>

            <div class="login-footer">
                <p>&copy; 2026 Strand-Sync Infrastructure</p>
            </div>
        </div>
    </div>

    <div id="resetModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3>Account Recovery</h3>
            <p style="font-size: 0.9rem; color: #475569; margin-bottom: 15px;">Enter your username. The admin will be notified to reset your password.</p>
            <div class="input-group">
                <input type="text" id="resetUsername" placeholder="Enter Username / LRN" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" onclick="closeResetModal()" style="background: none; border: none; cursor: pointer; color: #64748b;">Cancel</button>
                <button type="button" onclick="submitResetRequest()" style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Notify Admin</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>