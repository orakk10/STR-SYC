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
            resetModal.classList.remove('hidden');
        });
    }

    if (closeResetBtn) {
        closeResetBtn.addEventListener('click', () => {
            resetModal.classList.add('hidden');
        });
    }

    if (submitResetBtn) {
        submitResetBtn.addEventListener('click', () => {
            const resetUser = document.getElementById('resetUsername').value.trim();
            if (!resetUser) {
                alert('Please enter your Username or LRN.');
                return;
            }
            alert('Admin has been notified for account recovery.');
            resetModal.classList.add('hidden');
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

    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        submitBtn.textContent = isLoading ? 'Authenticating...' : 'Secure Login';
    }
});