<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control: Require Admin Role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

$message = "";

// Helper function to map subject type to DepEd DO 8, s. 2015 weights
function getComponentWeights(?string $type): array {
    switch ($type) {
        case 'Core':
            return ['ww' => 25, 'pt' => 50, 'qa' => 25];
        case 'Applied':
        case 'Specialized':
            return ['ww' => 25, 'pt' => 45, 'qa' => 30];
        default:
            return ['ww' => 20, 'pt' => 50, 'qa' => 30];
    }
}

// 2. Handle Form Submission (Save or Update Assignment)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_assignment'])) {
    $faculty_id = $_POST['faculty_id'] ?? null;
    $section_id = $_POST['section_id'] ?? null;
    $subject_id = $_POST['subject_id'] ?? null;

    if (!$faculty_id || !$section_id || !$subject_id) {
        $message = "missing_fields";
    } else {
        try {
            // Check if subject is already assigned to this section
            $check_stmt = $conn->prepare("SELECT id, faculty_id FROM subject_assignments WHERE section_id = ? AND subject_id = ?");
            $check_stmt->execute([$section_id, $subject_id]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update assigned faculty
                $update_stmt = $conn->prepare("UPDATE subject_assignments SET faculty_id = ? WHERE id = ?");
                $update_stmt->execute([$faculty_id, $existing['id']]);
                $message = "updated";
            } else {
                // Create new teaching load entry
                $insert_stmt = $conn->prepare("INSERT INTO subject_assignments (faculty_id, section_id, subject_id) VALUES (?, ?, ?)");
                $insert_stmt->execute([$faculty_id, $section_id, $subject_id]);
                $message = "success";
            }
        } catch (PDOException $e) {
            $message = "error";
        }
    }
}

// 3. Handle Delete Assignment
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $del_stmt = $conn->prepare("DELETE FROM subject_assignments WHERE id = ?");
    $del_stmt->execute([$id]);
    header("Location: manage_assignments.php?msg=deleted");
    exit();
}

// 4. Fetch Dropdown Selection Options
$faculty_members = $conn->query("SELECT id, full_name FROM users WHERE role IN ('faculty', 'adviser') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$sections = $conn->query("SELECT id, section_name, grade_level FROM sections ORDER BY grade_level ASC, section_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $conn->query("SELECT id, subject_name, subject_code, type FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Active Assignments Table Data
$filter_section = $_GET['filter_section'] ?? null;
$query_str = "
    SELECT sa.id,
           u.full_name AS teacher_name,
           sec.section_name, sec.grade_level,
           sub.subject_name, sub.subject_code, sub.type AS subject_type
    FROM subject_assignments sa
    JOIN users u ON sa.faculty_id = u.id
    JOIN sections sec ON sa.section_id = sec.id
    JOIN subjects sub ON sa.subject_id = sub.id
";

if ($filter_section) {
    $query_str .= " WHERE sa.section_id = " . intval($filter_section);
}

$query_str .= " ORDER BY sec.grade_level ASC, sec.section_name ASC, sub.subject_name ASC";
$assignments = $conn->query($query_str)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty Load Assignments | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .dashboard-wrapper { display: flex; min-height: 100vh; width: 100%; }
        main.content { flex-grow: 1; margin-left: 260px; padding: 30px; box-sizing: border-box; transition: margin-left 0.3s; }
        #sidebar:not(.active) ~ main.content { margin-left: 0; width: 100%; }
        
        .admin-layout { display: grid; grid-template-columns: 360px 1fr; gap: 25px; margin-top: 20px; align-items: start; }
        .card { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.85rem; color: #475569; }
        .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 0.9rem; }
        .btn-primary { background: #2563eb; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #1d4ed8; }
        
        .status-msg { padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 600; }
        .msg-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .msg-warning { background: #fffbe3; color: #b45309; border: 1px solid #fde68a; }
        .delete-btn { color: #ef4444; text-decoration: none; font-weight: bold; font-size: 0.85rem; }
        .delete-btn:hover { text-decoration: underline; }
        
        .weight-pill { font-size: 0.75rem; background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-left: 4px; }
        .type-badge { font-size: 0.75rem; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-left: 6px; }
        .table-container { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; }

        @media (max-width: 1024px) {
            main.content { margin-left: 0 !important; padding: 15px; padding-top: 60px; }
            .admin-layout { grid-template-columns: 1fr; }
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
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_strands.php">Manage Strands</a></li>
                <li><a href="curriculum_guide.php">Manage Subjects</a></li>
                <li><a href="manage_sections.php">Manage Sections</a></li>
                <li><a href="manage_assignments.php" class="active">Teaching Assignments</a></li>
                <li><a href="admin_schedule.php">Manage Schedules</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <main class="content">
            <header class="content-header">
                <h1>Faculty Teaching Assignments</h1>
                <p>Assign subjects and sections to teachers to grant them E-Class Record access.</p>
            </header>

            <div class="admin-layout">
                <!-- Assign Form Card -->
                <div class="card">
                    <h3>Assign Subject Load</h3>
                    <?php if($message == "success"): ?><div class="status-msg msg-success">✔ Subject assignment saved!</div><?php endif; ?>
                    <?php if($message == "updated"): ?><div class="status-msg msg-warning">⚡ Subject reassigned to new teacher!</div><?php endif; ?>
                    <?php if($message == "missing_fields"): ?><div class="status-msg msg-warning">⚠ Please select all required fields.</div><?php endif; ?>
                    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?><div class="status-msg msg-success">✔ Assignment removed successfully.</div><?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>Assigned Teacher</label>
                            <select name="faculty_id" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($faculty_members as $fac): ?>
                                    <option value="<?= $fac['id']; ?>"><?= htmlspecialchars($fac['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Target Section</label>
                            <select name="section_id" required>
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= $sec['id']; ?>">Grade <?= htmlspecialchars($sec['grade_level'] . ' - ' . $sec['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Subject</label>
                            <select name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?= $sub['id']; ?>"><?= htmlspecialchars($sub['subject_name'] . ' (' . $sub['subject_code'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" name="save_assignment" class="btn-primary">Assign Load</button>
                    </form>
                </div>

                <!-- Active Assignments Table -->
                <div class="table-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2>Active Faculty Loads (<?= count($assignments) ?>)</h2>
                        <form method="GET">
                            <select name="filter_section" onchange="this.form.submit()" style="padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= $sec['id']; ?>" <?= ($filter_section == $sec['id']) ? 'selected' : ''; ?>>
                                        Grade <?= htmlspecialchars($sec['grade_level'] . ' - ' . $sec['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; background: #f8fafc;">
                                <th style="padding: 10px;">Teacher</th>
                                <th style="padding: 10px;">Section</th>
                                <th style="padding: 10px;">Subject & Weights</th>
                                <th style="padding: 10px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignments)): ?>
                                <?php foreach ($assignments as $row): ?>
                                    <?php $weights = getComponentWeights($row['subject_type'] ?? 'Core'); ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 10px; font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['teacher_name']); ?></td>
                                        <td style="padding: 10px;">Grade <?= htmlspecialchars($row['grade_level'] . ' - ' . $row['section_name']); ?></td>
                                        <td style="padding: 10px;">
                                            <strong><?= htmlspecialchars($row['subject_name']); ?></strong> 
                                            <span style="color: #64748b; font-size: 0.8rem;">(<?= htmlspecialchars($row['subject_code']); ?>)</span>
                                            <span class="type-badge"><?= htmlspecialchars($row['subject_type'] ?? 'Core'); ?></span>
                                            <div style="margin-top: 4px;">
                                                <span class="weight-pill">WW: <?= $weights['ww'] ?>%</span>
                                                <span class="weight-pill">PT: <?= $weights['pt'] ?>%</span>
                                                <span class="weight-pill">QA: <?= $weights['qa'] ?>%</span>
                                            </div>
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <a href="?delete=<?= $row['id']; ?>" class="delete-btn" onclick="return confirm('Remove this subject assignment?')">Remove</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 25px; color: #94a3b8;">No faculty assignments created yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>