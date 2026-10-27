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

function getSubjectTermsForAssignment(string $semester): array {
    switch ($semester) {
        case '1st Term':
        case '1st Sem':
            return ['1st', '1st Sem'];
        case '2nd Term':
        case '2nd Sem':
            return ['2nd', '2nd Sem'];
        case '3rd Term':
            return ['3rd', '3rd Term'];
        case 'Full Year':
            return ['Full Year'];
        default:
            return [];
    }
}

// Active Academic Year & Term selections
$allowed_years = ['2026-2027', '2025-2026'];
$allowed_semesters = ['1st Term', '2nd Term', '3rd Term', '1st Sem', '2nd Sem', 'Full Year', 'All'];
$requested_ay = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['academic_year'] ?? '')
    : ($_GET['academic_year'] ?? '');
$requested_semester = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['semester'] ?? '')
    : ($_GET['semester'] ?? '');
$active_ay = in_array($requested_ay, $allowed_years, true) ? $requested_ay : '2026-2027';
$active_sem = in_array($requested_semester, $allowed_semesters, true) ? $requested_semester : '1st Term';

// 2. Handle Form Submission (Save or Update Assignment)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_assignment'])) {
    $faculty_id    = $_POST['faculty_id'] ?? null;
    $section_id    = $_POST['section_id'] ?? null;
    $subject_id    = $_POST['subject_id'] ?? null;
    $subject_strand_id = isset($_POST['subject_strand_id']) && $_POST['subject_strand_id'] !== ''
        ? (int) $_POST['subject_strand_id']
        : null;
    $academic_year = $active_ay;
    $semester      = $active_sem;

    if (!$faculty_id || !$section_id || !$subject_id) {
        $message = "missing_fields";
    } else {
        $section_stmt = $conn->prepare('
            SELECT id, grade_level, strand_id, academic_year, curriculum_version
            FROM sections
            WHERE id = ?
            LIMIT 1
        ');
        $section_stmt->execute([(int) $section_id]);
        $target_section = $section_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target_section || $target_section['academic_year'] !== $academic_year) {
            $message = "invalid_section";
        } elseif ($subject_strand_id !== null && $subject_strand_id <= 0) {
            $message = "invalid_strand";
        } else {
            if ($subject_strand_id !== null) {
                $strand_stmt = $conn->prepare('SELECT id FROM strands WHERE id = ? LIMIT 1');
                $strand_stmt->execute([$subject_strand_id]);
                if (!$strand_stmt->fetchColumn()) {
                    $message = "invalid_strand";
                }
            }

            $subject_terms = getSubjectTermsForAssignment($semester);
            $subject_sql = '
                SELECT id
                FROM subjects
                WHERE id = ?
                    AND curriculum_version = ?
                    AND (grade_level = ? OR grade_level IS NULL)
                    AND ' . ($subject_strand_id === null
                        ? 'strand_id IS NULL'
                        : '(strand_id = ? OR strand_id IS NULL)') . '
                    AND `term` IN (' . implode(', ', array_fill(0, count($subject_terms), '?')) . ')';
            $subject_params = [
                (int) $subject_id,
                $target_section['curriculum_version'],
                (int) $target_section['grade_level'],
            ];
            if ($subject_strand_id !== null) {
                $subject_params[] = $subject_strand_id;
            }
            if ($message !== "invalid_strand") {
                if (!$subject_terms) {
                    $message = "invalid_term";
                } else {
                    $subject_params = array_merge($subject_params, $subject_terms);
                    $subject_stmt = $conn->prepare($subject_sql);
                    $subject_stmt->execute($subject_params);

                    if (!$subject_stmt->fetchColumn()) {
                        $message = "invalid_subject";
                    } else {
                        try {
                            // Check if the subject is already assigned to this section for the selected term.
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
            }
        }
    }
}

// 3. Handle Delete Assignment
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $del_stmt = $conn->prepare("DELETE FROM subject_assignments WHERE id = ?");
    $del_stmt->execute([$id]);
    header("Location: manage_assignments.php?academic_year=" . urlencode($active_ay) . "&semester=" . urlencode($active_sem) . "&filter_section=" . urlencode($_GET['filter_section'] ?? '') . "&msg=deleted");
    exit();
}

// 4. Fetch Dropdown Selection Options
$faculty_members = $conn->query("SELECT id, full_name FROM users WHERE role IN ('faculty', 'adviser') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$strands = $conn->query("SELECT id, strand_name, strand_code FROM strands ORDER BY strand_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$filter_sections_stmt = $conn->prepare("
    SELECT id, section_name, grade_level, strand_id, academic_year, curriculum_version
    FROM sections
    WHERE academic_year = ?
    ORDER BY grade_level ASC, section_name ASC
");
$filter_sections_stmt->execute([$active_ay]);
$filter_sections = $filter_sections_stmt->fetchAll(PDO::FETCH_ASSOC);
$subjects = $conn->query("
    SELECT id, subject_name, subject_code, type, curriculum_version, strand_id, grade_level, `term`
    FROM subjects
    ORDER BY curriculum_version DESC, subject_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Active Assignments Table Data
$requested_section = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['section_id'] ?? '')
    : ($_GET['filter_section'] ?? '');
$filter_section = null;
foreach ($filter_sections as $section) {
    if ((string) $section['id'] === (string) $requested_section) {
        $filter_section = (int) $section['id'];
        break;
    }
}
$assignment_section_id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int) ($_POST['section_id'] ?? 0)
    : (int) ($_GET['filter_section'] ?? 0);
$assignment_strand_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('subject_strand_id', $_POST)) {
    $assignment_strand_id = $_POST['subject_strand_id'] === '' ? 0 : (int) $_POST['subject_strand_id'];
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach ($filter_sections as $section) {
        if ((int) $section['id'] === $assignment_section_id) {
            $assignment_strand_id = (int) ($section['strand_id'] ?? 0);
            break;
        }
    }
}
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
} else {
    $query_str .= " AND 1 = 0";
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
            display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 20px;
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
            min-width: 0; height: 100%; overflow: auto; box-sizing: border-box;
        }

        .mobile-toggle { display: none; z-index: 999; }
        .sidebar-close { display: none; background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; }
        .sidebar { height: 100vh; overflow-y: auto; box-sizing: border-box; }

        @media (max-width: 1024px) {
            body { overflow-x: hidden; overflow-y: auto; }
            .dashboard-wrapper { height: auto; min-height: 100vh; overflow: visible; }
            main.content { margin-left: 0 !important; width: 100%; min-width: 0; min-height: 100vh; height: auto; overflow: visible; padding: 15px; }
            .admin-layout { grid-template-columns: minmax(0, 1fr); flex: none; min-height: auto; overflow: visible; }
            .table-container { height: auto; overflow-x: auto; overflow-y: visible; }
            .table-container > div:first-child form { flex-wrap: wrap; max-width: 100%; }
            .table-container > div:first-child form select { flex: 1 1 130px; min-width: 0; max-width: 100%; }
            .table-container table { min-width: 700px; }
            #sidebar.active .sidebar-header h3 { margin-bottom: 0; }
            #sidebar.active .sidebar-close { display: flex; align-items: center; justify-content: center; flex: 0 0 36px; width: 36px; height: 36px; position: relative; z-index: 1002; }
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
                    <?php if($message == "invalid_section"): ?><div class="status-msg msg-warning">⚠ Select a section that belongs to the chosen academic year.</div><?php endif; ?>
                    <?php if($message == "invalid_strand"): ?><div class="status-msg msg-warning">⚠ Select a valid subject strand.</div><?php endif; ?>
                    <?php if($message == "invalid_term"): ?><div class="status-msg msg-warning">⚠ Select a valid academic term.</div><?php endif; ?>
                    <?php if($message == "invalid_subject"): ?><div class="status-msg msg-warning">⚠ Select a subject that matches the target section's curriculum, grade, strand, and term.</div><?php endif; ?>
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
                            <select name="semester" id="assignmentSemester" required>
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
                            <select name="section_id" id="targetSection" required>
                                <option value="">Select Section</option>
                                <?php foreach ($filter_sections as $sec): ?>
                                    <option
                                        value="<?= $sec['id']; ?>"
                                        data-grade="<?= htmlspecialchars((string) $sec['grade_level']); ?>"
                                        data-strand-id="<?= htmlspecialchars((string) ($sec['strand_id'] ?? '')); ?>"
                                        data-curriculum="<?= htmlspecialchars($sec['curriculum_version']); ?>"
                                        <?= $assignment_section_id === (int) $sec['id'] ? 'selected' : ''; ?>
                                    >
                                        Grade <?= htmlspecialchars($sec['grade_level'] . ' - ' . $sec['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Subject Strand</label>
                            <select name="subject_strand_id" id="subjectStrand">
                                <option value="" <?= $assignment_strand_id === 0 ? 'selected' : ''; ?>>General / Shared Subjects</option>
                                <?php foreach ($strands as $strand): ?>
                                    <option
                                        value="<?= $strand['id']; ?>"
                                        <?= $assignment_strand_id === (int) $strand['id'] ? 'selected' : ''; ?>
                                    >
                                        <?= htmlspecialchars($strand['strand_name'] . ' (' . $strand['strand_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; margin-top: 5px; color: #64748b;">
                                Choose a strand to include its subjects plus shared subjects. This does not change the section’s configured strand.
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Subject</label>
                            <select name="subject_id" id="targetSubject" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option
                                        value="<?= $sub['id']; ?>"
                                        data-grade="<?= htmlspecialchars((string) ($sub['grade_level'] ?? '')); ?>"
                                        data-strand-id="<?= htmlspecialchars((string) ($sub['strand_id'] ?? '')); ?>"
                                        data-curriculum="<?= htmlspecialchars($sub['curriculum_version']); ?>"
                                        data-term="<?= htmlspecialchars($sub['term']); ?>"
                                        hidden
                                    >
                                        <?= htmlspecialchars($sub['subject_name'] . ' (' . $sub['subject_code'] . ')') ?> [<?= $sub['curriculum_version'] ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="subjectFilterHint" style="display: block; margin-top: 5px; color: #64748b;">
                                Select a target section, subject strand, and term to see applicable subjects.
                            </small>
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
                                <option value="">Select a section</option>
                                <?php foreach ($filter_sections as $sec): ?>
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
                                            <a href="?delete=<?= $row['id']; ?>&academic_year=<?= urlencode($active_ay) ?>&semester=<?= urlencode($active_sem) ?>&filter_section=<?= urlencode((string) $filter_section) ?>" class="delete-btn" onclick="return confirm('Remove this subject assignment?')">Remove</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 25px; color: #94a3b8;">
                                        <?php if (!$filter_section): ?>
                                            Select a section to view its faculty assignments for SY <?= htmlspecialchars($active_ay) ?>.
                                        <?php else: ?>
                                            No faculty loads assigned to this section for SY <?= htmlspecialchars($active_ay) ?> (<?= htmlspecialchars($active_sem) ?>).
                                        <?php endif; ?>
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
        const sectionSelect = document.getElementById('targetSection');
        const strandSelect = document.getElementById('subjectStrand');
        const semesterSelect = document.getElementById('assignmentSemester');
        const subjectSelect = document.getElementById('targetSubject');
        const subjectFilterHint = document.getElementById('subjectFilterHint');

        function getSubjectTermsForAssignment(semester) {
            if (semester === '1st Term' || semester === '1st Sem') return ['1st', '1st Sem'];
            if (semester === '2nd Term' || semester === '2nd Sem') return ['2nd', '2nd Sem'];
            if (semester === '3rd Term') return ['3rd', '3rd Term'];
            if (semester === 'Full Year') return ['Full Year'];
            return [];
        }

        function filterSubjectsForSection() {
            const sectionOption = sectionSelect.options[sectionSelect.selectedIndex];
            const hasSection = Boolean(sectionSelect.value);
            const grade = sectionOption?.dataset.grade || '';
            const strandId = strandSelect.value;
            const curriculum = (sectionOption?.dataset.curriculum || '').toLowerCase();
            const allowedTerms = getSubjectTermsForAssignment(semesterSelect.value);
            let visibleSubjectCount = 0;
            let selectedSubjectStillValid = false;

            Array.from(subjectSelect.options).forEach(option => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const subjectGrade = option.dataset.grade || '';
                const subjectStrandId = option.dataset.strandId || '';
                const subjectCurriculum = (option.dataset.curriculum || '').toLowerCase();
                const subjectTerm = option.dataset.term || '';
                const matches = hasSection
                    && subjectCurriculum === curriculum
                    && (!subjectGrade || subjectGrade === grade)
                    && (subjectStrandId === strandId || subjectStrandId === '')
                    && allowedTerms.includes(subjectTerm);

                option.hidden = !matches;
                if (matches) {
                    visibleSubjectCount++;
                    if (option.value === subjectSelect.value) {
                        selectedSubjectStillValid = true;
                    }
                }
            });

            if (!selectedSubjectStillValid) {
                subjectSelect.value = '';
            }
            subjectSelect.disabled = !hasSection || visibleSubjectCount === 0;
            subjectFilterHint.textContent = !hasSection
                ? 'Select a target section to see applicable subjects.'
                : visibleSubjectCount === 0
                    ? 'No subjects match this section, selected strand, and academic term.'
                    : `${visibleSubjectCount} applicable subject${visibleSubjectCount === 1 ? '' : 's'} available.`;
        }

        sectionSelect.addEventListener('change', function () {
            const sectionOption = sectionSelect.options[sectionSelect.selectedIndex];
            strandSelect.value = sectionOption?.dataset.strandId || '';
            filterSubjectsForSection();
        });
        strandSelect.addEventListener('change', filterSubjectsForSection);
        semesterSelect.addEventListener('change', filterSubjectsForSection);
        filterSubjectsForSection();

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