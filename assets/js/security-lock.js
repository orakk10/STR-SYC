// assets/js/security-lock.js

// Dynamic path helper to locate modules/auth/ endpoints safely
function getAuthEndpoint(filename) {
    const pathSegments = window.location.pathname.split('/');
    const authIndex = pathSegments.indexOf('modules');
    
    if (authIndex !== -1) {
        const rootPath = pathSegments.slice(0, authIndex).join('/');
        return `${rootPath}/modules/auth/${filename}`;
    }
    return `/modules/auth/${filename}`;
}

// Idle Timeout Configuration (5 minutes = 300,000 milliseconds)
const IDLE_TIMEOUT_MS = 5 * 60 * 1000; 
let idleTimer = null;
let singleDeviceInterval = null;

// -------------------------------------------------------------
// UTILITY HELPERS
// -------------------------------------------------------------
function arrayBufferToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

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
// CENTERED FIXED SESSION EXPIRED MODAL
// -------------------------------------------------------------
function showSessionExpiredModal(customMessage = null) {
    if (document.getElementById('sessionExpiredOverlay')) return;

    // Remove old stylesheet if present to force style updates
    const oldStyle = document.getElementById('sessionOverlayStyles');
    if (oldStyle) oldStyle.remove();

    const style = document.createElement('style');
    style.id = 'sessionOverlayStyles';
    style.innerHTML = `
        .session-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            height: 100dvh; /* Dynamic Viewport Height for Mobile Browsers */
            background: rgba(15, 23, 42, 0.82);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999999;
            display: flex;
            align-items: center; /* Always centers vertically on all viewports */
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
            touch-action: none; /* Prevents background touch scrolling */
            animation: fadeInOverlay 0.25s ease-out forwards;
        }

        .session-modal-card {
            width: 100%;
            max-width: 400px;
            background: #ffffff;
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.35);
            text-align: center;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            animation: popInModal 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            box-sizing: border-box;
            position: relative;
        }

        .session-shield-badge {
            width: 56px;
            height: 56px;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 0 0 6px #f0f7ff;
        }

        .session-shield-badge svg {
            width: 26px;
            height: 26px;
        }

        .session-modal-card h3 {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .session-modal-card p {
            font-size: 0.88rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 12px;
            padding: 0 4px;
        }

        .session-error-code {
            font-size: 0.78rem;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 20px;
            letter-spacing: 0.02em;
        }

        .btn-session-login {
            width: 100%;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            transition: transform 0.15s ease, background-color 0.15s ease;
            outline: none;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-session-login:active {
            transform: scale(0.98);
        }

        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes popInModal {
            from { opacity: 0; transform: scale(0.92) translateY(12px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    `;
    document.head.appendChild(style);

    const messageText = customMessage || "Your session has expired. For your security, we need you to log in again to continue.";

    const overlay = document.createElement('div');
    overlay.id = 'sessionExpiredOverlay';
    overlay.className = 'session-overlay';
    overlay.innerHTML = `
        <div class="session-modal-card">
            <div class="session-shield-badge">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h3>Log in to continue</h3>
            <p>${messageText}</p>
            <div class="session-error-code">(Code: 3103401)</div>
            <button type="button" class="btn-session-login" onclick="redirectToLogin()">Log in</button>
        </div>
    `;

    document.body.appendChild(overlay);
}

function redirectToLogin() {
    const pathSegments = window.location.pathname.split('/');
    const modulesIndex = pathSegments.indexOf('modules');
    
    let rootPath = '';
    if (modulesIndex > 0) {
        rootPath = pathSegments.slice(0, modulesIndex).join('/');
    }
    
    window.location.href = `${window.location.origin}${rootPath}/manifest/login.php?timeout=1`;
}

// -------------------------------------------------------------
// INITIALIZATION LISTENER & SAFEGUARD
// -------------------------------------------------------------
function runInitSequence() {
    const currentPath = window.location.pathname;
    const isPublicPage = currentPath.includes('login.php') || currentPath.includes('2fa-challenge.php');

    if (!isPublicPage) {
        initIdleTimer();
        initSingleDeviceCheck();
    }

    if (document.getElementById('unlockPatternCanvas')) {
        initUnlockPatternCanvas();
    }
}

if (document.readyState === "complete" || document.readyState === "interactive") {
    setTimeout(runInitSequence, 50);
} else {
    document.addEventListener("DOMContentLoaded", runInitSequence);
}

// -------------------------------------------------------------
// IDLE TIMEOUT LOGIC
// -------------------------------------------------------------
function initIdleTimer() {
    const activityEvents = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'];

    const resetTimer = () => {
        if (idleTimer) clearTimeout(idleTimer);
        
        idleTimer = setTimeout(async () => {
            console.log("Inactivity limit reached. Prompting session expiration...");
            await lockSessionAndLogout();
        }, IDLE_TIMEOUT_MS);
    };

    activityEvents.forEach(eventType => {
        window.addEventListener(eventType, resetTimer, { passive: true });
    });

    resetTimer();
}

async function lockSessionAndLogout() {
    if (singleDeviceInterval) {
        clearInterval(singleDeviceInterval);
        singleDeviceInterval = null;
    }

    try {
        await fetch(getAuthEndpoint('force-logout.php'), {
            method: 'POST',
            credentials: 'same-origin'
        });
        showSessionExpiredModal();
    } catch (err) {
        console.error("Failed to expire session on inactivity:", err);
        showSessionExpiredModal();
    }
}

// -------------------------------------------------------------
// SINGLE DEVICE LOGIN CHECKER
// Checks if session ID matches database every 10 seconds
// -------------------------------------------------------------
function initSingleDeviceCheck() {
    if (singleDeviceInterval) clearInterval(singleDeviceInterval);

    singleDeviceInterval = setInterval(async () => {
        try {
            const endpoint = `${getAuthEndpoint('check-session-validity.php')}?_t=${Date.now()}`;
            
            const res = await fetch(endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'ngrok-skip-browser-warning': 'true',
                    'Cache-Control': 'no-cache'
                }
            });
            
            const data = await res.json();

            if (!data.valid) {
                // STOP POLLING immediately once session is invalidated
                clearInterval(singleDeviceInterval);
                singleDeviceInterval = null;

                console.warn("Session invalidated:", data.reason || 'device_mismatch');
                showSessionExpiredModal("You have been logged in from another device or location.");
            }
        } catch (err) {
            console.error("Single device check error:", err);
        }
    }, 10000);
}

// -------------------------------------------------------------
// WebAuthn Fingerprint Registration (Called from settings.php)
// -------------------------------------------------------------
async function registerFingerprint() {
    if (!window.PublicKeyCredential) {
        alert("WebAuthn API is not enabled on this connection. Please verify HTTPS or localhost connection.");
        return;
    }

    try {
        const res = await fetch(`${getAuthEndpoint('register-biometric.php')}?action=get_options`, {
            credentials: 'same-origin'
        });
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
                rp: { name: "STRAND-SYNC App" },
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

        const saveRes = await fetch(getAuthEndpoint('register-biometric.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
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
        if (err.name === "NotAllowedError" || err.name === "AbortError") {
            console.log("Biometric registration canceled by user.");
            return;
        }
        console.error("Biometric registration error:", err);
        alert("Fingerprint Scan Error: " + err.message);
    }
}

// -------------------------------------------------------------
// WebAuthn Fingerprint Authentication (Called on 2FA Challenge)
// -------------------------------------------------------------
async function triggerFingerprintScan(isAutoPrompt = false) {
    if (!window.PublicKeyCredential) {
        if (!isAutoPrompt) alert("Fingerprint biometrics not supported on this device.");
        return;
    }

    try {
        const response = await fetch(getAuthEndpoint('get-biometric-challenge.php'), {
            credentials: 'same-origin'
        });
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

        const verifyRes = await fetch(getAuthEndpoint('verify-biometric.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(authPayload)
        });

        const result = await verifyRes.json();
        if (result.success) {
            window.location.href = result.redirect || '../../index.php';
        } else {
            if (!isAutoPrompt) alert(result.message || "Fingerprint authentication failed.");
        }
    } catch (err) {
        if (err.name === "NotAllowedError" || err.name === "AbortError") {
            console.log("Biometric scan canceled by user.");
            return;
        }
        console.error("Biometric authentication error:", err);
        if (!isAutoPrompt) {
            alert("Biometric Error: " + err.message);
        }
    }
}

// -------------------------------------------------------------
// Interactive 3x3 Canvas Pattern Logic (For 2FA Challenge)
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
        unlockCtx.strokeStyle = '#2563eb';
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
        unlockCtx.fillStyle = isSelected ? '#2563eb' : '#cbd5e1';
        unlockCtx.fill();

        if (isSelected) {
            unlockCtx.beginPath();
            unlockCtx.arc(dot.x, dot.y, dot.radius + 6, 0, Math.PI * 2);
            unlockCtx.strokeStyle = 'rgba(37, 99, 235, 0.3)';
            unlockCtx.lineWidth = 3;
            unlockCtx.stroke();
        }
    });
}

function resetUnlockPatternCanvas() {
    unlockPatternSequence = [];
    drawUnlockPatternGrid();
}