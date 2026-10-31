<?php
// modules/auth/2fa-challenge.php
session_start();

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: ../../manifest/login.php");
    exit();
}

$sec_pref = $_SESSION['pending_security_pref'] ?? 'pin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Two-Step Verification | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/security-lock.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="../../assets/js/security-lock.js?v=<?php echo time(); ?>" defer></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --danger: #ef4444;
            --success: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.8)),
                        url('../../assets/img/school_image.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            user-select: none;
            -webkit-user-select: none;
        }

        .challenge-container {
            width: 100%;
            max-width: 420px;
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .challenge-header {
            padding: 32px 28px 16px;
            text-align: center;
        }

        .shield-badge {
            width: 64px;
            height: 64px;
            background: #eff6ff;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            box-shadow: 0 0 0 8px #f0f7ff;
        }

        .shield-badge svg {
            width: 32px;
            height: 32px;
        }

        .challenge-header h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .challenge-header p {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .challenge-body {
            padding: 0 28px 32px;
        }

        /* PIN UI */
        .pin-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .pin-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 1.25rem;
            text-align: center;
            letter-spacing: 0.3em;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            outline: none;
            transition: all 0.2s ease;
            background: #f8fafc;
            color: var(--text-main);
            font-weight: 700;
        }

        .pin-input:focus {
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }

        /* PATTERN UI */
        .pattern-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        #unlockPatternCanvas {
            background: #f8fafc;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            touch-action: none !important;
            cursor: pointer;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: border-color 0.2s;
            display: block;
        }

        #unlockPatternCanvas:hover {
            border-color: var(--primary);
        }

        .pattern-hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 10px;
            font-weight: 500;
        }

        .btn-clear-pattern {
            margin-top: 10px;
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

        /* CLEANED & UNIFIED BIOMETRIC UI */
        .biometric-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 0 8px;
        }

        .fingerprint-touch-zone {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #eff6ff;
            border: 2.5px solid #bfdbfe;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin: 0 auto 18px;
            outline: none;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            animation: bioPulse 2.5s infinite;
        }

        @keyframes bioPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.35);
            }
            70% {
                box-shadow: 0 0 0 20px rgba(37, 99, 235, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
            }
        }

        .fingerprint-touch-zone:hover, 
        .fingerprint-touch-zone:active {
            transform: scale(1.06);
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 12px 24px -6px rgba(37, 99, 235, 0.45);
        }

        .fingerprint-touch-zone svg {
            width: 60px;
            height: 60px;
            pointer-events: none;
            transition: stroke 0.2s ease;
        }

        .biometric-status-text {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-muted);
            text-align: center;
            min-height: 24px;
        }

        /* BUTTONS */
        .btn-primary-action {
            width: 100%;
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        .btn-primary-action:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-primary-action:active {
            transform: translateY(0);
        }

        .challenge-footer {
            padding: 16px 28px 24px;
            border-top: 1px solid var(--border-color);
            background: #f8fafc;
            text-align: center;
        }

        .logout-link {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .logout-link:hover {
            color: var(--danger);
        }
    </style>
</head>
<body>

<div class="challenge-container">
    <div class="challenge-header">
        <div class="shield-badge">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
        </div>
        <h2>Two-Step Verification</h2>
        <p>Confirm your identity using your secondary security preference.</p>
    </div>

    <div class="challenge-body">
        <!-- PIN Mode -->
        <?php if ($sec_pref === 'pin'): ?>
            <div class="pin-wrapper">
                <input type="password" id="challengePinInput" maxlength="6" placeholder="••••" pattern="[0-9]*" inputmode="numeric" class="pin-input" autofocus>
                <button type="button" class="btn-primary-action" onclick="verifyOneTimeSecurity('pin')">Verify PIN Code</button>
            </div>
        <?php endif; ?>

        <!-- Pattern Mode -->
        <?php if ($sec_pref === 'pattern'): ?>
            <div class="pattern-wrapper">
                <canvas id="unlockPatternCanvas" width="220" height="220"></canvas>
                <span class="pattern-hint">Connect at least 4 dots to unlock</span>
                <button type="button" class="btn-clear-pattern" onclick="resetUnlockPatternCanvas()">Reset Pattern Canvas</button>
            </div>
        <?php endif; ?>

        <!-- Biometric Mode -->
        <?php if ($sec_pref === 'biometric'): ?>
            <div class="biometric-wrapper">
                <button type="button" class="fingerprint-touch-zone" onclick="startBiometricScan()" title="Touch to scan fingerprint">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"></path>
                        <path d="M14 13.12c0 2.38-.2 4.41-.44 5.88"></path>
                        <path d="M8.5 10.5A3.5 3.5 0 0 1 12 7c1.32 0 2.5.73 3.12 1.83"></path>
                        <path d="M10.1 6.13A5.5 5.5 0 0 1 12 5c2.28 0 4.29 1.39 5.15 3.4"></path>
                        <path d="M16 16.5c-.24 1.48-.56 2.65-.89 3.5"></path>
                        <path d="M6.88 8.88A7.5 7.5 0 0 1 12 3c3.22 0 6.06 2.03 7.12 4.96"></path>
                        <path d="M18.8 13.5c-.2 1.82-.6 3.73-1.05 5.5"></path>
                        <path d="M8.8 19.5c.2-.73.42-1.63.6-2.5"></path>
                    </svg>
                </button>
                <div id="biometricStatusText" class="biometric-status-text">Tap fingerprint sensor to unlock</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="challenge-footer">
        <a href="../../manifest/logout.php" class="logout-link">Cancel & Log Out</a>
    </div>
</div>

<script>
    function initPatternWithRetry(retries = 10) {
        if (typeof initUnlockPatternCanvas === 'function') {
            const canvas = document.getElementById('unlockPatternCanvas');
            if (canvas) {
                initUnlockPatternCanvas();
                
                if (!canvas.dataset.listenerAttached) {
                    canvas.dataset.listenerAttached = "true";
                    canvas.addEventListener('pointerup', () => {
                        setTimeout(() => {
                            if (typeof unlockPatternSequence !== 'undefined' && unlockPatternSequence.length >= 4) {
                                verifyOneTimeSecurity('pattern');
                            }
                        }, 80);
                    });
                }
                return;
            }
        }
        if (retries > 0) {
            setTimeout(() => initPatternWithRetry(retries - 1), 50);
        }
    }

    async function startBiometricScan() {
        const statusText = document.getElementById('biometricStatusText');
        if (statusText) {
            statusText.textContent = "Scanning biometric credential...";
            statusText.style.color = "var(--primary)";
        }

        if (typeof triggerFingerprintScan === 'function') {
            try {
                await triggerFingerprintScan();
            } catch (err) {
                if (statusText) {
                    statusText.textContent = "Scan canceled or failed. Tap icon to retry.";
                    statusText.style.color = "var(--danger)";
                }
            }
        } else {
            setTimeout(() => {
                if (typeof triggerFingerprintScan === 'function') {
                    triggerFingerprintScan();
                } else if (statusText) {
                    statusText.textContent = "Biometric module loading failed.";
                    statusText.style.color = "var(--danger)";
                }
            }, 300);
        }
    }

    function initBiometricWithRetry(retries = 10) {
        if (typeof triggerFingerprintScan === 'function') {
            triggerFingerprintScan(true);
            return;
        }
        if (retries > 0) {
            setTimeout(() => initBiometricWithRetry(retries - 1), 50);
        }
    }

    function setupChallengeUI() {
        const currentPref = "<?php echo htmlspecialchars($sec_pref); ?>";
        
        if (currentPref === "pattern") {
            initPatternWithRetry();
        } else if (currentPref === "biometric") {
            initBiometricWithRetry();
        }

        const pinInput = document.getElementById('challengePinInput');
        if (pinInput) {
            pinInput.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') verifyOneTimeSecurity('pin');
            });
        }
    }

    if (document.readyState === "complete" || document.readyState === "interactive") {
        setupChallengeUI();
    } else {
        document.addEventListener("DOMContentLoaded", setupChallengeUI);
    }
    window.addEventListener("load", setupChallengeUI);

    async function verifyOneTimeSecurity(type) {
        let value = '';
        if (type === 'pin') {
            value = document.getElementById('challengePinInput').value.trim();
        } else if (type === 'pattern') {
            value = typeof unlockPatternSequence !== 'undefined' ? unlockPatternSequence.join('-') : '';
        }

        if (!value) {
            alert(`Please enter your ${type.toUpperCase()}.`);
            return;
        }

        const payload = {
            type: type,
            value: value,
            pin: value,
            pattern: value
        };

        try {
            const response = await fetch('verify-2fa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });

            const res = await response.json();
            if (res.success) {
                window.location.href = res.redirect || '../../index.php';
            } else {
                alert(res.message || "Invalid security code.");
                if (type === 'pattern' && typeof resetUnlockPatternCanvas === 'function') {
                    resetUnlockPatternCanvas();
                }
            }
        } catch (err) {
            console.error("2FA Verification error:", err);
            alert("An error occurred during verification.");
        }
    }
</script>
</body>
</html>