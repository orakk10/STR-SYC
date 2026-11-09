<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$pdo = getDBConnection();

// Authorization Guard
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['adviser', 'faculty', 'admin'])) {
    header("Location: ../../manifest/login.php");
    exit();
}

$user_id   = (int) $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role']);
$is_adviser_or_admin = in_array($user_role, ['adviser', 'admin']);
$success_msg = "";
$error_msg = "";

$reset_query = $pdo->prepare("SELECT require_reset FROM users WHERE id = ?");
$reset_query->execute([$user_id]);
$require_password_change = (bool) $reset_query->fetchColumn();

// Handle Approval / Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['student_id'])) {
    $student_id = (int) $_POST['student_id'];
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT pending_profile_image, profile_image FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student && !empty($student['pending_profile_image'])) {
            if ($action === 'approve') {
                // Delete old approved file if it exists
                if (!empty($student['profile_image']) && file_exists('../student/' . $student['profile_image'])) {
                    @unlink('../student/' . $student['profile_image']);
                }
                // Promote pending image to active profile image
                $update = $pdo->prepare("UPDATE users SET profile_image = pending_profile_image, pending_profile_image = NULL, avatar_status = 'approved' WHERE id = ?");
                $update->execute([$student_id]);
                $success_msg = "Student profile picture approved successfully!";
            } elseif ($action === 'reject') {
                // Delete pending file from storage
                if (file_exists('../student/' . $student['pending_profile_image'])) {
                    @unlink('../student/' . $student['pending_profile_image']);
                }
                // Clear pending data
                $update = $pdo->prepare("UPDATE users SET pending_profile_image = NULL, avatar_status = 'approved' WHERE id = ?");
                $update->execute([$student_id]);
                $success_msg = "Student profile picture submission rejected and discarded.";
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Avatar moderation failed: " . $e->getMessage());
        $error_msg = "An error occurred while processing the avatar moderation request.";
    }
}

// Fetch pending avatars count and student list for this adviser's section
$pending_avatar_count = 0;
$avatar_count_query = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM users u
    JOIN sections s ON u.section_id = s.id
    WHERE s.adviser_id = ? AND u.avatar_status = 'pending'
");
$avatar_count_query->execute([$user_id]);
$pending_avatar_count = (int) ($avatar_count_query->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$query = "
    SELECT u.id, u.full_name, u.username as lrn, u.profile_image, u.pending_profile_image, s.section_name
    FROM users u
    JOIN sections s ON u.section_id = s.id
    WHERE s.adviser_id = ? AND u.avatar_status = 'pending'
    ORDER BY u.full_name ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$pending_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Student Avatars | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/security-lock.css">
    <script src="../../assets/js/security-lock.js?v=<?php echo time(); ?>"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            height: auto;
            width: 100%;
            overflow: visible;
            position: relative;
        }

        main.content {
            flex-grow: 1;
            margin-left: 260px;
            padding: 30px;
            width: 100%;
            max-width: 100%;
            height: 100vh;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        #sidebar:not(.active) ~ main.content {
            margin-left: 0;
            width: 100%;
        }

        .sidebar {
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.25);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.4);
        }

        .nav-badge {
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            float: right;
            margin-top: 2px;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .mobile-toggle {
            display: block;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1100;
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .mobile-toggle.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .student-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .avatar-comparison {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 15px 0;
            align-items: center;
        }

        .avatar-box img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #cbd5e1;
        }

        .avatar-box p {
            font-size: 0.75rem;
            color: #64748b;
            margin: 5px 0 0;
        }

        .action-btns {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-approve {
            background: #16a34a;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            flex: 1;
            font-weight: 600;
        }

        .btn-approve:hover {
            background: #15803d;
        }

        .btn-reject {
            background: #dc2626;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            flex: 1;
            font-weight: 600;
        }

        .btn-reject:hover {
            background: #b91c1c;
        }

        @media (max-width: 1024px) {
            html,
            body {
                height: auto;
                min-height: 100%;
                overflow-x: hidden;
                overflow-y: auto;
            }

            body:has(.dashboard-wrapper) {
                height: auto;
                overflow-y: auto;
            }

            .dashboard-wrapper {
                display: block;
                width: 100%;
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }

            main.content {
                margin-left: 0 !important;
                width: 100%;
                height: auto;
                min-height: 100vh;
                overflow: visible;
                padding: 15px;
                box-sizing: border-box;
            }

            #sidebar.active .sidebar-close {
                display: block;
            }
        }
    </style>
</head>

<body>
    <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>

    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li>
                    <a href="faculty_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'faculty_dashboard.php' ? 'active' : '' ?>">Dashboard</a>
                </li>
                <li>
                    <a href="faculty_schedule.php" class="<?= basename($_SERVER['PHP_SELF']) == 'faculty_schedule.php' ? 'active' : '' ?>">My Schedule</a>
                </li>
                <li>
                    <a href="ecr-inputdata.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ecr-inputdata.php' ? 'active' : '' ?>">ECR Setup & Roster</a>
                </li>
                <li>
                    <a href="ecr-view.php?term=1" class="<?= basename($_SERVER['PHP_SELF']) == 'ecr-view.php' ? 'active' : '' ?>">Encode Term Grades</a>
                </li>
                <li>
                    <a href="ecr-summary.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ecr-summary.php' ? 'active' : '' ?>">ECR Summary</a>
                </li>

                <?php if ($is_adviser_or_admin): ?>
                    <!-- Official DepEd Advisory Forms Section -->
                    <li class="menu-divider" style="padding: 12px 16px 4px 16px; font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">
                        Advisory Forms
                    </li>
                    <li>
                        <a href="review_avatars.php" class="<?= basename($_SERVER['PHP_SELF']) == 'review_avatars.php' ? 'active' : '' ?>">
                            Review Avatars 
                            <?php if ($pending_avatar_count > 0): ?>
                                <span class="nav-badge"><?= $pending_avatar_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="sf9-reportcard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'sf9-reportcard.php' ? 'active' : '' ?>">
                            SF9 (Report Card)
                        </a>
                    </li>
                    <li>
                        <a href="sf10-record.php" class="<?= basename($_SERVER['PHP_SELF']) == 'sf10-record.php' ? 'active' : '' ?>">
                            SF10 (Permanent Record)
                        </a>
                    </li>
                <?php endif; ?>

                <li>
                    <a href="../../manifest/logout.php" class="logout">Logout</a>
                </li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header" style="margin-bottom: 25px;">
                <h1>Student Profile Picture Moderation</h1>
                <p>Review and verify pending avatar photo uploads from students in your advisory section.</p>
            </header>

            <?php if ($require_password_change && in_array($user_role, ['faculty', 'adviser'], true)): ?>
                <div class="alert-warning" role="alert" style="background: #fffbeb; border: 1px solid #fcd34d; border-left: 5px solid #f59e0b; border-radius: 8px; color: #92400e; padding: 14px 18px; margin-bottom: 24px;">
                    <strong>Your account is using a temporary password.</strong>
                    For your security, <a href="change_password.php" style="color: #78350f; font-weight: 700;">change it now</a>.
                </div>
            <?php endif; ?>

            <?php if ($success_msg): ?>
                <div class="alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert-error">❌ <?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <div class="card-grid">
                <?php if (!empty($pending_students)): ?>
                    <?php foreach ($pending_students as $student): ?>
                        <div class="student-card">
                            <h3 style="margin: 0 0 5px 0; font-size: 1rem;"><?= htmlspecialchars($student['full_name']) ?></h3>
                            <p style="margin: 0; font-size: 0.8rem; color: #64748b;">LRN: <?= htmlspecialchars($student['lrn']) ?> | Section: <?= htmlspecialchars($student['section_name']) ?></p>
                            
                            <div class="avatar-comparison">
                                <div class="avatar-box">
                                    <img src="<?= !empty($student['profile_image']) ? '../student/' . htmlspecialchars($student['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png' ?>" alt="Current">
                                    <p>Current</p>
                                </div>
                                <span style="font-size: 1.2rem;">➡️</span>
                                <div class="avatar-box">
                                    <img src="../student/<?= htmlspecialchars($student['pending_profile_image']) ?>" alt="Pending">
                                    <p style="color: #2563eb; font-weight: 600;">New Submission</p>
                                </div>
                            </div>

                            <form action="review_avatars.php" method="POST" class="action-btns">
                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Are you sure you want to reject and discard this upload?')">Reject</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; color: #94a3b8; font-weight: 500;">
                        No pending profile picture moderation requests found for your advisory section.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../../assets/js/app.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const burger  = document.getElementById('mobileBurger');

            sidebar.classList.toggle('active');

            if (sidebar.classList.contains('active')) {
                burger.classList.add('hidden');
            } else {
                burger.classList.remove('hidden');
            }
        }
    </script>
</body>

</html>