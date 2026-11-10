        </main>
    </div>
    <script src="../../assets/js/admin-form-confirmation.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const burger = document.getElementById('mobileBurger');
            const isOpen = sidebar.classList.toggle('active');

            burger.classList.toggle('hidden', isOpen);
            burger.setAttribute('aria-expanded', String(isOpen));
        }

        document.querySelectorAll('[data-dialog-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = document.getElementById(button.dataset.dialogTarget);
                if (dialog instanceof HTMLDialogElement) {
                    dialog.showModal();
                }
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog')?.close());
        });

        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const passwordInput = document.getElementById(button.getAttribute('aria-controls'));
                if (!(passwordInput instanceof HTMLInputElement)) {
                    return;
                }

                const isVisible = passwordInput.type === 'text';
                passwordInput.type = isVisible ? 'password' : 'text';
                button.textContent = isVisible ? 'Show password' : 'Hide password';
                button.setAttribute('aria-pressed', String(!isVisible));
            });
        });
    </script>
</body>
</html>
