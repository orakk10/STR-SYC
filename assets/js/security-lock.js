// assets/js/security-lock.js

// Dynamic base path auto-detection for modules/auth/ endpoints
const BASE_AUTH_URL = window.location.pathname.includes('/modules/auth/') 
    ? '' 
    : '/str-syc/modules/auth/';

// Idle Timeout Configuration (5 minutes = 300,000 milliseconds)
const IDLE_TIMEOUT_MS = 5 * 60 * 1000; 
let idleTimer = null;
let isAppLocked = false;

document.addEventListener("DOMContentLoaded", () => {
    checkAppLockStatus();
    initIdleTimer();
});

document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
        checkAppLockStatus(true);
    }
});

// Helper function to safely decode Base64 and Base64URL strings
function base64ToUint8Array(base64String) {
    if (!base64String) return new Uint8Array(0);
    
    let padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    let base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');

    let rawData = window.atob(base64);
    let outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// -------------------------------------------------------------
// IDLE TIMEOUT LOGIC
// -------------------------------------------------------------
function initIdleTimer() {
    // List of events that signal user activity
    const activityEvents = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'];

    const resetTimer = () => {
        // Only reset timer if app is not currently locked
        if (isAppLocked) return;

        if (idleTimer) clearTimeout(idleTimer);
        
        idleTimer = setTimeout(() => {
            console.log("Inactivity limit reached. Locking application...");
            checkAppLockStatus();
        }, IDLE_TIMEOUT_MS);
    };

    // Attach passive activity listeners to window
    activityEvents.forEach(eventType => {
        window.addEventListener(eventType, resetTimer, { passive: true });
    });

    // Start initial timer
    resetTimer();
}

// -------------------------------------------------------------
// 1. Check User Security Preference & Display Lock
// -------------------------------------------------------------
async function checkAppLockStatus(isUserResume = false) {
    try {
        const response = await fetch(`${BASE_AUTH_URL}get-user-security-preference.php`);
        if (!response.ok) return;

        const data = await response.json();

        if (!data.success || data.security_preference === 'none' || !data.security_preference) {
            console.log("Security lock not configured for this user.");
            return;
        }

        switch (data.security_preference) {
            case 'biometric':
                showAppLockOverlay('biometric');
                triggerFingerprintScan(true);
                break;
            case 'pin':
                showAppLockOverlay('pin');
                break;
            case 'pattern':
                showAppLockOverlay('pattern');
                initUnlockPatternCanvas();
                break;
            default:
                break;
        }
    } catch (err) {
        console.error("Failed to fetch security preferences:", err);
    }
}

// -------------------------------------------------------------
// 2. WebAuthn Fingerprint Registration (Called from settings.php)
// -------------------------------------------------------------
async function registerFingerprint() {
    if (!window.PublicKeyCredential) {
        alert("WebAuthn API is not enabled on this connection. Please verify your connection context or HTTPS connection.");
        return;
    }

    try {
        const res = await fetch(`${BASE_AUTH_URL}register-biometric.php?action=get_options`);
        const options = await res.json();

        if (!options.success) {
            alert(options.message || "Failed to initiate biometric challenge.");
            return;
        }

        const challengeBuffer = base64ToUint8Array(options.challenge);
        const userIdBuffer = base64ToUint8Array(options.user.id);

        const credential = await navigator.credentials.create({
            publicKey: {
                challenge: challengeBuffer,
                rp: { name: "Strand-Sync App" },
                user: {
                    id: userIdBuffer,
                    name: options.user.name,
                    displayName: options.user.displayName
                },
                pubKeyCredParams: [
                    { alg: -7, type: "public-key" },
                    { alg: -257, type: "public-key" },
                    { alg: -37, type: "public-key" }
                ],
                authenticatorSelection: {
                    authenticatorAttachment: "platform",
                    userVerification: "preferred"
                },
                timeout: 60000
            }
        });

        const saveRes = await fetch(`${BASE_AUTH_URL}register-biometric.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: credential.id,
                rawId: arrayBufferToBase64(credential.rawId),
                type: credential.type
            })
        });

        const result = await saveRes.json();
        if (result.success) {
            alert(result.message || "Fingerprint registered successfully!");
            if (result.redirect) {
                window.location.href = result.redirect;
            } else {
                location.reload();
            }
        } else {
            alert(result.message || "Failed to save biometric credential.");
        }
    } catch (err) {
        console.error("Biometric registration error:", err);
        alert("Fingerprint Scan Error: " + err.message);
    }
}

// -------------------------------------------------------------
// 3. WebAuthn Fingerprint Authentication (Unlocking App)
// -------------------------------------------------------------
async function triggerFingerprintScan(isAutoPrompt = false) {
    if (!window.PublicKeyCredential) {
        if (!isAutoPrompt) alert("Fingerprint biometrics not supported on this device.");
        return;
    }

    try {
        const response = await fetch(`${BASE_AUTH_URL}get-biometric-challenge.php`);
        const options = await response.json();

        if (!options.success) {
            if (!isAutoPrompt) alert(options.message || "Could not retrieve biometric challenge.");
            return;
        }

        const challengeBuffer = base64ToUint8Array(options.challenge);

        const allowCredentials = [];
        if (options.credentialId) {
            const rawCredId = base64ToUint8Array(options.credentialId);
            allowCredentials.push({ id: rawCredId, type: 'public-key' });
        }

        const credential = await navigator.credentials.get({
            publicKey: {
                challenge: challengeBuffer,
                allowCredentials: allowCredentials,
                userVerification: "preferred",
                timeout: 60000
            }
        });

        const authPayload = {
            id: credential.id,
            rawId: arrayBufferToBase64(credential.rawId),
            response: {
                clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
                authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
                signature: arrayBufferToBase64(credential.response.signature),
                userHandle: credential.response.userHandle ? arrayBufferToBase64(credential.response.userHandle) : null
            },
            type: credential.type
        };

        const verifyRes = await fetch(`${BASE_AUTH_URL}verify-biometric.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(authPayload)
        });

        const result = await verifyRes.json();
        if (result.success) {
            unlockAppScreen();
        } else {
            if (!isAutoPrompt) alert(result.message || "Fingerprint authentication failed.");
        }
    } catch (err) {
        console.error("Biometric authentication error:", err);
        if (!isAutoPrompt && err.name !== "NotAllowedError") {
            alert("Biometric Error: " + err.message);
        }
    }
}

// -------------------------------------------------------------
// 4. Verify PIN or Pattern Credentials via Backend
// -------------------------------------------------------------
async function verifyPinOrPattern(inputVal, type = 'pin') {
    if (!inputVal || inputVal.trim() === '') {
        alert(`Please enter your ${type.toUpperCase()}.`);
        return;
    }

    const formData = new FormData();
    formData.append('type', type);
    if (type === 'pin') {
        formData.append('pin', inputVal.trim());
    } else {
        formData.append('pattern', inputVal.trim());
    }

    try {
        const response = await fetch(`${BASE_AUTH_URL}verify-pin.php`, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            unlockAppScreen();
        } else {
            alert(result.message || "Incorrect PIN or Pattern.");
            if (type === 'pattern') {
                resetUnlockPatternCanvas();
            }
        }
    } catch (err) {
        console.error("Verification error:", err);
        alert("An error occurred during verification.");
        if (type === 'pattern') {
            resetUnlockPatternCanvas();
        }
    }
}

// -------------------------------------------------------------
// 5. Interactive 3x3 Canvas Pattern Logic for Lock Overlay
// -------------------------------------------------------------
let unlockPatternSequence = [];
let isUnlockDrawing = false;
const unlockDots = [];
let unlockCanvas, unlockCtx;

function initUnlockPatternCanvas() {
    unlockCanvas = document.getElementById('unlockPatternCanvas');
    if (!unlockCanvas) return;
    unlockCtx = unlockCanvas.getContext('2d');

    unlockCanvas.width = 220;
    unlockCanvas.height = 220;

    unlockDots.length = 0;
    const radius = 12;
    const spacing = 55;
    const offset = 55;

    for (let row = 0; row < 3; row++) {
        for (let col = 0; col < 3; col++) {
            const id = row * 3 + col + 1;
            unlockDots.push({ id, x: offset + col * spacing, y: offset + row * spacing, radius });
        }
    }

    drawUnlockPatternGrid();

    unlockCanvas.onpointerdown = (e) => {
        unlockCanvas.setPointerCapture(e.pointerId);
        isUnlockDrawing = true;
        unlockPatternSequence = [];
        checkUnlockHit(e);
        drawUnlockPatternGrid(getUnlockPos(e));
    };

    unlockCanvas.onpointermove = (e) => {
        if (!isUnlockDrawing) return;
        checkUnlockHit(e);
        drawUnlockPatternGrid(getUnlockPos(e));
    };

    unlockCanvas.onpointerup = (e) => {
        if (!isUnlockDrawing) return;
        isUnlockDrawing = false;
        unlockCanvas.releasePointerCapture(e.pointerId);
        drawUnlockPatternGrid();

        if (unlockPatternSequence.length > 0) {
            const patternStr = unlockPatternSequence.join('-');
            verifyPinOrPattern(patternStr, 'pattern');
        }
    };
}

function getUnlockPos(e) {
    const rect = unlockCanvas.getBoundingClientRect();
    return {
        x: (e.clientX - rect.left) * (unlockCanvas.width / rect.width),
        y: (e.clientY - rect.top) * (unlockCanvas.height / rect.height)
    };
}

function checkUnlockHit(e) {
    const pos = getUnlockPos(e);
    unlockDots.forEach(dot => {
        const dist = Math.hypot(dot.x - pos.x, dot.y - pos.y);
        if (dist < 30 && !unlockPatternSequence.includes(dot.id)) {
            unlockPatternSequence.push(dot.id);
        }
    });
}

function drawUnlockPatternGrid(currentPos = null) {
    if (!unlockCtx) return;
    unlockCtx.clearRect(0, 0, unlockCanvas.width, unlockCanvas.height);

    if (unlockPatternSequence.length > 0) {
        unlockCtx.beginPath();
        unlockCtx.strokeStyle = '#0d6efd';
        unlockCtx.lineWidth = 5;
        unlockCtx.lineCap = 'round';
        unlockCtx.lineJoin = 'round';
        
        for (let i = 0; i < unlockPatternSequence.length; i++) {
            const dot = unlockDots.find(d => d.id === unlockPatternSequence[i]);
            if (i === 0) unlockCtx.moveTo(dot.x, dot.y);
            else unlockCtx.lineTo(dot.x, dot.y);
        }

        if (currentPos && isUnlockDrawing) {
            unlockCtx.lineTo(currentPos.x, currentPos.y);
        }
        unlockCtx.stroke();
    }

    unlockDots.forEach(dot => {
        const isSelected = unlockPatternSequence.includes(dot.id);
        unlockCtx.beginPath();
        unlockCtx.arc(dot.x, dot.y, dot.radius, 0, Math.PI * 2);
        unlockCtx.fillStyle = isSelected ? '#0d6efd' : '#cccccc';
        unlockCtx.fill();

        if (isSelected) {
            unlockCtx.beginPath();
            unlockCtx.arc(dot.x, dot.y, dot.radius + 6, 0, Math.PI * 2);
            unlockCtx.strokeStyle = 'rgba(13, 110, 253, 0.3)';
            unlockCtx.lineWidth = 3;
            unlockCtx.stroke();
        }
    });
}

function resetUnlockPatternCanvas() {
    unlockPatternSequence = [];
    drawUnlockPatternGrid();
}

// -------------------------------------------------------------
// 6. UI Helper Functions
// -------------------------------------------------------------
function showAppLockOverlay(mode = 'pin') {
    isAppLocked = true; // Set lock flag

    let overlay = document.getElementById('securityLockOverlay');
    if (!overlay) {
        overlay = createLockOverlayElement();
        document.body.appendChild(overlay);
    }

    overlay.querySelectorAll('.lock-section').forEach(sec => sec.style.display = 'none');
    const activeSection = overlay.querySelector(`.lock-section-${mode}`);
    if (activeSection) activeSection.style.display = 'block';

    overlay.classList.add('active');

    if (mode === 'pattern') {
        setTimeout(initUnlockPatternCanvas, 50);
    }
}

function unlockAppScreen() {
    isAppLocked = false; // Reset lock flag
    const overlay = document.getElementById('securityLockOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
    // Reset inactivity timer after unlocking
    if (idleTimer) clearTimeout(idleTimer);
    initIdleTimer();
}

function arrayBufferToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

// -------------------------------------------------------------
// 7. DOM Generator for Security Lock Overlay
// -------------------------------------------------------------
function createLockOverlayElement() {
    const div = document.createElement('div');
    div.id = 'securityLockOverlay';
    div.className = 'security-lock-overlay';
    div.innerHTML = `
        <div class="security-lock-box">
            <div class="lock-icon">🔒</div>
            <h3>App Locked</h3>
            <p>Please authenticate to access Strand-Sync.</p>
            
            <!-- Biometric Section -->
            <div class="lock-section lock-section-biometric" style="display: none;">
                <button type="button" class="btn-unlock-biometric" onclick="triggerFingerprintScan()">
                    Touch Fingerprint Sensor
                </button>
            </div>

            <!-- PIN Section -->
            <div class="lock-section lock-section-pin" style="display: none;">
                <input type="password" id="lockPinInput" maxlength="6" placeholder="Enter PIN" pattern="[0-9]*" inputmode="numeric">
                <button type="button" class="btn-unlock-submit" onclick="verifyPinOrPattern(document.getElementById('lockPinInput').value, 'pin')">
                    Unlock with PIN
                </button>
            </div>

            <!-- Pattern Section -->
            <div class="lock-section lock-section-pattern" style="display: none;">
                <p style="font-size: 0.85rem; color: #666; margin-bottom: 8px;">Draw pattern to unlock</p>
                <canvas id="unlockPatternCanvas" width="220" height="220" style="background:#f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; touch-action: none !important; cursor: pointer;"></canvas>
            </div>
        </div>
    `;
    return div;
}