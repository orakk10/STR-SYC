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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'create';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        header('Location: admin_management.php?status=invalid_request');
        exit();
    }

    if (in_array($action, ['archive', 'delete'], true)) {
        $adminId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$adminId || $adminId < 1) {
            header('Location: admin_management.php?status=admin_not_found');
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
                header('Location: admin_management.php?status=admin_not_found');
                exit();
            }

            $archiveStatement = $conn->prepare(
                "INSERT INTO account_archives (user_id, original_role, archived_by)
                 VALUES (:user_id, 'admin', :archived_by)"
            );
            $archiveStatement->execute([
                'user_id' => $adminId,
                'archived_by' => (int) $_SESSION['user_id'],
            ]);

            $archiveUserStatement = $conn->prepare(
                "UPDATE users SET role = 'archived' WHERE id = :id AND role = 'admin'"
            );
            $archiveUserStatement->execute(['id' => $adminId]);
            if ($archiveUserStatement->rowCount() !== 1) {
                throw new RuntimeException('The admin account could not be archived.');
            }

            $logStatement = $conn->prepare(
                "INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
                 VALUES (:user_id, :action, 'account_archives', :ip_address)"
            );
            $logStatement->execute([
                'user_id' => (int) $_SESSION['user_id'],
                'action' => "Archived admin account: {$admin['username']}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $conn->commit();
            header('Location: admin_management.php?status=archived');
            exit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Super admin account archive failed: ' . $e->getMessage());
            header('Location: admin_management.php?status=archive_failed');
            exit();
        }
    }

    $fullName = trim(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '');
    $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $adminId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);

    if (
        $fullName === '' ||
        strlen($fullName) > 100 ||
        $username === '' ||
        strlen($username) > 50 ||
        ($email !== '' && (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)))
    ) {
        header('Location: admin_management.php?status=invalid_fields');
        exit();
    }

    if ($action === 'edit' && (!$adminId || ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)))) {
        header('Location: admin_management.php?status=invalid_fields');
        exit();
    }

    if ($action === 'create' && ($password === '' || strlen($password) < 8 || strlen($password) > 72)) {
        header('Location: admin_management.php?status=invalid_fields');
        exit();
    }

    if (!in_array($action, ['create', 'edit'], true)) {
        header('Location: admin_management.php?status=invalid_request');
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
            header('Location: admin_management.php?status=username_exists');
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
                header('Location: admin_management.php?status=admin_not_found');
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
            'user_id' => (int) $_SESSION['user_id'],
            'action' => $logAction,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $conn->commit();
        header("Location: admin_management.php?status={$successStatus}");
        exit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Super admin account save failed: ' . $e->getMessage());
        header('Location: admin_management.php?status=save_failed');
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
    'archived' => ['success', 'Admin account archived and added to the review queue.'],
    'archive_failed' => ['error', 'The admin account could not be archived. Please try again.'],
    'username_exists' => ['error', 'That username is already in use.'],
    'invalid_fields' => ['error', 'Check the name, username, email, and password fields. Passwords must be between 8 and 72 characters.'],
    'invalid_request' => ['error', 'Your request could not be verified. Please try again.'],
    'admin_not_found' => ['error', 'That admin account no longer exists.'],
    'save_failed' => ['error', 'The admin account could not be saved. Please try again.'],
];
$notice = $statusMessages[$_GET['status'] ?? ''] ?? null;
$pageTitle = 'Admin Management';
$activePage = 'admin';
require __DIR__ . '/_super_admin_header.php';
?>
<header class="page-header">
    <h1>Admin Management</h1>
    <p>Create, update, and archive administrator accounts.</p>
</header>

<?php if ($notice): ?>
    <div class="alert alert-<?= htmlspecialchars($notice[0], ENT_QUOTES, 'UTF-8') ?> notice" role="status">
        <?= htmlspecialchars($notice[1], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="management-grid" style="display: grid; grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.5fr); gap: 24px; align-items: start;">
    <section class="table-container panel" aria-labelledby="create-admin-heading">
        <h2 id="create-admin-heading">Create Admin Account</h2>
        <form method="POST" action="admin_management.php">
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
                <label for="email">Email (optional)</label>
                <input type="email" id="email" name="email" maxlength="100" autocomplete="email">
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
                    <thead><tr><th>Full name</th><th>Username</th><th>Email</th><th>Actions</th></tr></thead>
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
                                    <form method="POST" action="admin_management.php">
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
                                <form method="POST" action="admin_management.php" onsubmit="return confirm('Archive this admin account? It will be sent to the archive review queue.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>">
                                    <button type="submit" class="btn-delete delete-button">Archive</button>
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
<style>
    @media (max-width: 900px) {
        .management-grid { grid-template-columns: minmax(0, 1fr) !important; }
    }
</style>
<?php require __DIR__ . '/_super_admin_footer.php'; ?>
