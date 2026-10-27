<?php
session_start();

// 1. Correct Path Resolution using __DIR__
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/EcrTermLock.php';

// Instantiate PDO connection
$pdo = getDBConnection();

// 2. Access Control: Ensure only Faculty or Advisers can enter
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    header("Location: ../../manifest/login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Variables for dynamic navigation sidebar
$nav_assignment_param = !empty($assignment_id) ? "?assignment_id={$assignment_id}" : "";
$nav_term_param = !empty($assignment_id) 
    ? "?assignment_id={$assignment_id}&term=" . ($term ?? 1) 
    : "?term=" . ($term ?? 1);

$user_role_clean = strtolower($_SESSION['role'] ?? 'faculty');
$is_adviser_or_admin = in_array($user_role_clean, ['adviser', 'admin'], true);
$current_page = basename($_SERVER['PHP_SELF']);

// 3. Query Selected Term & Assignment
$term = isset($_GET['term']) ? (int)$_GET['term'] : 1;
if (!in_array($term, [1, 2, 3])) { $term = 1; }

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

// 4. Fetch All Active Teaching Assignments for Faculty
$assignments_stmt = $pdo->prepare("
    SELECT sa.id AS assignment_id, sub.subject_name, sub.subject_code, sub.type AS subject_type,
           sec.id AS section_id, sec.section_name, sec.grade_level, st.strand_code, st.strand_name
    FROM subject_assignments sa
    JOIN subjects sub ON sa.subject_id = sub.id
    JOIN sections sec ON sa.section_id = sec.id
    LEFT JOIN strands st ON sec.strand_id = st.id
    WHERE sa.faculty_id = :faculty_id
    ORDER BY sec.grade_level ASC, sec.section_name ASC, sub.subject_name ASC
");
$assignments_stmt->execute(['faculty_id' => $user_id]);
$my_assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($assignment_id === 0 && !empty($my_assignments)) {
    $assignment_id = (int)$my_assignments[0]['assignment_id'];
}

// 5. Extract Active Assignment Data
$active_assignment = null;
foreach ($my_assignments as $assign) {
    if ((int)$assign['assignment_id'] === $assignment_id) {
        $active_assignment = $assign;
        break;
    }
}

$section_id = $active_assignment['section_id'] ?? 0;
$strand_identifier = ($active_assignment['strand_code'] ?? '') . ' ' . ($active_assignment['strand_name'] ?? '');
$is_tech_track = (stripos($strand_identifier, 'TVL') !== false || stripos($strand_identifier, 'ICT') !== false);

// 6. Fetch Teacher Name
$teacher_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :user_id");
$teacher_stmt->execute(['user_id' => $user_id]);
$teacher_name =$teacher_stmt->fetchColumn() ?: 'Unassigned Teacher';

// 7. Fetch Active Student Roster by Gender for Selected Assignment Section
$male_students = [];$female_students = [];

if ($section_id > 0) {
    // Fetch Male Roster
    $m_stmt =$pdo->prepare("
        SELECT u.id, u.username AS lrn, u.full_name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND (sp.gender = 'Male' OR sp.gender IS NULL)
        ORDER BY u.full_name ASC
    ");
    $m_stmt->execute(['section_id' =>$section_id]);
    $male_students =$m_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Female Roster
    $f_stmt =$pdo->prepare("
        SELECT u.id, u.username AS lrn, u.full_name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND sp.gender = 'Female'
        ORDER BY u.full_name ASC
    ");
    $f_stmt->execute(['section_id' =>$section_id]);
    $female_students =$f_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 8. Fetch Configured Assessment Counts & Scores from DB
$term_str = "Term " . $term;
$db_components = ['WW' => [], 'PT' => [], 'QA' => []];

if ($assignment_id > 0) {
    $comp_stmt =$pdo->prepare("
        SELECT category, task_number, highest_possible_score
        FROM ecr_assessment_components
        WHERE assignment_id = :assignment_id AND term = :term
        ORDER BY category ASC, task_number ASC
    ");
    $comp_stmt->execute(['assignment_id' => $assignment_id, 'term' =>$term_str]);
    $rows =$comp_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as$r) {
        $db_components[$r['category']][$r['task_number']] =$r['highest_possible_score'];
    }
}

$ww_count = !empty($db_components['WW']) ? count($db_components['WW']) : 8;
$pt_count = !empty($db_components['PT']) ? count($db_components['PT']) : 6;
$qa_count = !empty($db_components['QA']) ? count($db_components['QA']) : 1;
$term_is_locked = false;

if ($assignment_id > 0) {
    $term_is_locked = isEcrTermLocked($pdo, $assignment_id, $term_str);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECR Input Data | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow: hidden;
        }

        .dashboard-wrapper { 
            display: flex; 
            height: 100vh; 
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        main.content { 
            flex-grow: 1;
            margin-left: 260px; 
            padding: 15px; 
            width: 100%; 
            max-width: 100% !important;
            height: 100vh; 
            box-sizing: border-box; 
            display: flex; 
            flex-direction: column; 
            overflow-y: auto; 
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #sidebar:not(.active) ~ main.content {
            margin-left: 0;
            width: 100%;
        }

        .ecr-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .card-title {
            background-color: #0f3854;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            text-transform: uppercase;
        }

        .card-content {
            padding: 20px;
        }

        .field-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            gap: 12px;
        }

        .field-group label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            flex: 1;
            white-space: normal;
            word-break: break-word;
        }

        .form-input, .form-select {
            width: 55%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #ffffff;
            font-size: 0.85rem;
            outline: none;
            box-sizing: border-box;
        }

        .form-input[readonly] {
            background-color: #f8fafc;
        }

        /* Interactive Counter Controls */
        .counter-box {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-count {
            width: 30px;
            height: 30px;
            background: #e2e8f0;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }

        .btn-count:hover {
            background: #cbd5e1;
        }

        .form-input-count {
            width: 45px !important;
            text-align: center;
            font-weight: 700;
            padding: 4px !important;
            background: #ffffff !important;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .btn-action {
            background-color: #2563eb;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-action:hover {
            background-color: #1d4ed8;
        }

        .input-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 20px;
        }

        .gender-block {
            margin-bottom: 20px;
        }

        .gender-tag {
            font-weight: bold;
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            display: inline-block;
        }

        .male-tag { background: #dbeafe; color: #1e40af; }
        .female-tag { background: #fce7f3; color: #9d174d; }

        .roster-entry {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .entry-index {
            font-weight: bold;
            font-size: 0.85rem;
            width: 25px;
            color: #64748b;
        }

        .roster-field {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #f8fafc;
            font-size: 0.85rem;
        }

        .btn-proceed {
            background-color: #2563eb;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: background 0.2s ease;
        }

        .btn-proceed:hover {
            background-color: #1d4ed8;
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
        }

        .sidebar {
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        @media (max-width: 992px) {
            main.content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 15px;
            }

            .input-layout {
                grid-template-columns: 1fr;
            }

            .field-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .form-input, .form-select {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>

    <div class="dashboard-wrapper">
        <!-- Dynamic Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li>
                    <a href="faculty_dashboard.php" class="<?= $current_page == 'faculty_dashboard.php' ? 'active' : '' ?>">
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="faculty_schedule.php" class="<?= $current_page == 'faculty_schedule.php' ? 'active' : '' ?>">
                        My Schedule
                    </a>
                </li>
                <li>
                    <a href="ecr-inputdata.php<?= $nav_assignment_param ?>" class="<?= $current_page == 'ecr-inputdata.php' ? 'active' : '' ?>">
                        ECR Setup &amp; Roster
                    </a>
                </li>
                <li>
                    <a href="ecr-view.php<?= $nav_term_param ?>" class="<?= $current_page == 'ecr-view.php' ? 'active' : '' ?>">
                        Encode Term Grades
                    </a>
                </li>
                <li>
                    <a href="ecr-summary.php<?= $nav_assignment_param ?>" class="<?= $current_page == 'ecr-summary.php' ? 'active' : '' ?>">
                        ECR Summary
                    </a>
                </li>

                <?php if ($is_adviser_or_admin): ?>
                    <!-- Official DepEd Advisory Forms Section -->
                    <li class="menu-divider" style="padding: 12px 16px 4px 16px; font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">
                        Advisory Forms
                    </li>
                    <li>
                        <a href="sf9-reportcard.php<?= $nav_assignment_param ?>" class="<?= $current_page == 'sf9-reportcard.php' ? 'active' : '' ?>">
                            SF9 (Report Card)
                        </a>
                    </li>
                    <li>
                        <a href="sf10-record.php<?= $nav_assignment_param ?>" class="<?= $current_page == 'sf10-record.php' ? 'active' : '' ?>">
                            SF10 (Permanent Record)
                        </a>
                    </li>
                <?php endif; ?>
                <li><a href="../auth/settings.php" class="nav-link">
                    <i class="icon-shield"></i> Security Settings
                        </a></li>

                <li>
                    <a href="../../manifest/logout.php" class="logout">Logout</a>
                </li>
            </ul>
        </nav>

        <main class="content">
            <div class="ecr-container">
                <header class="ecr-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <img src="../../assets/icons/kagawaran-logo.png" alt="Kagawaran Logo" style="max-height: 50px;">
                    <div class="header-text" style="text-align: center;">
                        <h2 style="margin: 0; color: #0f172a; font-size: 1.25rem;">Input Data Sheet for Electronic-Class Record (ECR)</h2>
                        <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.85rem;">Strengthened Senior High School System - SY 2026-2027</p>
                    </div>
                    <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" style="max-height: 50px;">
                </header>

                <div class="input-layout">
                    <!-- Left Column: School & Subject Assignment Configuration -->
                    <div class="config-column">
                        
                        <!-- Dynamic Assignment Selector -->
                        <div class="ecr-card">
                            <div class="card-title">SUBJECT LOAD & TERM SELECTOR</div>
                            <div class="card-content">
                                <div class="field-group">
                                    <label for="assignSelect">ASSIGNED CLASS:</label>
                                    <select id="assignSelect" class="form-select" onchange="switchAssignment(this.value)">
                                        <?php if (empty($my_assignments)): ?>
                                            <option value="0">No active subject load assigned</option>
                                        <?php else: ?>
                                            <?php foreach ($my_assignments as$assign): ?>
                                                <option value="<?= $assign['assignment_id'] ?>" <?= $assign['assignment_id'] ==$assignment_id ? 'selected' : '' ?>>
                                                    Grade <?= htmlspecialchars($assign['grade_level'] . ' - ' . $assign['section_name']) ?> | <?= htmlspecialchars($assign['subject_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label for="termSelect">ACADEMIC TERM:</label>
                                    <select id="termSelect" class="form-select" onchange="switchTerm(this.value)">
                                        <option value="1" <?= $term === 1 ? 'selected' : '' ?>>Term 1 (First Quarter)</option>
                                        <option value="2" <?= $term === 2 ? 'selected' : '' ?>>Term 2 (Second Quarter)</option>
                                        <option value="3" <?= $term === 3 ? 'selected' : '' ?>>Term 3 (Third Quarter)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Class & Subject Info Card -->
                        <div class="ecr-card">
                            <div class="card-title">CLASS INFO</div>
                            <div class="card-content">
                                <div class="field-group">
                                    <label>TEACHER:</label>
                                    <input type="text" value="<?= htmlspecialchars($teacher_name) ?>" class="form-input" readonly>
                                </div>
                                <div class="field-group">
                                    <label>SUBJECT TYPE:</label>
                                    <input type="text" value="<?= htmlspecialchars($active_assignment['subject_type'] ?? 'Core') ?>" class="form-input" readonly>
                                </div>
                                <div class="field-group">
                                    <label>TRACK / STRAND:</label>
                                    <input type="text" value="<?= $is_tech_track ? 'TVL / ICT TRACK' : 'ACADEMIC TRACK' ?>" class="form-input" readonly>
                                </div>
                                <div class="field-group">
                                    <label>SECTION:</label>
                                    <input type="text" value="<?= htmlspecialchars(($active_assignment['grade_level'] ?? '') ? 'Grade ' . $active_assignment['grade_level'] . ' - ' .$active_assignment['section_name'] : 'Unassigned') ?>" class="form-input" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Interactive Assessment Setup Card -->
                        <div class="ecr-card">
                            <div class="card-title">ASSESSMENT SETUP (TERM <?= $term ?>)</div>
                            <div class="card-content">
                                <?php if ($term_is_locked): ?>
                                    <p role="status" style="padding: 10px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px;">
                                        This term has already been saved. Its assessment setup cannot be changed.
                                    </p>
                                <?php endif; ?>
                                <?php if (isset($_GET['saved'])): ?>
                                    <p role="status" style="padding: 10px; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;">
                                        Assessment columns updated. They are now reflected in the ECR for this term.
                                    </p>
                                <?php elseif (isset($_GET['error'])): ?>
                                    <?php
                                    $setup_errors = [
                                        'assignment_invalid' => 'The selected subject load could not be verified. Please select your assigned class again.',
                                        'invalid_count' => 'The assessment counts are invalid. Please use the available controls and try again.',
                                        'term_locked' => 'This term has already been saved. Its assessment setup cannot be changed.',
                                        'save_failed' => 'The assessment setup could not be saved. No changes were applied; please try again or contact the system administrator.'
                                    ];
                                    $setup_error = $setup_errors[$_GET['error']] ?? null;
                                    ?>
                                    <?php if ($setup_error !== null): ?>
                                        <p role="alert" style="padding: 10px; color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px;">
                                            <?= htmlspecialchars($setup_error, ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <form id="assessmentSetupForm" action="save-assessment-setup.php" method="POST">
                                    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                                    <input type="hidden" name="term" value="Term <?= $term ?>">

                                    <!-- Written Works Row -->
                                    <div class="field-group">
                                        <label>WRITTEN WORKS (WW):</label>
                                        <div class="counter-box">
                                            <button type="button" class="btn-count" onclick="adjustCount('ww_count', -1)" <?= $term_is_locked ? 'disabled' : '' ?>>-</button>
                                            <input type="number" id="ww_count" name="ww_count" min="1" max="10" value="<?= $ww_count ?>" class="form-input-count" readonly>
                                            <button type="button" class="btn-count" onclick="adjustCount('ww_count', 1)" <?= $term_is_locked ? 'disabled' : '' ?>>+</button>
                                        </div>
                                    </div>

                                    <!-- Performance Tasks Row -->
                                    <div class="field-group">
                                        <label>PERFORMANCE TASKS (PT):</label>
                                        <div class="counter-box">
                                            <button type="button" class="btn-count" onclick="adjustCount('pt_count', -1)" <?= $term_is_locked ? 'disabled' : '' ?>>-</button>
                                            <input type="number" id="pt_count" name="pt_count" min="1" max="10" value="<?= $pt_count ?>" class="form-input-count" readonly>
                                            <button type="button" class="btn-count" onclick="adjustCount('pt_count', 1)" <?= $term_is_locked ? 'disabled' : '' ?>>+</button>
                                        </div>
                                    </div>

                                    <!-- Quarterly Exam Row -->
                                    <div class="field-group">
                                        <label>QUARTERLY EXAM (QA):</label>
                                        <div class="counter-box">
                                            <button type="button" class="btn-count" onclick="adjustCount('qa_count', -1)" <?= $term_is_locked ? 'disabled' : '' ?>>-</button>
                                            <input type="number" id="qa_count" name="qa_count" min="1" max="3" value="<?= $qa_count ?>" class="form-input-count" readonly>
                                            <button type="button" class="btn-count" onclick="adjustCount('qa_count', 1)" <?= $term_is_locked ? 'disabled' : '' ?>>+</button>
                                        </div>
                                    </div>

                                    <div style="margin-top: 15px; text-align: right;">
                                        <button type="submit" class="btn-action" <?= $term_is_locked ? 'disabled' : '' ?>>Update Columns</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Dynamic Student Roster -->
                    <div class="roster-column">
                        <div class="ecr-card">
                            <div class="card-title">ENROLLED LEARNERS' ROSTER</div>
                            <div class="card-content">
                                
                                <!-- Male Section -->
                                <div class="gender-block">
                                    <div class="gender-tag male-tag">MALE LEARNERS (<?= count($male_students) ?> Enrolled)</div>
                                    <div class="roster-list">
                                        <?php if (!empty($male_students)): ?>
                                            <?php foreach ($male_students as $idx =>$student): ?>
                                                <div class="roster-entry">
                                                    <span class="entry-index"><?= $idx + 1 ?></span>
                                                    <input type="text" value="<?= htmlspecialchars($student['full_name']) ?> (LRN: <?= htmlspecialchars($student['lrn']) ?>)" class="roster-field" readonly>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p style="color: #94a3b8; font-size: 0.85rem; margin: 0 0 15px 0;">No male learners enrolled in this section.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Female Section -->
                                <div class="gender-block">
                                    <div class="gender-tag female-tag">FEMALE LEARNERS (<?= count($female_students) ?> Enrolled)</div>
                                    <div class="roster-list">
                                        <?php if (!empty($female_students)): ?>
                                            <?php foreach ($female_students as $idx =>$student): ?>
                                                <div class="roster-entry">
                                                    <span class="entry-index"><?= $idx + 1 ?></span>
                                                    <input type="text" value="<?= htmlspecialchars($student['full_name']) ?> (LRN: <?= htmlspecialchars($student['lrn']) ?>)" class="roster-field" readonly>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">No female learners enrolled in this section.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div style="margin-top: 20px; text-align: right;">
                            <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=<?= $term ?>" class="btn-proceed">
                                Proceed to Class Hub & Grade Encoding (Term <?= $term ?>) →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JS Assets -->
    <script src="../../assets/js/app.js"></script>
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

        function switchAssignment(assignmentId) {
            if (assignmentId > 0) {
                window.location.href = `ecr-inputdata.php?assignment_id=${assignmentId}&term=<?= $term ?>`;
            }
        }

        function switchTerm(term) {
            window.location.href = `ecr-inputdata.php?assignment_id=<?= $assignment_id ?>&term=${term}`;
        }

        function adjustCount(inputId, change) {
            const input = document.getElementById(inputId);
            let val = parseInt(input.value) + change;
            const min = parseInt(input.getAttribute('min'));
            const max = parseInt(input.getAttribute('max'));

            if (val >= min && val <= max) {
                input.value = val;
            }
        }
    </script>
</body>

</html>