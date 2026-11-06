<?php
// modules/auth/settings.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Redirect pending 2FA attempts to the challenge screen
if (!isset($_SESSION['user_id']) && isset($_SESSION['pending_user_id'])) {
    header("Location: 2fa-challenge.php");
    exit;
}

// Redirect unauthenticated users to the correct login path
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../manifest/login.php");
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$user_role = strtolower($_SESSION['role'] ?? 'student');
$current_pref = 'none';

try {
    $stmt = $conn->prepare("SELECT security_preference FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !empty($user['security_preference'])) {
        $current_pref = $user['security_preference'];
    }
} catch (Exception $e) {
    error_log("Settings preference fetch error: " . $e->getMessage());
}

// Route back to the proper dashboard based on user role
$dashboard_url = '../student/student_dashboard.php';
if (in_array($user_role, ['super_admin', 'admin', 'super admin'])) {
    $dashboard_url = '../admin/admin_dashboard.php';
} elseif (in_array($user_role, ['faculty', 'adviser'])) {
    $dashboard_url = '../faculty/faculty_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Account Security Settings | STRAND-SYNC</title>
    
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/security-lock.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --danger: #ef4444;
            --success: #10b981;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            user-select: none;
            -webkit-user-select: none;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }

        main.content {
            flex-grow: 1;
            margin-left: 260px;
            padding: 40px;
            width: calc(100% - 260px);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* SETTINGS CARD UI */
        .settings-card {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 32px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            animation: fadeIn 0.3s ease-out;
            margin-top: 20px;
            box-sizing: border-box;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .settings-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .settings-header .badge-icon {
            width: 56px;
            height: 56px;
            background: #eff6ff;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            box-shadow: 0 0 0 6px #f0f7ff;
        }

        .settings-header .badge-icon svg {
            width: 28px;
            height: 28px;
        }

        .settings-header h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }

        .settings-header p {
            font-size: 0.88rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        .form-group {
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .form-group label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .form-select, .form-input {
            width: 100%;
            padding: 12px 14px;
            font-size: 0.95rem;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            outline: none;
            background-color: #f8fafc;
            color: var(--text-main);
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .form-select:focus, .form-input:focus {
            border-color: var(--primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .pin-input {
            letter-spacing: 0.25em;
            text-align: center;
            font-size: 1.25rem;
            font-weight: 700;
        }

        /* RESPONSIVE PATTERN WRAPPER & CANVAS */
        .pattern-wrapper {
            text-align: center;
            background: #f8fafc;
            border: 1.5px dashed var(--border-color);
            border-radius: 16px;
            padding: 16px;
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        #patternCanvas {
            background: #ffffff;
            border: 2px solid var(--border-color);
            border-radius: 14px;
            touch-action: none !important;
            cursor: pointer;
            display: block;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: border-color 0.2s ease;
            max-width: 100%;
            height: auto;
            box-sizing: border-box;
        }

        #patternCanvas:hover {
            border-color: var(--primary);
        }

        .pattern-status {
            font-size: 0.85rem;
            color: var(--primary);
            margin-top: 10px;
            font-weight: 600;
            min-height: 20px;
        }

        .btn-clear-pattern {
            margin-top: 8px;
            background: none;
            border: none;
            color: var(--danger);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-clear-pattern:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        .btn-save-security {
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            margin-top: 8px;
            box-sizing: border-box;
        }

        .btn-save-security:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-save-security:disabled {
            background-color: #94a3b8;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        /* MOBILE SIDEBAR CONTROLS */
        .mobile-toggle {
            display: none;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 999;
            background: #2563eb; 
            color: white; 
            border: none; 
            padding: 8px 12px; 
            border-radius: 6px;
            font-size: 1.1rem;
            cursor: pointer;
            line-height: 1;
            align-self: flex-start;
        }

        .mobile-toggle.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .sidebar-close {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: -260px;
                transition: 0.3s;
                z-index: 1050;
            }

            .sidebar.active {
                left: 0;
            }

            .mobile-toggle {
                display: block;
                margin-bottom: 20px;
            }

            main.content {
                margin-left: 0 !important;
                width: 100%;
                padding: 16px;
            }

            #sidebar.active .sidebar-close {
                display: block;
            }

            .settings-card {
                padding: 20px;
            }
        }

        #sidebar:not(.active)~main.content {
            margin-left: 0;
            width: 100%;
        }
    </style>
</head>

<body>

    <div class="dashboard-wrapper">
        <!-- SIDEBAR NAVIGATION -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="<?php echo htmlspecialchars($dashboard_url); ?>">My Dashboard</a></li>
                <?php if ($user_role === 'student'): ?>
                    <li><a href="../student/student_announcements.php">Announcements</a></li>
                    <li><a href="../student/student_grades.php">My Grades</a></li>
                    <li><a href="../student/student_schedule.php">Class Schedule</a></li>
                    <li><a href="../student/student_profile.php">Account Settings</a></li>
                <?php endif; ?>
                <li><a href="settings.php" class="active">Security Settings</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <!-- MAIN CONTENT AREA -->
        <main class="content">
            <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>

            <div class="settings-card">
                <div class="settings-header">
                    <div class="badge-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3>Account Security Settings</h3>
                    <p>Configure secondary authentication for two-step verification and inactivity auto-locking.</p>
                </div>

                <form id="securitySetupForm" onsubmit="return false;">
                    <div class="form-group">
                        <label for="securityPrefSelect">Select Secondary Security Mode:</label>
                        <select id="securityPrefSelect" name="security_preference" class="form-select">
                            <option value="none" <?php echo $current_pref === 'none' ? 'selected' : ''; ?>>None (Disabled)</option>
                            <option value="pin" <?php echo $current_pref === 'pin' ? 'selected' : ''; ?>>PIN Code</option>
                            <option value="pattern" <?php echo $current_pref === 'pattern' ? 'selected' : ''; ?>>Pattern Code</option>
                            <option value="biometric" <?php echo $current_pref === 'biometric' ? 'selected' : ''; ?>>Fingerprint Biometric</option>
                        </select>
                    </div>

                    <div id="pinSetupGroup" class="form-group" style="display: <?php echo $current_pref === 'pin' ? 'block' : 'none'; ?>;">
                        <label for="newPinInput">Set 4 to 6 Digit PIN:</label>
                        <input type="password" id="newPinInput" class="form-input pin-input" maxlength="6" placeholder="••••" pattern="[0-9]*" inputmode="numeric">
                    </div>

                    <div id="patternSetupGroup" class="form-group" style="display: <?php echo $current_pref === 'pattern' ? 'block' : 'none'; ?>;">
                        <label>Draw Pattern Code (Connect at least 4 dots):</label>
                        <div class="pattern-wrapper">
                            <canvas id="patternCanvas" width="280" height="280"></canvas>
                            <div id="patternStatus" class="pattern-status">Draw your pattern</div>
                            <button type="button" class="btn-clear-pattern" onclick="resetPatternCanvas()">Clear Canvas</button>
                        </div>
                        <input type="hidden" id="newPatternInput" value="">
                    </div>

                    <div>
                        <button type="button" id="btnSaveSecurity" class="btn-save-security">
                            Save Security Preferences
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const burger = document.getElementById('mobileBurger');
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                if (burger) burger.style.display = 'none';
            } else {
                if (burger) burger.style.display = 'block';
            }
        }
    </script>
    <script src="../../assets/js/security-lock.js?v=<?php echo time(); ?>"></script>

    <script>
        let patternSequence = [];
        let isDrawing = false;
        const dots = [];
        let canvas, ctx;

        function initPatternCanvas() {
            canvas = document.getElementById('patternCanvas');
            if (!canvas) return;
            ctx = canvas.getContext('2d');

            // Fixed internal dimensions
            canvas.width = 280;
            canvas.height = 280;

            dots.length = 0;
            const radius = 16;
            const spacing = 70;
            const offset = 70;

            for (let row = 0; row < 3; row++) {
                for (let col = 0; col < 3; col++) {
                    const id = row * 3 + col + 1;
                    dots.push({ id, x: offset + col * spacing, y: offset + row * spacing, radius });
                }
            }

            drawPatternGrid();

            // Unified Pointer Events (Mouse, Touch, Stylus)
            canvas.onpointerdown = (e) => {
                canvas.setPointerCapture(e.pointerId);
                isDrawing = true;
                patternSequence = [];
                document.getElementById('newPatternInput').value = '';
                checkHit(e);
                drawPatternGrid(getPos(e));
            };

            canvas.onpointermove = (e) => {
                if (!isDrawing) return;
                checkHit(e);
                drawPatternGrid(getPos(e));
            };

            canvas.onpointerup = (e) => {
                if (!isDrawing) return;
                isDrawing = false;
                canvas.releasePointerCapture(e.pointerId);
                drawPatternGrid();

                if (patternSequence.length > 0) {
                    const patternStr = patternSequence.join('-');
                    document.getElementById('newPatternInput').value = patternStr;
                    const status = document.getElementById('patternStatus');
                    
                    if (patternSequence.length < 4) {
                        status.textContent = 'Pattern too short (minimum 4 dots)';
                        status.style.color = '#ef4444';
                    } else {
                        status.textContent = `Pattern Recorded (${patternSequence.length} dots connected)`;
                        status.style.color = '#10b981';
                    }
                }
            };
        }

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: (e.clientX - rect.left) * (canvas.width / rect.width),
                y: (e.clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        function checkHit(e) {
            const pos = getPos(e);
            dots.forEach(dot => {
                const dist = Math.hypot(dot.x - pos.x, dot.y - pos.y);
                if (dist < 38 && !patternSequence.includes(dot.id)) {
                    patternSequence.push(dot.id);
                }
            });
        }

        function drawPatternGrid(currentPos = null) {
            if (!ctx) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (patternSequence.length > 0) {
                ctx.beginPath();
                ctx.strokeStyle = '#2563eb';
                ctx.lineWidth = 6;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                
                for (let i = 0; i < patternSequence.length; i++) {
                    const dot = dots.find(d => d.id === patternSequence[i]);
                    if (i === 0) ctx.moveTo(dot.x, dot.y);
                    else ctx.lineTo(dot.x, dot.y);
                }

                if (currentPos && isDrawing) {
                    ctx.lineTo(currentPos.x, currentPos.y);
                }
                ctx.stroke();
            }

            dots.forEach(dot => {
                const isSelected = patternSequence.includes(dot.id);
                ctx.beginPath();
                ctx.arc(dot.x, dot.y, dot.radius, 0, Math.PI * 2);
                ctx.fillStyle = isSelected ? '#2563eb' : '#cbd5e1';
                ctx.fill();

                if (isSelected) {
                    ctx.beginPath();
                    ctx.arc(dot.x, dot.y, dot.radius + 8, 0, Math.PI * 2);
                    ctx.strokeStyle = 'rgba(37, 99, 235, 0.3)';
                    ctx.lineWidth = 4;
                    ctx.stroke();
                }
            });
        }

        function resetPatternCanvas() {
            patternSequence = [];
            document.getElementById('newPatternInput').value = '';
            document.getElementById('patternStatus').textContent = 'Draw your pattern';
            document.getElementById('patternStatus').style.color = '#2563eb';
            drawPatternGrid();
        }

        document.addEventListener("DOMContentLoaded", () => {
            const prefSelect = document.getElementById('securityPrefSelect');
            const saveBtn = document.getElementById('btnSaveSecurity');

            if (prefSelect) {
                prefSelect.addEventListener('change', () => {
                    const pref = prefSelect.value;
                    document.getElementById('pinSetupGroup').style.display = (pref === 'pin') ? 'block' : 'none';
                    
                    const patternGroup = document.getElementById('patternSetupGroup');
                    patternGroup.style.display = (pref === 'pattern') ? 'block' : 'none';
                    
                    if (pref === 'pattern') {
                        setTimeout(initPatternCanvas, 100);
                    }
                });

                if (prefSelect.value === 'pattern') {
                    setTimeout(initPatternCanvas, 100);
                }
            }

            if (saveBtn) {
                saveBtn.onclick = async (e) => {
                    e.preventDefault();

                    if (saveBtn.dataset.submitting === "true") return;
                    saveBtn.dataset.submitting = "true";
                    saveBtn.disabled = true;

                    const prefSelect = document.getElementById('securityPrefSelect');
                    if (!prefSelect) return;

                    const pref = prefSelect.value;
                    const pin = document.getElementById('newPinInput')?.value.trim() || '';
                    const pattern = document.getElementById('newPatternInput')?.value.trim() || '';

                    if (pref === 'pin' && (pin.length < 4 || pin.length > 6)) {
                        alert("Please enter a 4 to 6 digit numeric PIN.");
                        saveBtn.disabled = false;
                        delete saveBtn.dataset.submitting;
                        return;
                    }

                    if (pref === 'pattern' && (pattern === '' || pattern.split('-').length < 4)) {
                        alert("Please draw a pattern connecting at least 4 dots.");
                        saveBtn.disabled = false;
                        delete saveBtn.dataset.submitting;
                        return;
                    }

                    if (pref === 'biometric') {
                        if (typeof registerFingerprint === 'function') {
                            await registerFingerprint();
                        } else {
                            alert("Biometric function not loaded.");
                        }
                        saveBtn.disabled = false;
                        delete saveBtn.dataset.submitting;
                        return;
                    }

                    const formData = new FormData();
                    formData.append('security_preference', pref);
                    formData.append('pin', pin);
                    formData.append('pattern', pattern);

                    try {
                        const res = await fetch('save-security-preference.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        });

                        const data = await res.json();
                        
                        if (data.success) {
                            alert(data.message || "Security preferences updated successfully.");
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            } else {
                                location.reload();
                            }
                        } else {
                            alert(data.message || "Failed to update security preferences.");
                            saveBtn.disabled = false;
                            delete saveBtn.dataset.submitting;
                        }
                    } catch (err) {
                        console.error("Error saving security preference:", err);
                        alert("An error occurred while saving your preferences: " + err.message);
                        saveBtn.disabled = false;
                        delete saveBtn.dataset.submitting;
                    }
                };
            }
        });
    </script>
</body>
</html>