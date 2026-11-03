// assets/js/session-overlay.js

// Dynamic path helper to locate login screen safely
function getAuthEndpoint(filename) {
    const pathParts = window.location.pathname.split('/');
    const authIndex = pathParts.indexOf('modules');
    
    if (authIndex !== -1) {
        const rootPath = pathParts.slice(0, authIndex).join('/');
        return `${rootPath}/modules/auth/${filename}`;
    }
    return `/modules/auth/${filename}`;
}

function showSessionExpiredModal(customMessage = null) {
    // Check if overlay already exists
    if (document.getElementById('sessionExpiredOverlay')) return;

    // Inject system-aligned CSS styles
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

    // Build modal markup
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