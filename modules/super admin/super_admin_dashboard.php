<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../../manifest/login.php');
    exit();
}

$conn = getDBConnection();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? 'create';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        header('Location: super_admin_dashboard.php?status=invalid_request');
        exit();
    }

    if ($action === 'delete') {
        $adminId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$adminId) {
            header('Location: super_admin_dashboard.php?status=admin_not_found');
            exit();
        }

        try {
            $conn->beginTransaction();
            $adminStatement = $conn->prepare(
                "SELECT username FROM users WHERE id = :id AND role = 'admin' FOR UPDATE"
            );
            $adminStatement->execute(['id' => $adminId]);
            $admin = $adminStatement->fetch();
            if (!$admin) {
                $conn->rollBack();
                header('Location: super_admin_dashboard.php?status=admin_not_found');
                exit();
            }

            $deleteStatement = $conn->prepare("DELETE FROM users WHERE id = :id AND role = 'admin'");
            $deleteStatement->execute(['id' => $adminId]);

            $logStatement = $conn->prepare(
                "INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
                 VALUES (:user_id, :action, 'users', :ip_address)"
            );
            $logStatement->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => "Deleted admin account: {$admin['username']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $conn->commit();
            header('Location: super_admin_dashboard.php?status=deleted');
            exit();
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Super admin account deletion failed: ' . $e->getMessage());
            header('Location: super_admin_dashboard.php?status=delete_failed');
            exit();
        }
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $adminId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);

    if (
        $fullName === '' ||
        strlen($fullName) > 100 ||
        $username === '' ||
        strlen($username) > 50 ||
        ($email !== '' && (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)))
    ) {
        header('Location: super_admin_dashboard.php?status=invalid_fields');
        exit();
    }

    if ($action === 'edit' && (!$adminId || ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)))) {
        header('Location: super_admin_dashboard.php?status=invalid_fields');
        exit();
    }

    if ($action === 'create' && ($password === '' || strlen($password) < 8 || strlen($password) > 72)) {
        header('Location: super_admin_dashboard.php?status=invalid_fields');
        exit();
    }

    if (!in_array($action, ['create', 'edit'], true)) {
        header('Location: super_admin_dashboard.php?status=invalid_request');
        exit();
    }

    try {
        $checkStatement = $action === 'edit'
            ? $conn->prepare('SELECT id FROM users WHERE username = :username AND id <> :admin_id LIMIT 1')
            : $conn->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $checkParameters = ['username' => $username];
        if ($action === 'edit') {
            $checkParameters['admin_id'] = $adminId;
        }
        $checkStatement->execute($checkParameters);
        if ($checkStatement->fetch()) {
            header('Location: super_admin_dashboard.php?status=username_exists');
            exit();
        }

        $conn->beginTransaction();
        if ($action === 'create') {
            $createStatement = $conn->prepare(
                "INSERT INTO users (lrn, username, password, full_name, email, role, section_id, require_reset)
                 VALUES (NULL, :username, :password, :full_name, :email, 'admin', NULL, 1)"
            );
            $createStatement->execute([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
            ]);

            $newAdminId = $conn->lastInsertId();
            $profileStatement = $conn->prepare(
                "INSERT INTO student_profiles (user_id, gender) VALUES (:user_id, 'N/A')"
            );
            $profileStatement->execute(['user_id' => $newAdminId]);
            $successStatus = 'created';
            $logAction = "Created admin account: {$username}";
        } else {
            $adminStatement = $conn->prepare(
                "SELECT username FROM users WHERE id = :id AND role = 'admin' FOR UPDATE"
            );
            $adminStatement->execute(['id' => $adminId]);
            $existingAdmin = $adminStatement->fetch();
            if (!$existingAdmin) {
                $conn->rollBack();
                header('Location: super_admin_dashboard.php?status=admin_not_found');
                exit();
            }

            if ($password !== '') {
                $updateStatement = $conn->prepare(
                    "UPDATE users SET username = :username, full_name = :full_name, email = :email,
                     password = :password, require_reset = 1 WHERE id = :id AND role = 'admin'"
                );
                $updateStatement->execute([
                    'username' => $username,
                    'full_name' => $fullName,
                    'email' => $email !== '' ? $email : null,
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'id' => $adminId,
                ]);
            } else {
                $updateStatement = $conn->prepare(
                    "UPDATE users SET username = :username, full_name = :full_name, email = :email
                     WHERE id = :id AND role = 'admin'"
                );
                $updateStatement->execute([
                    'username' => $username,
                    'full_name' => $fullName,
                    'email' => $email !== '' ? $email : null,
                    'id' => $adminId,
                ]);
            }

            $successStatus = 'updated';
            $logAction = "Updated admin account: {$existingAdmin['username']} to {$username}";
        }

        $logStatement = $conn->prepare(
            "INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
             VALUES (:user_id, :action, 'users', :ip_address)"
        );
        $logStatement->execute([
            'user_id' => $_SESSION['user_id'],
            'action' => $logAction,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $conn->commit();
        header("Location: super_admin_dashboard.php?status={$successStatus}");
        exit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Super admin account save failed: ' . $e->getMessage());
        header('Location: super_admin_dashboard.php?status=save_failed');
        exit();
    }
}

$adminsStatement = $conn->query(
    "SELECT id, full_name, username, email
     FROM users
     WHERE role = 'admin'
     ORDER BY full_name, username"
);
$admins = $adminsStatement->fetchAll();

$statusMessages = [
    'created' => ['success', 'Admin account created successfully.'],
    'updated' => ['success', 'Admin account updated successfully.'],
    'deleted' => ['success', 'Admin account deleted successfully.'],
    'username_exists' => ['error', 'That username is already in use.'],
    'invalid_fields' => ['error', 'Check the name, username, email, and password fields. Passwords must be between 8 and 72 characters.'],
    'invalid_request' => ['error', 'Your request could not be verified. Please try again.'],
    'admin_not_found' => ['error', 'That admin account no longer exists.'],
    'save_failed' => ['error', 'The admin account could not be saved. Please try again.'],
    'delete_failed' => ['error', 'The admin account could not be deleted. Please try again.'],
];
$status = $_GET['status'] ?? '';
$notice = $statusMessages[$status] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .content {
            width: 100%;
            max-width: 1100px;
            min-width: 0;
            margin: 0 auto;
        }
        .page-header { 
            margin-bottom: 24px; 
        }
        .page-header h1 { 
            color: #0f172a; 
            margin-bottom: 4px; 
        }
        .page-header p { 
            color: #64748b; 
        }
        .management-grid {
            display: grid; 
            grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.5fr);
            gap: 24px; 
            align-items: start; 
        }
        .panel {
            min-width: 0; 
            padding: 1.5rem;
        }
        .panel h2 { color: #0f172a; font-size: 1.15rem; margin-bottom: 18px; }
        .form-field { margin-bottom: 16px; }
        .form-field label { display: block; font-weight: 600; margin-bottom: 6px; }
        .create-button { width: 100%; }
        .notice { margin-bottom: 20px; }
        .admin-table-wrap { width: 100%; min-width: 0; overflow-x: auto; }
        .admin-table-wrap table { min-width: 0; }
        .admin-table-wrap th, .admin-table-wrap td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .admin-table-wrap th { color: #475569; font-size: 0.85rem; }
        .delete-button { margin-top: 8px; }
        .edit-dialog {
            position: fixed;
            inset: auto;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(480px, calc(100vw - 32px));
            max-width: 100%;
            max-height: min(720px, calc(100vh - 32px));
            overflow-y: auto;
            margin: 0;
            border: 0;
            border-radius: 1rem;
            color: var(--text-main);
            background: var(--white);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        @supports (height: 100dvh) {
            .edit-dialog { max-height: min(720px, calc(100dvh - 32px)); }
        }
        .edit-dialog::backdrop { background: rgb(15 23 42 / 0.58); }
        .edit-dialog-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 18px; }
        .edit-dialog-header h2 { margin: 0; color: #0f172a; font-size: 1.2rem; }
        .close-dialog-button { flex: 0 0 auto; width: 36px; height: 36px; border: 0; border-radius: 8px; background: #f1f5f9; color: #334155; font-size: 1.3rem; cursor: pointer; }
        .dialog-actions { display: flex; gap: 10px; margin-top: 20px; }
        .dialog-actions .create-button { flex: 1; }
        .cancel-dialog-button { padding: 0.6rem 1.2rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; background: var(--white); color: var(--text-main); font: inherit; font-weight: 600; cursor: pointer; }
        .logout-link { display: inline-block; margin-top: 24px; padding: 10px 14px; border: 0; border-radius: 8px; background: #fee2e2; color: #b91c1c; font: inherit; font-weight: 600; text-decoration: none; }
        .empty-state { color: #64748b; padding: 16px 0; }
        @media (max-width: 900px) {
            .management-grid { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 640px) {
            .content { padding: 16px; }
            .page-header { margin-bottom: 18px; }
            .page-header h1 { font-size: 1.5rem; }
            .page-header p { font-size: 0.95rem; }
            .management-grid { gap: 16px; }
            .panel { padding: 18px; }
            .admin-table-wrap { overflow: visible; }
            .admin-table-wrap table,
            .admin-table-wrap tbody { display: block; width: 100%; }
            .admin-table-wrap table { min-width: 0; }
            .admin-table-wrap thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
            .admin-table-wrap tr { display: block; margin-bottom: 14px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 10px; }
            .admin-table-wrap td { display: grid; grid-template-columns: minmax(82px, 0.45fr) minmax(0, 1fr); gap: 12px; padding: 10px 0; overflow-wrap: anywhere; border-bottom: 1px solid #f1f5f9; }
            .admin-table-wrap td::before { content: attr(data-label); color: #64748b; font-size: 0.8rem; font-weight: 700; }
            .admin-table-wrap td:last-child { display: block; border-bottom: 0; }
            .admin-table-wrap td:last-child::before { display: block; margin-bottom: 8px; }
            .btn-edit, .delete-button { min-height: 40px; }
            .delete-button { margin-top: 0; }
            .edit-dialog { padding: 20px; }
        }
        @media (max-width: 360px) {
            .content { padding: 12px; }
            .panel { padding: 14px; }
            .admin-table-wrap td { grid-template-columns: 1fr; gap: 4px; }
        }
    </style>
</head>
<body>
    <main class="content">
        <header class="page-header">
            <h1>Super Admin</h1>
            <p>Create, edit, or delete admin accounts and review the current administrators.</p>
        </header>

        <?php if ($notice): ?>
            <div class="alert alert-<?= htmlspecialchars($notice[0], ENT_QUOTES, 'UTF-8') ?> notice" role="status">
                <?= htmlspecialchars($notice[1], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="management-grid">
            <section class="table-container panel" aria-labelledby="create-admin-heading">
                <h2 id="create-admin-heading">Create Admin Account</h2>
                <form method="POST" action="super_admin_dashboard.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-field">
                        <label for="full_name">Full name</label>
                        <input type="text" id="full_name" name="full_name" maxlength="100" autocomplete="name" required>
                    </div>
                    <div class="form-field">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" maxlength="50" autocomplete="username" required>
                    </div>
                    <div class="form-field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        <button type="button" class="btn-view-profile" data-password-toggle aria-controls="password" aria-pressed="false">Show password</button>
                    </div>
                    <button type="submit" class="btn-primary create-button">Create Admin</button>
                </form>
            </section>

            <section class="table-container panel" aria-labelledby="admins-heading">
                <h2 id="admins-heading">Current Admin Accounts (<?= count($admins) ?>)</h2>
                <?php if ($admins): ?>
                    <div class="admin-table-wrap">
                        <table>
                            <thead>
                                <tr><th>Full name</th><th>Username</th><th>Email</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td data-label="Full name"><?= htmlspecialchars($admin['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Username"><?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Email"><?= htmlspecialchars($admin['email'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Actions">
                                            <button type="button" class="btn-edit" data-dialog-target="edit-admin-<?= (int) $admin['id'] ?>">Edit</button>
                                            <dialog class="edit-dialog modal-content" id="edit-admin-<?= (int) $admin['id'] ?>" aria-labelledby="edit-admin-heading-<?= (int) $admin['id'] ?>">
                                                <div class="edit-dialog-header">
                                                    <h2 id="edit-admin-heading-<?= (int) $admin['id'] ?>">Edit Admin Account</h2>
                                                    <button type="button" class="close-dialog-button" data-close-dialog aria-label="Close edit window">&times;</button>
                                                </div>
                                                <form method="POST" action="super_admin_dashboard.php">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>">
                                                    <div class="form-field">
                                                        <label for="full_name_<?= (int) $admin['id'] ?>">Full name</label>
                                                        <input type="text" id="full_name_<?= (int) $admin['id'] ?>" name="full_name" maxlength="100" value="<?= htmlspecialchars($admin['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                                                    </div>
                                                    <div class="form-field">
                                                        <label for="username_<?= (int) $admin['id'] ?>">Username</label>
                                                        <input type="text" id="username_<?= (int) $admin['id'] ?>" name="username" maxlength="50" value="<?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') ?>" required>
                                                    </div>
                                                    <div class="form-field">
                                                        <label for="email_<?= (int) $admin['id'] ?>">Email</label>
                                                        <input type="email" id="email_<?= (int) $admin['id'] ?>" name="email" maxlength="100" value="<?= htmlspecialchars($admin['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>
                                                    <div class="form-field">
                                                        <label for="password_<?= (int) $admin['id'] ?>">New password (optional)</label>
                                                        <input type="password" id="password_<?= (int) $admin['id'] ?>" name="password" minlength="8" maxlength="72" autocomplete="new-password">
                                                        <button type="button" class="btn-view-profile" data-password-toggle aria-controls="password_<?= (int) $admin['id'] ?>" aria-pressed="false">Show password</button>
                                                    </div>
                                                    <div class="dialog-actions">
                                                        <button type="button" class="cancel-dialog-button" data-close-dialog>Cancel</button>
                                                        <button type="submit" class="btn-primary create-button">Save Changes</button>
                                                    </div>
                                                </form>
                                            </dialog>
                                            <form method="POST" action="super_admin_dashboard.php" onsubmit="return confirm('Delete this admin account? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>">
                                                <button type="submit" class="btn-delete delete-button">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="empty-state">There are no admin accounts yet.</p>
                <?php endif; ?>
            </section>
        </div>

        <a class="logout-link" href="../../manifest/logout.php">Log out</a>
    </main>
    <script>
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
