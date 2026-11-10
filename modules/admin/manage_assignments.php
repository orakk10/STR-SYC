<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control: Require Admin Role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    $assignment_id = filter_var($_POST['assignment_id'] ?? 0, FILTER_VALIDATE_INT);
    $faculty_id    = $_POST['faculty_id'] ?? null;
    $section_id    = $_POST['section_id'] ?? null;
    $subject_id    = $_POST['subject_id'] ?? null;
    $subject_strand_id = isset($_POST['subject_strand_id']) && $_POST['subject_strand_id'] !== ''
        ? (int) $_POST['subject_strand_id']
        : null;
    $academic_year = $active_ay;
    $semester      = $active_sem;

    if (
        empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))
    ) {
        $message = "invalid_request";
    } elseif ($assignment_id === false || $assignment_id < 0) {
        $message = "invalid_assignment";
    } elseif ($assignment_id > 0) {
        $assignment_stmt = $conn->prepare("
            SELECT id
            FROM subject_assignments
            WHERE id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM resource_archives ra
                  WHERE ra.resource_type = 'assignment' AND ra.resource_id = subject_assignments.id
              )
            LIMIT 1
        ");
        $assignment_stmt->execute([$assignment_id]);
        if (!$assignment_stmt->fetchColumn()) {
            $message = "invalid_assignment";
        }
    }

    if ($message === "" && (!$faculty_id || !$section_id || !$subject_id)) {
        $message = "missing_fields";
    } elseif ($message === "") {
        $faculty_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('faculty', 'adviser') LIMIT 1");
        $faculty_stmt->execute([(int) $faculty_id]);
        if (!$faculty_stmt->fetchColumn()) {
            $message = "invalid_faculty";
        }

        $section_stmt = $conn->prepare("
            SELECT id, grade_level, strand_id, academic_year, curriculum_version
            FROM sections
            WHERE id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM resource_archives ra
                  WHERE ra.resource_type = 'section' AND ra.resource_id = sections.id
              )
              AND (
                  sections.strand_id IS NULL OR NOT EXISTS (
                      SELECT 1 FROM resource_archives ra
                      WHERE ra.resource_type = 'strand' AND ra.resource_id = sections.strand_id
                  )
              )
            LIMIT 1
        ");
        $section_stmt->execute([(int) $section_id]);
        $target_section = $section_stmt->fetch(PDO::FETCH_ASSOC);

        if ($message === "" && (!$target_section || $target_section['academic_year'] !== $academic_year)) {
            $message = "invalid_section";
        } elseif ($message === "" && $subject_strand_id !== null && $subject_strand_id <= 0) {
            $message = "invalid_strand";
        } elseif ($message === "") {
            if ($subject_strand_id !== null) {
                $strand_stmt = $conn->prepare("
                    SELECT s.id FROM strands s
                    WHERE s.id = ?
                      AND NOT EXISTS (
                          SELECT 1 FROM resource_archives ra
                          WHERE ra.resource_type = 'strand' AND ra.resource_id = s.id
                      )
                    LIMIT 1
                ");
                $strand_stmt->execute([$subject_strand_id]);
                if (!$strand_stmt->fetchColumn()) {
                    $message = "invalid_strand";
                }
            }

            $subject_terms = getSubjectTermsForAssignment($semester);
            $subject_sql = '
                SELECT id
                FROM subjects sub
                WHERE id = ?
                    AND curriculum_version = ?
                    AND (grade_level = ? OR grade_level IS NULL)
                    AND ' . ($subject_strand_id === null
                        ? 'strand_id IS NULL'
                        : '(strand_id = ? OR strand_id IS NULL)') . '
                    AND `term` IN (' . implode(', ', array_fill(0, count($subject_terms), '?')) . ')
                    AND NOT EXISTS (
                        SELECT 1 FROM resource_archives ra
                        WHERE ra.resource_type = \'subject\' AND ra.resource_id = sub.id
                    )
                    AND (
                        sub.strand_id IS NULL OR NOT EXISTS (
                            SELECT 1 FROM resource_archives ra
                            WHERE ra.resource_type = \'strand\' AND ra.resource_id = sub.strand_id
                        )
                    )';
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
                            $duplicate_sql = "
                                SELECT id, faculty_id
                                FROM subject_assignments
                                WHERE section_id = ? AND subject_id = ? AND academic_year = ?
                                  AND (? = 'Full Year' OR semester = ? OR semester = 'Full Year')
                                  AND NOT EXISTS (
                                      SELECT 1 FROM resource_archives ra
                                      WHERE ra.resource_type = 'assignment' AND ra.resource_id = subject_assignments.id
                                  )
                            ";
                            $duplicate_params = [$section_id, $subject_id, $academic_year, $semester, $semester];
                            if ($assignment_id > 0) {
                                $duplicate_sql .= " AND id <> ?";
                                $duplicate_params[] = $assignment_id;
                            }
                            $check_stmt = $conn->prepare($duplicate_sql);
                            $check_stmt->execute($duplicate_params);
                            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

                            if ($assignment_id > 0 && $existing) {
                                $message = "duplicate_assignment";
                            } elseif ($assignment_id > 0) {
                                $update_stmt = $conn->prepare("
                                    UPDATE subject_assignments
                                    SET faculty_id = ?, section_id = ?, subject_id = ?, academic_year = ?, semester = ?
                                    WHERE id = ?
                                      AND NOT EXISTS (
                                          SELECT 1 FROM resource_archives ra
                                          WHERE ra.resource_type = 'assignment' AND ra.resource_id = subject_assignments.id
                                      )
                                ");
                                $update_stmt->execute([$faculty_id, $section_id, $subject_id, $academic_year, $semester, $assignment_id]);
                                if ($update_stmt->rowCount() > 0) {
                                    $message = "assignment_updated";
                                } else {
                                    $still_active_stmt = $conn->prepare("
                                        SELECT id FROM subject_assignments
                                        WHERE id = ?
                                          AND NOT EXISTS (
                                              SELECT 1 FROM resource_archives ra
                                              WHERE ra.resource_type = 'assignment' AND ra.resource_id = subject_assignments.id
                                          )
                                        LIMIT 1
                                    ");
                                    $still_active_stmt->execute([$assignment_id]);
                                    $message = $still_active_stmt->fetchColumn() ? "assignment_updated" : "invalid_assignment";
                                }
                            } elseif ($existing) {
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

// 4. Fetch Dropdown Selection Options
$faculty_members = $conn->query("SELECT id, full_name FROM users WHERE role IN ('faculty', 'adviser') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$strands = $conn->query("
    SELECT s.id, s.strand_name, s.strand_code
    FROM strands s
    WHERE NOT EXISTS (
        SELECT 1 FROM resource_archives ra
        WHERE ra.resource_type = 'strand' AND ra.resource_id = s.id
    )
    ORDER BY s.strand_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$filter_sections_stmt = $conn->prepare("
    SELECT id, section_name, grade_level, strand_id, academic_year, curriculum_version
    FROM sections
    WHERE academic_year IN (?, ?)
      AND NOT EXISTS (
          SELECT 1 FROM resource_archives ra
          WHERE ra.resource_type = 'section' AND ra.resource_id = sections.id
      )
      AND (
          sections.strand_id IS NULL OR NOT EXISTS (
              SELECT 1 FROM resource_archives ra
              WHERE ra.resource_type = 'strand' AND ra.resource_id = sections.strand_id
          )
      )
    ORDER BY academic_year DESC, grade_level ASC, section_name ASC
");
$filter_sections_stmt->execute($allowed_years);
$assignment_sections = $filter_sections_stmt->fetchAll(PDO::FETCH_ASSOC);
$filter_sections = array_values(array_filter(
    $assignment_sections,
    static fn (array $section): bool => $section['academic_year'] === $active_ay
));
$subjects = $conn->query("
    SELECT id, subject_name, subject_code, type, curriculum_version, strand_id, grade_level, `term`
    FROM subjects sub
    WHERE NOT EXISTS (
        SELECT 1 FROM resource_archives ra
        WHERE ra.resource_type = 'subject' AND ra.resource_id = sub.id
    )
      AND (
          sub.strand_id IS NULL OR NOT EXISTS (
              SELECT 1 FROM resource_archives ra
              WHERE ra.resource_type = 'strand' AND ra.resource_id = sub.strand_id
          )
      )
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
    SELECT sa.id, sa.faculty_id, sa.section_id, sa.subject_id, sa.academic_year, sa.semester,
           u.full_name AS teacher_name,
           sec.section_name, sec.grade_level,
           sub.subject_name, sub.subject_code, sub.type AS subject_type, sub.curriculum_version, sub.strand_id AS subject_strand_id
    FROM subject_assignments sa
    JOIN users u ON sa.faculty_id = u.id
    JOIN sections sec ON sa.section_id = sec.id
    JOIN subjects sub ON sa.subject_id = sub.id
    WHERE sa.academic_year = :ay
      AND NOT EXISTS (
          SELECT 1 FROM resource_archives ra
          WHERE ra.resource_type = 'assignment' AND ra.resource_id = sa.id
      )
      AND NOT EXISTS (
          SELECT 1 FROM resource_archives ra
          WHERE ra.resource_type = 'section' AND ra.resource_id = sec.id
      )
      AND NOT EXISTS (
          SELECT 1 FROM resource_archives ra
          WHERE ra.resource_type = 'subject' AND ra.resource_id = sub.id
      )
      AND (
          sub.strand_id IS NULL OR NOT EXISTS (
              SELECT 1 FROM resource_archives ra
              WHERE ra.resource_type = 'strand' AND ra.resource_id = sub.strand_id
          )
      )
      AND (
          sec.strand_id IS NULL OR NOT EXISTS (
              SELECT 1 FROM resource_archives ra
              WHERE ra.resource_type = 'strand' AND ra.resource_id = sec.strand_id
          )
      )
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
            margin: 0; padding: 0; overflow-x: hidden; overflow-y: auto;
            background-color: #f8fafc; font-family: 'Inter', system-ui, sans-serif;
        }

        .dashboard-wrapper { display: flex; min-height: 100vh; height: auto; width: 100%; overflow: visible; position: relative; }

        main.content {
            flex-grow: 1; margin-left: 260px; padding: 25px 30px; width: 100%;
            min-height: 100vh; height: auto; box-sizing: border-box; display: flex; flex-direction: column;
            transition: margin-left 0.3s ease; overflow: visible;
        }

        #sidebar:not(.active) ~ main.content { margin-left: 0; width: 100%; }

        .admin-layout {
            display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 20px;
            margin-top: 15px; align-items: start; flex: none; min-height: auto; overflow: visible;
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

        .assignments-table { width: 100%; min-width: 760px; table-layout: fixed; border-collapse: collapse; font-size: 0.85rem; }
        .assignments-table th,
        .assignments-table td { padding: 10px; text-align: left; vertical-align: middle; overflow-wrap: anywhere; }
        .assignments-table th:last-child,
        .assignments-table td:last-child { text-align: center; }
        .assignment-actions { display: flex; justify-content: center; align-items: center; gap: 8px; white-space: nowrap; }
        .assignment-actions form { display: inline-flex; margin: 0; }
        .assignment-action-btn {
            display: inline-flex; justify-content: center; align-items: center;
            min-width: 64px; padding: 6px 10px; border: 1px solid transparent;
            border-radius: 6px; font: inherit; font-size: 0.78rem; font-weight: 700;
            line-height: 1.2; text-decoration: none; cursor: pointer; transition: background-color 0.2s, border-color 0.2s;
        }
        .assignment-action-edit { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }
        .assignment-action-edit:hover { background: #dbeafe; }
        .assignment-action-archive { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
        .assignment-action-archive:hover { background: #fee2e2; }

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
            min-width: 0; height: auto; overflow-x: auto; overflow-y: visible; box-sizing: border-box;
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
            .table-container .assignments-table { min-width: 760px; }
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
                    <h3 id="assignmentFormTitle" style="margin-top: 0; color: #1e293b;">Assign Subject Load</h3>
                    <?php if($message == "success"): ?><div class="status-msg msg-success">✔ Subject assignment saved!</div><?php endif; ?>
                    <?php if($message == "updated"): ?><div class="status-msg msg-warning">⚡ Subject reassigned to new teacher!</div><?php endif; ?>
                    <?php if($message == "assignment_updated"): ?><div class="status-msg msg-success">✔ Subject assignment updated!</div><?php endif; ?>
                    <?php if($message == "missing_fields"): ?><div class="status-msg msg-warning">⚠ Please select all required fields.</div><?php endif; ?>
                    <?php if($message == "invalid_request"): ?><div class="status-msg msg-warning">⚠ The request could not be verified. Please refresh the page and try again.</div><?php endif; ?>
                    <?php if($message == "invalid_assignment"): ?><div class="status-msg msg-warning">⚠ This assignment is no longer available to edit.</div><?php endif; ?>
                    <?php if($message == "invalid_faculty"): ?><div class="status-msg msg-warning">⚠ Select a valid faculty member or adviser.</div><?php endif; ?>
                    <?php if($message == "duplicate_assignment"): ?><div class="status-msg msg-warning">⚠ This subject is already assigned to the selected section and term.</div><?php endif; ?>
                    <?php if($message == "error"): ?><div class="status-msg msg-warning">⚠ The assignment could not be saved. Please try again.</div><?php endif; ?>
                    <?php if($message == "invalid_section"): ?><div class="status-msg msg-warning">⚠ Select a section that belongs to the chosen academic year.</div><?php endif; ?>
                    <?php if($message == "invalid_strand"): ?><div class="status-msg msg-warning">⚠ Select a valid subject strand.</div><?php endif; ?>
                    <?php if($message == "invalid_term"): ?><div class="status-msg msg-warning">⚠ Select a valid academic term.</div><?php endif; ?>
                    <?php if($message == "invalid_subject"): ?><div class="status-msg msg-warning">⚠ Select a subject that matches the target section's curriculum, grade, strand, and term.</div><?php endif; ?>
                    <?php if(($_GET['msg'] ?? '') === 'archived'): ?><div class="status-msg msg-success">✔ Assignment archived and sent to the super admin for review.</div><?php endif; ?>
                    <?php if(($_GET['msg'] ?? '') === 'archive_failed'): ?><div class="status-msg msg-warning">⚠ The assignment could not be archived. Please try again.</div><?php endif; ?>

                    <form id="assignmentForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="assignment_id" id="assignmentId" value="">
                        <div class="form-group">
                            <label>Academic Year</label>
                            <select name="academic_year" id="assignmentAcademicYear" required>
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
                            <select name="faculty_id" id="assignmentFaculty" required>
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
                                <?php foreach ($assignment_sections as $sec): ?>
                                    <option
                                        value="<?= $sec['id']; ?>"
                                        data-academic-year="<?= htmlspecialchars($sec['academic_year'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-grade="<?= htmlspecialchars((string) $sec['grade_level']); ?>"
                                        data-strand-id="<?= htmlspecialchars((string) ($sec['strand_id'] ?? '')); ?>"
                                        data-curriculum="<?= htmlspecialchars($sec['curriculum_version']); ?>"
                                        <?= $sec['academic_year'] !== $active_ay ? 'hidden disabled' : ''; ?>
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

                        <button type="submit" name="save_assignment" id="assignmentSubmit" class="btn-primary">Assign Teaching Load</button>
                        <button type="button" id="cancelAssignmentEdit" class="btn-primary" style="display: none; margin-top: 8px; background: #64748b;">Cancel Edit</button>
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

                    <table class="assignments-table">
                        <colgroup>
                            <col style="width: 15%;">
                            <col style="width: 17%;">
                            <col style="width: 31%;">
                            <col style="width: 13%;">
                            <col style="width: 24%;">
                        </colgroup>
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; background: #f8fafc;">
                                <th style="padding: 10px;">Teacher</th>
                                <th style="padding: 10px;">Section</th>
                                <th style="padding: 10px;">Subject & Weights</th>
                                <th style="padding: 10px;">Term</th>
                                <th style="padding: 10px; text-align: center;">Actions</th>
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
                                            <div class="assignment-actions">
                                                <button
                                                    type="button"
                                                    class="assignment-action-btn assignment-action-edit"
                                                    data-edit-assignment
                                                    data-assignment-id="<?= (int) $row['id'] ?>"
                                                    data-faculty-id="<?= (int) $row['faculty_id'] ?>"
                                                    data-section-id="<?= (int) $row['section_id'] ?>"
                                                    data-subject-id="<?= (int) $row['subject_id'] ?>"
                                                    data-subject-strand-id="<?= htmlspecialchars((string) ($row['subject_strand_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-academic-year="<?= htmlspecialchars($row['academic_year'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-semester="<?= htmlspecialchars($row['semester'], ENT_QUOTES, 'UTF-8') ?>"
                                                >Edit</button>
                                                <form method="POST" action="archive_resource.php" data-confirm-title="Confirm Archive" data-confirm-label="Archive" data-confirm-variant="danger" data-confirm-message="Archive this teaching assignment? The super admin can restore or permanently delete it.">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="resource_type" value="assignment">
                                                    <input type="hidden" name="resource_id" value="<?= (int) $row['id'] ?>">
                                                    <input type="hidden" name="academic_year" value="<?= htmlspecialchars($active_ay, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="semester" value="<?= htmlspecialchars($active_sem, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="filter_section" value="<?= (int) $filter_section ?>">
                                                    <button type="submit" class="assignment-action-btn assignment-action-archive">Archive</button>
                                                </form>
                                            </div>
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

    <script src="../../assets/js/admin-form-confirmation.js"></script>
    <script>
        const sectionSelect = document.getElementById('targetSection');
        const strandSelect = document.getElementById('subjectStrand');
        const semesterSelect = document.getElementById('assignmentSemester');
        const academicYearSelect = document.getElementById('assignmentAcademicYear');
        const subjectSelect = document.getElementById('targetSubject');
        const subjectFilterHint = document.getElementById('subjectFilterHint');
        const assignmentForm = document.getElementById('assignmentForm');
        const assignmentTitle = document.getElementById('assignmentFormTitle');
        const assignmentSubmit = document.getElementById('assignmentSubmit');
        const cancelEditButton = document.getElementById('cancelAssignmentEdit');

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

        function filterSectionsForAcademicYear() {
            let selectedSectionIsAvailable = false;
            Array.from(sectionSelect.options).forEach(option => {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                const matchesYear = option.dataset.academicYear === academicYearSelect.value;
                option.hidden = !matchesYear;
                option.disabled = !matchesYear;
                if (matchesYear && option.value === sectionSelect.value) {
                    selectedSectionIsAvailable = true;
                }
            });

            if (!selectedSectionIsAvailable) {
                sectionSelect.value = '';
                strandSelect.value = '';
            }
            filterSubjectsForSection();
        }

        sectionSelect.addEventListener('change', function () {
            const sectionOption = sectionSelect.options[sectionSelect.selectedIndex];
            strandSelect.value = sectionOption?.dataset.strandId || '';
            filterSubjectsForSection();
        });
        academicYearSelect.addEventListener('change', filterSectionsForAcademicYear);
        strandSelect.addEventListener('change', filterSubjectsForSection);
        semesterSelect.addEventListener('change', filterSubjectsForSection);
        filterSectionsForAcademicYear();
        filterSubjectsForSection();
        cancelEditButton.addEventListener('click', resetAssignmentForm);

        document.querySelectorAll('[data-edit-assignment]').forEach(button => {
            button.addEventListener('click', () => {
                document.getElementById('assignmentId').value = button.dataset.assignmentId;
                academicYearSelect.value = button.dataset.academicYear;
                filterSectionsForAcademicYear();
                semesterSelect.value = button.dataset.semester;
                document.getElementById('assignmentFaculty').value = button.dataset.facultyId;
                sectionSelect.value = button.dataset.sectionId;

                const sectionOption = sectionSelect.options[sectionSelect.selectedIndex];
                strandSelect.value = button.dataset.subjectStrandId || sectionOption?.dataset.strandId || '';
                filterSubjectsForSection();
                subjectSelect.value = button.dataset.subjectId;

                assignmentTitle.textContent = 'Edit Subject Assignment';
                assignmentSubmit.textContent = 'Save Assignment Changes';
                cancelEditButton.style.display = 'block';
                assignmentForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        function resetAssignmentForm() {
            assignmentForm.reset();
            document.getElementById('assignmentId').value = '';
            assignmentTitle.textContent = 'Assign Subject Load';
            assignmentSubmit.textContent = 'Assign Teaching Load';
            cancelEditButton.style.display = 'none';
            filterSectionsForAcademicYear();
        }

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