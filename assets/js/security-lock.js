// assets/js/security-lock.js

// Dynamic base path auto-detection for modules/auth/ endpoints
const BASE_AUTH_URL = window.location.pathname.includes('/modules/auth/') 
    ? '' 
    : '/modules/auth/';

// Idle Timeout Configuration (5 minutes = 300,000 milliseconds)
const IDLE_TIMEOUT_MS = 5 * 60 * 1000; 
let idleTimer = null;

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
// INITIALIZATION LISTENER & SAFEGUARD
// -------------------------------------------------------------
function runInitSequence() {
    if (!window.location.pathname.includes('2fa-challenge.php') && 
        !window.location.pathname.includes('login.php')) {
        initIdleTimer();
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
            console.log("Inactivity limit reached. Redirecting to 2FA Challenge...");
            await lockSessionAndRedirect();
        }, IDLE_TIMEOUT_MS);
    };

    activityEvents.forEach(eventType => {
        window.addEventListener(eventType, resetTimer, { passive: true });
    });

    resetTimer();
}

async function lockSessionAndRedirect() {
    try {
        const response = await fetch(`${BASE_AUTH_URL}lock-session.php`, {
            method: 'POST',
            credentials: 'same-origin'
        });

        const data = await response.json();
        if (data.success) {
            window.location.href = `${BASE_AUTH_URL}2fa-challenge.php`;
        }
    } catch (err) {
        console.error("Failed to lock session on inactivity:", err);
    }
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
        const res = await fetch(`${BASE_AUTH_URL}register-biometric.php?action=get_options`, {
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

        const saveRes = await fetch(`${BASE_AUTH_URL}register-biometric.php`, {
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
        // Silently ignore user cancelation or timeout errors
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
        const response = await fetch(`${BASE_AUTH_URL}get-biometric-challenge.php`, {
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

        const verifyRes = await fetch(`${BASE_AUTH_URL}verify-biometric.php`, {
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
        // Silently handle user cancelation
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