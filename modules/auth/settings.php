<?php
// modules/auth/settings.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$conn = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$current_pref = 'none';

try {
    $stmt = $conn->prepare("SELECT security_preference FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !empty($user['security_preference'])) {
        $current_pref = $user['security_preference'];
    }
} catch (Exception $e) {
    // Fallback
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Account Security Settings</title>
    
    <link rel="stylesheet" href="../../assets/css/security-lock.css">

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 15px;
            user-select: none;
            -webkit-user-select: none;
        }

        .security-settings-container {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            max-width: 480px;
            margin: 10px auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }

        .security-form-group { margin-bottom: 15px; }
        .security-form-group label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: #333333;
            margin-bottom: 6px;
        }

        .security-select, .security-input {
            width: 100%;
            padding: 10px 12px;
            font-size: 0.95rem;
            border: 1px solid #cccccc;
            border-radius: 6px;
            box-sizing: border-box;
            background-color: #ffffff;
        }

        .pattern-container { text-align: center; margin-top: 10px; }

        #patternCanvas {
            background: #ffffff;
            border: 2px solid #0d6efd;
            border-radius: 12px;
            touch-action: none !important;
            cursor: pointer;
            display: block;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .pattern-status {
            font-size: 0.85rem;
            color: #0d6efd;
            margin-top: 8px;
            font-weight: 600;
            min-height: 20px;
        }

        .btn-save-security {
            background-color: #0d6efd;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        .btn-save-security:disabled { background-color: #6c757d; }
    </style>
</head>
<body>

<div class="security-settings-container">
    <h3>Account Security Settings</h3>
    <p>Configure secondary authentication for app resume and inactivity locking.</p>

    <form id="securitySetupForm" onsubmit="return false;">
        <div class="security-form-group">
            <label for="securityPrefSelect">Select App Lock Mode:</label>
            <select id="securityPrefSelect" name="security_preference" class="security-select">
                <option value="none" <?= $current_pref === 'none' ? 'selected' : '' ?>>None (Disabled)</option>
                <option value="pin" <?= $current_pref === 'pin' ? 'selected' : '' ?>>PIN Code</option>
                <option value="pattern" <?= $current_pref === 'pattern' ? 'selected' : '' ?>>Pattern</option>
                <option value="biometric" <?= $current_pref === 'biometric' ? 'selected' : '' ?>>Fingerprint Biometric</option>
            </select>
        </div>

        <div id="pinSetupGroup" class="security-form-group" style="display: <?= $current_pref === 'pin' ? 'block' : 'none' ?>;">
            <label for="newPinInput">Set 4 to 6 Digit PIN:</label>
            <input type="password" id="newPinInput" class="security-input" maxlength="6" placeholder="Enter PIN (e.g. 1234)" pattern="[0-9]*" inputmode="numeric">
        </div>

        <div id="patternSetupGroup" class="security-form-group" style="display: <?= $current_pref === 'pattern' ? 'block' : 'none' ?>;">
            <label>Draw Pattern Code (Connect at least 4 dots):</label>
            <div class="pattern-container">
                <canvas id="patternCanvas" width="280" height="280"></canvas>
                <div id="patternStatus" class="pattern-status">Draw your pattern</div>
                <button type="button" onclick="resetPatternCanvas()" style="margin-top: 8px; background: none; border: none; color: #dc3545; font-size: 0.82rem; cursor: pointer; text-decoration: underline;">Clear Pattern</button>
            </div>
            <input type="hidden" id="newPatternInput" value="">
        </div>

        <div style="margin-top: 18px;">
            <button type="button" id="btnSaveSecurity" class="btn-save-security">
                Save Security Preference
            </button>
        </div>
    </form>
</div>

<script src="../../assets/js/security-lock.js" defer></script>

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

        // Pointer Events (Unified Mouse, Touch, & Stylus handling)
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
                    status.style.color = '#dc3545';
                } else {
                    status.textContent = `Pattern Recorded (${patternSequence.length} dots connected)`;
                    status.style.color = '#198754';
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
            ctx.strokeStyle = '#0d6efd';
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
            ctx.fillStyle = isSelected ? '#0d6efd' : '#cccccc';
            ctx.fill();

            if (isSelected) {
                ctx.beginPath();
                ctx.arc(dot.x, dot.y, dot.radius + 8, 0, Math.PI * 2);
                ctx.strokeStyle = 'rgba(13, 110, 253, 0.35)';
                ctx.lineWidth = 4;
                ctx.stroke();
            }
        });
    }

    function resetPatternCanvas() {
        patternSequence = [];
        document.getElementById('newPatternInput').value = '';
        document.getElementById('patternStatus').textContent = 'Draw your pattern';
        document.getElementById('patternStatus').style.color = '#0d6efd';
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
                        body: formData
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