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

// Helper function to map subject type to DepEd grading weights
function getComponentWeights(?string $type): array {
    switch ($type) {
        case 'Core':
            return ['ww' => 25, 'pt' => 50, 'qa' => 25];
        case 'Applied':
        case 'Specialized':
        case 'Elective':
            return ['ww' => 25, 'pt' => 45, 'qa' => 30];
        default:
            return ['ww' => 25, 'pt' => 50, 'qa' => 25];
    }
}

// Active Academic Year & Term selections
$active_ay = $_GET['academic_year'] ?? '2026-2027';
$active_sem = $_GET['semester'] ?? '1st Term';

// 2. Handle Form Submission (Save or Update Assignment)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_assignment'])) {
    $faculty_id    = $_POST['faculty_id'] ?? null;
    $section_id    = $_POST['section_id'] ?? null;
    $subject_id    = $_POST['subject_id'] ?? null;
    $academic_year = $_POST['academic_year'] ?? '2026-2027';
    $semester      = $_POST['semester'] ?? '1st Term';

    if (!$faculty_id || !$section_id || !$subject_id) {
        $message = "missing_fields";
    } else {
        try {
            // Check if subject is already assigned to this section for the given AY and Term
            $check_stmt = $conn->prepare("
                SELECT id, faculty_id 
                FROM subject_assignments 
                WHERE section_id = ? AND subject_id = ? AND academic_year = ? AND (semester = ? OR semester = 'Full Year')
            ");
            $check_stmt->execute([$section_id, $subject_id, $academic_year, $semester]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update assigned faculty
                $update_stmt = $conn->prepare("UPDATE subject_assignments SET faculty_id = ? WHERE id = ?");
                $update_stmt->execute([$faculty_id, $existing['id']]);
                $message = "updated";
            } else {
                // Create new teaching load entry
                $insert_stmt = $conn->prepare("
                    INSERT INTO subject_assignments (faculty_id, section_id, subject_id, academic_year, semester) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([$faculty_id, $section_id, $subject_id, $academic_year, $semester]);
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
    header("Location: manage_assignments.php?academic_year=" . urlencode($active_ay) . "&semester=" . urlencode($active_sem) . "&msg=deleted");
    exit();
}

// 4. Fetch Dropdown Selection Options
$faculty_members = $conn->query("SELECT id, full_name FROM users WHERE role IN ('faculty', 'adviser') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$sections        = $conn->query("SELECT id, section_name, grade_level FROM sections ORDER BY grade_level ASC, section_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$subjects        = $conn->query("SELECT id, subject_name, subject_code, type, curriculum_version FROM subjects ORDER BY curriculum_version DESC, subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Active Assignments Table Data
$filter_section = $_GET['filter_section'] ?? null;
$query_str = "
    SELECT sa.id, sa.academic_year, sa.semester,
           u.full_name AS teacher_name,
           sec.section_name, sec.grade_level,
           sub.subject_name, sub.subject_code, sub.type AS subject_type, sub.curriculum_version
    FROM subject_assignments sa
    JOIN users u ON sa.faculty_id = u.id
    JOIN sections sec ON sa.section_id = sec.id
    JOIN subjects sub ON sa.subject_id = sub.id
    WHERE sa.academic_year = :ay
";

$params = ['ay' => $active_ay];

if ($active_sem && $active_sem !== 'All') {
    $query_str .= " AND (sa.semester = :sem OR sa.semester = 'Full Year')";
    $params['sem'] = $active_sem;
}

if ($filter_section) {
    $query_str .= " AND sa.section_id = :sec_id";
    $params['sec_id'] = intval($filter_section);
}

$query_str .= " ORDER BY sec.grade_level ASC, sec.section_name ASC, sub.subject_name ASC";
$stmt = $conn->prepare($query_str);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty Load Assignments | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body {
            margin: 0; padding: 0; overflow: hidden;
            background-color: #f8fafc; font-family: 'Inter', system-ui, sans-serif;
        }

        .dashboard-wrapper { display: flex; height: 100vh; width: 100%; overflow: hidden; position: relative; }

        main.content {
            flex-grow: 1; margin-left: 260px; padding: 25px 30px; width: 100%;
            height: 100vh; box-sizing: border-box; display: flex; flex-direction: column;
            transition: margin-left 0.3s ease; overflow: hidden;
        }

        #sidebar:not(.active) ~ main.content { margin-left: 0; width: 100%; }

        .admin-layout {
            display: grid; grid-template-columns: 380px 1fr; gap: 20px;
            margin-top: 15px; align-items: start; flex: 1; min-height: 0; overflow: hidden;
        }

        .card {
            background: white; padding: 20px; border-radius: 12px;
            border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 0.82rem; color: #475569; }
        .form-group select {
            width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1;
            border-radius: 6px; box-sizing: border-box; font-size: 0.85rem; outline: none;
        }

        .btn-primary {
            background: #2563eb; color: white; border: none; padding: 10px;
            width: 100%; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s;
        }
        .btn-primary:hover { background: #1d4ed8; }

        .status-msg { padding: 10px; border-radius: 6px; margin-bottom: 12px; font-size: 0.82rem; font-weight: 600; }
        .msg-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .msg-warning { background: #fffbe3; color: #b45309; border: 1px solid #fde68a; }

        .delete-btn { color: #ef4444; text-decoration: none; font-weight: 700; font-size: 0.82rem; }
        .delete-btn:hover { text-decoration: underline; }

        .weight-pill {
            display: inline-block; font-size: 0.72rem; background: #e0e7ff;
            color: #3730a3; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-left: 2px;
        }
        .type-badge {
            display: inline-block; font-size: 0.72rem; background: #f1f5f9;
            color: #475569; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-left: 4px;
        }
        .ver-badge {
            display: inline-block; font-size: 0.7rem; background: #f3e8ff;
            color: #7e22ce; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 4px;
        }

        .table-container {
            background: white; padding: 20px; border-radius: 12px;
            border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            height: 100%; overflow-y: auto; box-sizing: border-box;
        }

        .mobile-toggle { display: none; z-index: 999; }
        .sidebar-close { display: none; background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; }
        .sidebar { height: 100vh; overflow-y: auto; box-sizing: border-box; }

        @media (max-width: 1024px) {
            body { overflow: auto; }
            main.content { margin-left: 0 !important; width: 100%; height: auto; overflow: visible; padding: 15px; }
            .admin-layout { grid-template-columns: 1fr; overflow: visible; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>
    <div class="dashboard-wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_strands.php">Manage Strands</a></li>
                <li><a href="curriculum_guide.php">Curriculum Guide</a></li>
                <li><a href="manage_sections.php">Manage Sections</a></li>
                <li><a href="manage_assignments.php" class="active">Teaching Assignments</a></li>
                <li><a href="admin_schedule.php">Manage Schedules</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1 style="margin: 0; font-size: 1.4rem;">Subject Assignment</h1>
                <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.85rem;">Assign subjects and sections to teachers per Academic Year and Term.</p>
            </header>

            <div class="admin-layout">
                <!-- Assign Form Card -->
                <div class="card">
                    <h3 style="margin-top: 0; color: #1e293b;">Assign Subject Load</h3>
                    <?php if($message == "success"): ?><div class="status-msg msg-success">✔ Subject assignment saved!</div><?php endif; ?>
                    <?php if($message == "updated"): ?><div class="status-msg msg-warning">⚡ Subject reassigned to new teacher!</div><?php endif; ?>
                    <?php if($message == "missing_fields"): ?><div class="status-msg msg-warning">⚠ Please select all required fields.</div><?php endif; ?>
                    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?><div class="status-msg msg-success">✔ Assignment removed successfully.</div><?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>Academic Year</label>
                            <select name="academic_year" required>
                                <option value="2026-2027" <?= $active_ay === '2026-2027' ? 'selected' : '' ?>>SY 2026-2027 (MATATAG / 3-Term)</option>
                                <option value="2025-2026" <?= $active_ay === '2025-2026' ? 'selected' : '' ?>>SY 2025-2026 (Legacy / 2-Sem)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Academic Term / Semester</label>
                            <select name="semester" required>
                                <optgroup label="Strengthened MATATAG (3-Term)">
                                    <option value="1st Term" <?= $active_sem === '1st Term' ? 'selected' : '' ?>>1st Term</option>
                                    <option value="2nd Term" <?= $active_sem === '2nd Term' ? 'selected' : '' ?>>2nd Term</option>
                                    <option value="3rd Term" <?= $active_sem === '3rd Term' ? 'selected' : '' ?>>3rd Term</option>
                                </optgroup>
                                <optgroup label="Legacy (2-Semester)">
                                    <option value="1st Sem" <?= $active_sem === '1st Sem' ? 'selected' : '' ?>>1st Semester</option>
                                    <option value="2nd Sem" <?= $active_sem === '2nd Sem' ? 'selected' : '' ?>>2nd Semester</option>
                                </optgroup>
                                <option value="Full Year">Full Year (Core Subject)</option>
                            </select>
                        </div>

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
                                    <option value="<?= $sub['id']; ?>">
                                        <?= htmlspecialchars($sub['subject_name'] . ' (' . $sub['subject_code'] . ')') ?> [<?= $sub['curriculum_version'] ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" name="save_assignment" class="btn-primary">Assign Teaching Load</button>
                    </form>
                </div>

                <!-- Active Assignments Table -->
                <div class="table-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0; font-size: 1.1rem; color: #1e293b;">Active Faculty Loads (<?= count($assignments) ?>)</h2>
                        
                        <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                            <select name="academic_year" onchange="this.form.submit()" style="padding: 6px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.82rem;">
                                <option value="2026-2027" <?= $active_ay === '2026-2027' ? 'selected' : '' ?>>2026-2027</option>
                                <option value="2025-2026" <?= $active_ay === '2025-2026' ? 'selected' : '' ?>>2025-2026</option>
                            </select>

                            <select name="semester" onchange="this.form.submit()" style="padding: 6px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.82rem;">
                                <option value="1st Term" <?= $active_sem === '1st Term' ? 'selected' : '' ?>>1st Term</option>
                                <option value="2nd Term" <?= $active_sem === '2nd Term' ? 'selected' : '' ?>>2nd Term</option>
                                <option value="3rd Term" <?= $active_sem === '3rd Term' ? 'selected' : '' ?>>3rd Term</option>
                                <option value="1st Sem" <?= $active_sem === '1st Sem' ? 'selected' : '' ?>>1st Sem</option>
                                <option value="2nd Sem" <?= $active_sem === '2nd Sem' ? 'selected' : '' ?>>2nd Sem</option>
                                <option value="All" <?= $active_sem === 'All' ? 'selected' : '' ?>>All Terms</option>
                            </select>

                            <select name="filter_section" onchange="this.form.submit()" style="padding: 6px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.82rem;">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= $sec['id']; ?>" <?= ($filter_section == $sec['id']) ? 'selected' : ''; ?>>
                                        Grade <?= htmlspecialchars($sec['grade_level'] . ' - ' . $sec['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; background: #f8fafc;">
                                <th style="padding: 10px;">Teacher</th>
                                <th style="padding: 10px;">Section</th>
                                <th style="padding: 10px;">Subject & Weights</th>
                                <th style="padding: 10px;">Term</th>
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
                                            <span style="color: #64748b; font-size: 0.78rem;">(<?= htmlspecialchars($row['subject_code']); ?>)</span>
                                            <span class="type-badge"><?= htmlspecialchars($row['subject_type'] ?? 'Core'); ?></span>
                                            <span class="ver-badge"><?= htmlspecialchars($row['curriculum_version'] ?? 'Strengthened'); ?></span>
                                            <div style="margin-top: 4px;">
                                                <span class="weight-pill">WW: <?= $weights['ww'] ?>%</span>
                                                <span class="weight-pill">PT: <?= $weights['pt'] ?>%</span>
                                                <span class="weight-pill">QA: <?= $weights['qa'] ?>%</span>
                                            </div>
                                        </td>
                                        <td style="padding: 10px; font-weight: 600; color: #2563eb;">
                                            <?= htmlspecialchars($row['semester']) ?>
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <a href="?delete=<?= $row['id']; ?>&academic_year=<?= urlencode($active_ay) ?>&semester=<?= urlencode($active_sem) ?>" class="delete-btn" onclick="return confirm('Remove this subject assignment?')">Remove</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 25px; color: #94a3b8;">
                                        No faculty loads assigned for SY <?= htmlspecialchars($active_ay) ?> (<?= htmlspecialchars($active_sem) ?>).
                                    </td>
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
            const sidebar = document.getElementById('sidebar');
            const burger = document.getElementById('mobileBurger');
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