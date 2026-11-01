document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const alertBox = document.getElementById('alertBox');
    const submitBtn = document.getElementById('submitBtn');

    // Modals
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const resetModal = document.getElementById('resetModal');
    const closeResetBtn = document.getElementById('closeResetBtn');
    const submitResetBtn = document.getElementById('submitResetBtn');
    const resetUsernameInput = document.getElementById('resetUsername');
    const resetAlert = document.getElementById('resetAlert');

    // 1. Toggle Password Visibility
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            togglePasswordBtn.textContent = isPassword ? 'Hide' : 'Show';
        });
    }

    // 2. AJAX Login Submission
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();

            const username = usernameInput.value.trim();
            const password = passwordInput.value;

            if (!username || !password) {
                showAlert('Please fill in all fields.', 'danger');
                return;
            }

            setLoading(true);

            try {
                const formData = new FormData(loginForm);
                
                // POST directly to the current page URL to prevent path errors
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    showAlert('Authentication successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 600);
                } else {
                    showAlert(data.message || 'Invalid credentials.', 'danger');
                    setLoading(false);
                }
            } catch (err) {
                showAlert('Server error. Please try again.', 'danger');
                setLoading(false);
            }
        });
    }

    // 3. Reset Password Modal Handling
    if (forgotPasswordLink && resetModal) {
        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            hideResetAlert();
            resetModal.classList.remove('hidden');
        });
    }

    if (closeResetBtn) {
        closeResetBtn.addEventListener('click', () => {
            hideResetAlert();
            resetModal.classList.add('hidden');
        });
    }

    if (submitResetBtn) {
        submitResetBtn.addEventListener('click', async () => {
            const resetUser = resetUsernameInput.value.trim();
            if (!resetUser) {
                showResetAlert('Please enter your Username or LRN.', 'danger');
                return;
            }

            submitResetBtn.disabled = true;
            submitResetBtn.textContent = 'Submitting...';
            hideResetAlert();

            try {
                const response = await fetch('request_password_reset.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ username: resetUser })
                });
                const data = await response.json();

                if (!response.ok || data.status !== 'success') {
                    showResetAlert(data.message || 'Unable to submit your request. Please try again.', 'danger');
                    return;
                }

                resetModal.classList.add('hidden');
                resetUsernameInput.value = '';
                hideAlert();
                showAlert(data.message, 'success');
            } catch (err) {
                showResetAlert('Unable to submit your request. Please try again.', 'danger');
            } finally {
                submitResetBtn.disabled = false;
                submitResetBtn.textContent = 'Notify Admin';
            }
        });
    }

    // Helpers
    function showAlert(msg, type) {
        alertBox.className = `alert-box alert-${type}`;
        alertBox.textContent = msg;
        alertBox.classList.remove('hidden');
    }

    function hideAlert() {
        alertBox.classList.add('hidden');
        alertBox.textContent = '';
    }

    function showResetAlert(msg, type) {
        resetAlert.className = `alert-box alert-${type}`;
        resetAlert.textContent = msg;
        resetAlert.classList.remove('hidden');
    }

    function hideResetAlert() {
        resetAlert.classList.add('hidden');
        resetAlert.textContent = '';
    }

    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        submitBtn.textContent = isLoading ? 'Authenticating...' : 'Secure Login';
    }
});

// Custom PWA Installation Logic
let deferredPrompt = null;
const installBtn = document.getElementById('pwaInstallBtn');

// Capture the browser's install event
window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent browser's automatic mini-infobar
    e.preventDefault();
    
    // Store the event for later activation
    deferredPrompt = e;

    // Unhide the custom install button on index.php
    if (installBtn) {
        installBtn.classList.remove('hidden');
        installBtn.style.display = 'inline-block'; // Explicit fallback in case CSS .hidden is missing
    }
});

// Add click listener directly to the button
if (installBtn) {
    installBtn.addEventListener('click', async () => {
        if (!deferredPrompt) {
            console.warn('Install prompt is not available yet or already used.');
            return;
        }

        // Trigger native installation dialog
        deferredPrompt.prompt();

        // Wait for the user to accept or dismiss
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`User installation choice: ${outcome}`);

        // Reset prompt variable and hide button
        deferredPrompt = null;
        installBtn.classList.add('hidden');
        installBtn.style.display = 'none';
    });
}

// Hide button once app is installed
window.addEventListener('appinstalled', () => {
    if (installBtn) {
        installBtn.classList.add('hidden');
        installBtn.style.display = 'none';
    }
    deferredPrompt = null;
    console.log('STR-SYNC PWA installed successfully!');
});