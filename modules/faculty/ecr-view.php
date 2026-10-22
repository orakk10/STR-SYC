<?php
session_start();

// 1. Path Resolution & Database Connection
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/EcrDraftStore.php';
require_once __DIR__ . '/../../core/EcrTermLock.php';
require_once __DIR__ . '/../../core/EcrSubjectTerm.php';
$pdo = getDBConnection();

// 2. Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    header("Location: ../../manifest/login.php");
    exit();
}

ensureEcrDraftTable($pdo);

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 3. Term & Assignment Query Parameters
$term = isset($_GET['term']) ? (int)$_GET['term'] : 1;
if (!in_array($term, [1, 2, 3])) {
    $term = 1;
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

// Variables for dynamic navigation sidebar
$nav_assignment_param = !empty($assignment_id) ? "?assignment_id={$assignment_id}" : "";
$nav_term_param = !empty($assignment_id) 
    ? "?assignment_id={$assignment_id}&term=" . ($term ?? 1) 
    : "?term=" . ($term ?? 1);

$user_role_clean = strtolower($_SESSION['role'] ?? 'faculty');
$is_adviser_or_admin = in_array($user_role_clean, ['adviser', 'admin'], true);
$current_page = basename($_SERVER['PHP_SELF']);

// Helper: DepEd DO 8, s. 2015 Subject Weights Mapper
function getComponentWeights(?string $type): array {
    switch ($type) {
        case 'Core':
            return ['ww' => 0.25, 'pt' => 0.50, 'qa' => 0.25];
        case 'Applied':
        case 'Specialized':
            return ['ww' => 0.25, 'pt' => 0.45, 'qa' => 0.30];
        default:
            return ['ww' => 0.20, 'pt' => 0.50, 'qa' => 0.30];
    }
}

// 4. Fetch Active Faculty Subject Load
$assignments_stmt = $pdo->prepare("
    SELECT sa.id AS assignment_id, sub.subject_name, sub.subject_code,
           sub.curriculum_version, sub.`term` AS subject_curriculum_term,
           subject_strand.strand_code AS subject_strand_code,
           subject_strand.strand_name AS subject_strand_name,
           sec.section_name, sec.grade_level,
           section_strand.strand_code, section_strand.strand_name
    FROM subject_assignments sa
    JOIN subjects sub ON sa.subject_id = sub.id
    JOIN sections sec ON sa.section_id = sec.id
    LEFT JOIN strands section_strand ON sec.strand_id = section_strand.id
    LEFT JOIN strands subject_strand ON sub.strand_id = subject_strand.id
    WHERE sa.faculty_id = :faculty_id
    ORDER BY sec.grade_level ASC, sec.section_name ASC, sub.subject_name ASC
");
$assignments_stmt->execute(['faculty_id' => $user_id]);
$my_assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($assignment_id === 0 && !empty($my_assignments)) {
    $assignment_id = (int)$my_assignments[0]['assignment_id'];
}

// 5. Fetch Weight Rules & Active Roster Info
$ww_weight    = 0.25;
$pt_weight    = 0.50;
$qa_weight    = 0.25;
$subject_name = "";
$section_id   = 0;
$section_name = "";
$strand_code = "";
$strand_name = "";
$subject_strand_code = "";
$subject_strand_name = "";
$curriculum_version = "";
$subject_curriculum_term = "";
$track_display = "ACADEMIC TRACK";

if ($assignment_id > 0) {
    $weight_stmt = $pdo->prepare("
        SELECT sub.subject_name, sub.type AS subject_type, sub.curriculum_version,
               sub.`term` AS subject_curriculum_term,
               subject_strand.strand_code AS subject_strand_code,
               subject_strand.strand_name AS subject_strand_name,
               sa.section_id, sec.section_name, sec.grade_level,
               section_strand.strand_code, section_strand.strand_name
        FROM subject_assignments sa
        JOIN subjects sub ON sa.subject_id = sub.id
        JOIN sections sec ON sa.section_id = sec.id
        LEFT JOIN strands section_strand ON sec.strand_id = section_strand.id
        LEFT JOIN strands subject_strand ON sub.strand_id = subject_strand.id
        WHERE sa.id = :assignment_id
    ");
    $weight_stmt->execute(['assignment_id' => $assignment_id]);
    $subject_weight_data = $weight_stmt->fetch(PDO::FETCH_ASSOC);

    if ($subject_weight_data) {
        $weights      = getComponentWeights($subject_weight_data['subject_type'] ?? 'Core');
        $ww_weight    = $weights['ww'];
        $pt_weight    = $weights['pt'];
        $qa_weight    = $weights['qa'];
        $subject_name = $subject_weight_data['subject_name'] ?? "";
        $section_id   = (int)$subject_weight_data['section_id'];
        $section_name = "Grade " . $subject_weight_data['grade_level'] . " - " . $subject_weight_data['section_name'];
        $strand_code = trim((string)($subject_weight_data['strand_code'] ?? ''));
        $strand_name = trim((string)($subject_weight_data['strand_name'] ?? ''));
        $subject_strand_code = trim((string)($subject_weight_data['subject_strand_code'] ?? ''));
        $subject_strand_name = trim((string)($subject_weight_data['subject_strand_name'] ?? ''));
        $curriculum_version = trim((string)($subject_weight_data['curriculum_version'] ?? ''));
        $subject_curriculum_term = trim((string)($subject_weight_data['subject_curriculum_term'] ?? ''));

        $strand_identifier = $strand_code . ' ' . $strand_name;
        if (stripos($strand_identifier, 'TVL') !== false || stripos($strand_identifier, 'ICT') !== false) {
            $track_display = 'TVL / ICT TRACK';
        }
    }
}
$section_strand_display = trim($strand_code . ' ' . $strand_name);
$subject_strand_display = trim($subject_strand_code . ' ' . $subject_strand_name);
if ($subject_strand_display === '') {
    $subject_strand_display = 'Universal Core / All Tracks';
}

// 6. Dynamic Assessment Components Query (Driven by ecr_assessment_components)
$term_str = "Term " . $term;
$term_is_available = isCurriculumSubjectAvailableForEcrTerm(
    $curriculum_version,
    $subject_curriculum_term,
    $term_str
);
$term_is_read_only = true;
$db_components = ['WW' => [], 'PT' => [], 'QA' => []];
$term_is_locked = false;
$saved_scores = [];
$draft_scores = [];
$draft_saved_at = null;

if ($assignment_id > 0) {
    $default_component_counts = ['WW' => 8, 'PT' => 6, 'QA' => 1];
    $existing_categories_stmt = $pdo->prepare("
        SELECT DISTINCT category
        FROM ecr_assessment_components
        WHERE assignment_id = :assignment_id AND term = :term
    ");
    $existing_categories_stmt->execute([
        'assignment_id' => $assignment_id,
        'term' => $term_str
    ]);
    $existing_categories = $existing_categories_stmt->fetchAll(PDO::FETCH_COLUMN);

    $insert_component_stmt = $pdo->prepare("
        INSERT INTO ecr_assessment_components
            (assignment_id, term, category, task_number, highest_possible_score)
        VALUES (:assignment_id, :term, :category, :task_number, 100.00)
    ");
    if ($term_is_available) {
        foreach ($default_component_counts as $category => $default_count) {
            if (in_array($category, $existing_categories, true)) {
                continue;
            }

            for ($task_number = 1; $task_number <= $default_count; $task_number++) {
                $insert_component_stmt->execute([
                    'assignment_id' => $assignment_id,
                    'term' => $term_str,
                    'category' => $category,
                    'task_number' => $task_number
                ]);
            }
        }
    }

    $components_stmt = $pdo->prepare("
        SELECT id, category, task_number, highest_possible_score 
        FROM ecr_assessment_components 
        WHERE assignment_id = :assignment_id AND term = :term
        ORDER BY category ASC, task_number ASC
    ");
    $components_stmt->execute([
        'assignment_id' => $assignment_id,
        'term' => $term_str
    ]);
    $raw_components = $components_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_components as $comp) {
        if (isset($db_components[$comp['category']])) {
            $db_components[$comp['category']][$comp['task_number']] = $comp;
        }
    }

    $saved_scores_stmt = $pdo->prepare("
        SELECT scores.component_id, scores.student_id, scores.score
        FROM ecr_student_scores scores
        JOIN ecr_assessment_components components ON components.id = scores.component_id
        WHERE components.assignment_id = :assignment_id AND components.term = :term
    ");
    $saved_scores_stmt->execute([
        'assignment_id' => $assignment_id,
        'term' => $term_str
    ]);
    foreach ($saved_scores_stmt->fetchAll(PDO::FETCH_ASSOC) as $saved_score) {
        $saved_scores[$saved_score['student_id']][$saved_score['component_id']] = $saved_score['score'];
    }

    $term_is_locked = isEcrTermLocked($pdo, $assignment_id, $term_str);
    $term_is_read_only = $term_is_locked || !$term_is_available;

    if (!$term_is_read_only) {
        $draft_stmt = $pdo->prepare("
            SELECT draft_data, updated_at
            FROM ecr_score_drafts
            WHERE user_id = :user_id AND assignment_id = :assignment_id AND term = :term
        ");
        $draft_stmt->execute([
            'user_id' => (int)$user_id,
            'assignment_id' => $assignment_id,
            'term' => $term_str
        ]);
        $draft = $draft_stmt->fetch(PDO::FETCH_ASSOC);
        if ($draft) {
            $draft_data = json_decode($draft['draft_data'], true);
            if (is_array($draft_data) && isset($draft_data['scores']) && is_array($draft_data['scores'])) {
                foreach ($draft_data['scores'] as $draft_score) {
                    if (isset($draft_score['student_id'], $draft_score['component_id'], $draft_score['score'])) {
                        $draft_scores[(int)$draft_score['student_id']][(int)$draft_score['component_id']] = (string)$draft_score['score'];
                    }
                }
                $draft_saved_at = $draft['updated_at'];
            }
        }
    }
}

// Fallback lists if no assessment components exist yet for this term
$ww_list = !empty($db_components['WW']) ? $db_components['WW'] : array_fill(1, 8, ['highest_possible_score' => 100]);
$pt_list = !empty($db_components['PT']) ? $db_components['PT'] : array_fill(1, 6, ['highest_possible_score' => 100]);
$qa_list = !empty($db_components['QA']) ? $db_components['QA'] : array_fill(1, 1, ['highest_possible_score' => 100]);

// Total table column span calculation for grouping headers
$total_grid_columns = 1 + count($ww_list) + 2 + count($pt_list) + 2 + count($qa_list) + 2 + 4;

// 7. Fetch Section Students (Split by Gender)
$males   = [];
$females = [];

if ($section_id > 0) {
    $m_stmt = $pdo->prepare("
        SELECT u.id, u.full_name AS name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND (sp.gender = 'Male' OR sp.gender IS NULL)
        ORDER BY u.full_name ASC
    ");
    $m_stmt->execute(['section_id' => $section_id]);
    $males = $m_stmt->fetchAll(PDO::FETCH_ASSOC);

    $f_stmt = $pdo->prepare("
        SELECT u.id, u.full_name AS name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND sp.gender = 'Female'
        ORDER BY u.full_name ASC
    ");
    $f_stmt->execute(['section_id' => $section_id]);
    $females = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encode Term <?= $term ?> Grades | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            background-color: #f8fafc; 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            color: #1e293b;
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
            overflow: hidden; 
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #sidebar.active ~ main.content { 
            margin-left: 260px; 
            width: calc(100% - 260px); 
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

        .ecr-card-master {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        .ecr-header-toolbar {
            padding-left: 40px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
        }

        .header-title-group h1 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0f172a;
        }

        .subject-badge { 
            background-color: #eff6ff; 
            color: #1d4ed8; 
            padding: 3px 10px; 
            border-radius: 20px; 
            font-weight: 600; 
            font-size: 0.78rem; 
            border: 1px solid #bfdbfe;
        }

        .btn-action { 
            background-color: #2563eb; 
            color: #ffffff; 
            padding: 8px 16px; 
            border-radius: 6px; 
            border: none; 
            font-weight: 600; 
            font-size: 0.82rem;
            line-height: 1.2;
            cursor: pointer; 
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .btn-action:hover { 
            background-color: #1d4ed8; 
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
            color: #0f172a;
            box-shadow: none;
        }

        .ecr-control-strip {
            padding: 10px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .selector-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .selector-box label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
        }

        .selector-box select {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 0.82rem;
            color: #0f172a;
            outline: none;
            background: #ffffff;
        }

        .control-action-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .roster-info-pill {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
        }

        .roster-info-pill span {
            color: #0f172a;
        }

        .assignment-context {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 18px;
            width: 100%;
            color: #64748b;
            font-size: 0.8rem;
        }

        .assignment-context strong {
            color: #0f172a;
        }

        .ecr-tab-bar {
            display: flex;
            background: #f8fafc;
            padding: 0 20px;
            border-bottom: 1px solid #e2e8f0;
            gap: 4px;
        }

        .tab-link {
            padding: 10px 18px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .tab-link:hover {
            color: #2563eb;
        }

        .tab-link.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
            background: #ffffff;
        }

        .table-viewport-container {
            flex: 1;
            overflow: auto;
            position: relative;
            background: #ffffff;
        }

        table.ecr-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }

        table.ecr-grid th, 
        table.ecr-grid td {
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            vertical-align: middle;
            box-sizing: border-box;
        }

        table.ecr-grid thead th {
            position: sticky;
            top: 0;
            z-index: 20;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            padding: 6px 2px;
        }

        .th-ww { background: #f0f9ff; color: #0369a1; }
        .th-pt { background: #f0fdf4; color: #15803d; }
        .th-qa { background: #fefce8; color: #a16207; }
        .th-calc { background: #f8fafc; color: #475569; font-weight: 600; }
        .th-final { background: #f1f5f9; color: #0f172a; }

        table.ecr-grid thead tr.hps-row th {
            top: 29px;
            background: #ffffff;
        }

        .score-input, .hps-input { 
            width: 48px; 
            height: 28px;
            text-align: center; 
            font-size: 0.8rem; 
            font-weight: 600;
            border: 1px solid #cbd5e1; 
            border-radius: 4px; 
            outline: none; 
            box-sizing: border-box;
            background-color: #ffffff;
            color: #0f172a;
            padding-right: 2px;
            transition: border-color 0.15s ease;
        }

        .score-input:hover, .hps-input:hover {
            border-color: #94a3b8;
        }

        .score-input:focus, .hps-input:focus { 
            border-color: #2563eb; 
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2); 
            background-color: #ffffff; 
        }

        .col-sticky-name { 
            position: sticky; 
            left: 0; 
            background-color: #ffffff; 
            z-index: 30; 
            width: 180px;
            min-width: 180px;
            max-width: 180px;
            text-align: left !important;
            padding: 6px 12px !important;
            box-shadow: 2px 0 4px rgba(0,0,0,0.04); 
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 600;
            color: #1e293b;
        }

        thead .col-sticky-name {
            z-index: 40 !important;
            background-color: #f8fafc !important;
            color: #475569;
        }

        tr.group-header td {
            background: #f1f5f9;
            font-weight: 700;
            color: #475569;
            text-align: left !important;
            padding: 6px 12px !important;
            font-size: 0.72rem;
            letter-spacing: 0.05em;
        }

        tr.student-row:hover td {
            background-color: #f8fafc;
        }

        tr.student-row:hover .col-sticky-name {
            background-color: #f8fafc;
        }

        .badge-remark {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .badge-remark.passed {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-remark.failed {
            background: #fee2e2;
            color: #b91c1c;
        }

        .cell-calc {
            background-color: #fafafa;
            color: #64748b;
            font-size: 0.72rem;
        }

        .cell-transmuted {
            font-weight: 700;
            color: #2563eb;
            background-color: #eff6ff;
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

        @media (max-width: 1024px) {
            body { overflow: auto; }
            
            main.content { 
                margin-left: 0 !important; 
                width: 100%; 
                height: auto; 
                overflow: visible;
                padding: 15px;
            }

            .table-container { 
                overflow-y: visible; 
                max-height: none; 
            }

            #sidebar.active .sidebar-close {
                display: block;
            }
        }

        #sidebar:not(.active) ~ main.content { 
            margin-left: 0; 
            width: 100%; 
        }
    </style>
</head>

<body>
    <input type="hidden" id="assignment_id" value="<?= $assignment_id ?>">
    <input type="hidden" id="active_term" value="Term <?= $term ?>">
    <input type="hidden" id="weight_ww" value="<?= $ww_weight ?>">
    <input type="hidden" id="weight_pt" value="<?= $pt_weight ?>">
    <input type="hidden" id="weight_qa" value="<?= $qa_weight ?>">

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

                <li>
                    <a href="../../manifest/logout.php" class="logout">Logout</a>
                </li>
            </ul>
        </nav>

        <main class="content">
            <div class="ecr-card-master">
                
                <header class="ecr-header-toolbar">
                    <div class="header-title-group">
                        <h1>
                            SHS Class Record — Term <?= $term ?>
                            <?php if ($subject_name): ?>
                                <span class="subject-badge"><?= htmlspecialchars($subject_name) ?></span>
                            <?php endif; ?>
                        </h1>
                    </div>
                </header>

                <div class="ecr-control-strip">
                    <div class="selector-box">
                        <label for="assignmentSelect">Assigned Subject:</label>
                        <select id="assignmentSelect" onchange="switchSubjectAssignment(this.value)">
                            <?php if (empty($my_assignments)): ?>
                                <option value="0">No teaching subjects assigned</option>
                            <?php else: ?>
                                <?php foreach ($my_assignments as $assign): ?>
                                    <?php
                                    $assignment_available_terms = [];
                                    foreach ([1, 2, 3] as $assignment_term) {
                                        if (isCurriculumSubjectAvailableForEcrTerm(
                                            $assign['curriculum_version'] ?? null,
                                            $assign['subject_curriculum_term'] ?? null,
                                            'Term ' . $assignment_term
                                        )) {
                                            $assignment_available_terms[] = 'Term ' . $assignment_term;
                                        }
                                    }
                                    $assignment_term_label = $assignment_available_terms
                                        ? implode(', ', $assignment_available_terms)
                                        : 'No ECR grade-entry terms';
                                    ?>
                                    <option value="<?= $assign['assignment_id'] ?>" <?= $assign['assignment_id'] == $assignment_id ? 'selected' : '' ?>>
                                        Grade <?= htmlspecialchars($assign['grade_level'] . ' - ' . $assign['section_name']) ?> | <?= htmlspecialchars($assign['subject_name'] . ' (' . $assign['subject_code'] . ')') ?> — <?= htmlspecialchars($assignment_term_label) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>

                        <!-- Action Buttons Beside Assigned Subject Select -->
                        <div class="control-action-buttons">
                            <button id="saveConfigBtn" class="btn-action btn-secondary" <?= $term_is_read_only ? 'disabled' : '' ?>>Save HPS Setup</button>
                            <button type="button" id="saveDraftBtn" class="btn-action btn-secondary" <?= ($term_is_read_only || $assignment_id <= 0) ? 'disabled' : '' ?>>Save Draft</button>
                            <button id="saveScoresBtn" class="btn-action" <?= $term_is_read_only ? 'disabled' : '' ?>><?= $term_is_locked ? 'Term Already Saved' : (!$term_is_available ? 'View Only' : 'Save ECR Scores') ?></button>
                        </div>
                    </div>
                    <span id="draftStatus" role="status" aria-live="polite">
                        <?php if ($draft_saved_at): ?>
                            Draft saved <?= htmlspecialchars(date('M j, Y g:i A', strtotime($draft_saved_at)), ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>

                    <!-- Active Class and Curriculum Information -->
                    <?php if ($section_name): ?>
                        <div class="assignment-context">
                            <span>Active Roster: <strong><?= htmlspecialchars($section_name) ?></strong></span>
                            <span>
                                Track / Strand: <strong><?= htmlspecialchars($track_display) ?></strong>
                                <?php if ($section_strand_display !== ''): ?>
                                    (<?= htmlspecialchars($section_strand_display) ?>)
                                <?php endif; ?>
                            </span>
                            <span>
                                Curriculum: <strong><?= htmlspecialchars($curriculum_version !== '' ? $curriculum_version : 'Not specified') ?></strong>
                                &middot; Curriculum Guide strand:
                                <strong><?= htmlspecialchars($subject_strand_display) ?></strong>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <nav class="ecr-tab-bar">
                    <a href="ecr-inputdata.php" class="tab-link">INPUT DATA</a>
                    <?php foreach ([1, 2, 3] as $tab_term): ?>
                        <?php
                        $tab_available = isCurriculumSubjectAvailableForEcrTerm(
                            $curriculum_version,
                            $subject_curriculum_term,
                            'Term ' . $tab_term
                        );
                        ?>
                        <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=<?= $tab_term ?>" class="tab-link <?= $term === $tab_term ? 'active' : '' ?>">
                            TERM <?= $tab_term ?><?= $tab_available ? '' : ' (VIEW ONLY)' ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="ecr-summary.php?assignment_id=<?= $assignment_id ?>" class="tab-link">SUMMARY</a>
                </nav>

                <div class="table-viewport-container">
                    <?php if ($term_is_locked): ?>
                        <div role="status" style="padding: 10px 20px; color: #92400e; background: #fffbeb; border-bottom: 1px solid #fde68a; font-weight: 600;">
                            <?= htmlspecialchars($term_str) ?> has already been saved. This term is read-only and cannot be entered again.
                        </div>
                    <?php elseif (!$term_is_available && $assignment_id > 0): ?>
                        <div role="status" style="padding: 10px 20px; color: #1e40af; background: #eff6ff; border-bottom: 1px solid #bfdbfe; font-weight: 600;">
                            <?= htmlspecialchars($term_str) ?> is not listed for this subject in the <?= htmlspecialchars($curriculum_version ?: 'selected') ?> curriculum. This ECR is view-only for <?= htmlspecialchars($term_str) ?>.
                        </div>
                    <?php endif; ?>
                    <table class="ecr-grid">
                        <thead>
                            <tr>
                                <th rowspan="2" class="col-sticky-name">LEARNERS' NAMES</th>
                                
                                <!-- Dynamic WW Header Span -->
                                <th colspan="<?= count($ww_list) ?>" class="th-ww">
                                    WRITTEN WORKS (<?= $ww_weight * 100 ?>%)
                                </th>
                                <th colspan="2" class="th-ww th-calc">WW CALC</th>

                                <!-- Dynamic PT Header Span -->
                                <th colspan="<?= count($pt_list) ?>" class="th-pt">
                                    PERFORMANCE TASKS (<?= $pt_weight * 100 ?>%)
                                </th>
                                <th colspan="2" class="th-pt th-calc">PT CALC</th>

                                <!-- Dynamic QA Header Span -->
                                <th colspan="<?= count($qa_list) ?>" class="th-qa">
                                    EXAMS (<?= $qa_weight * 100 ?>%)
                                </th>
                                <th colspan="2" class="th-qa th-calc">QA CALC</th>

                                <th rowspan="2" class="th-final" style="width: 44px;">Initial</th>
                                <th rowspan="2" class="th-final" style="width: 48px;">Trans.</th>
                                <th rowspan="2" class="th-final" style="width: 44px;">Desc.</th>
                                <th rowspan="2" class="th-final" style="width: 54px;">Remarks</th>
                            </tr>

                            <!-- Dynamic HPS Input Row -->
                            <tr class="hps-row">
                                <!-- WW HPS Inputs -->
                                <?php foreach ($ww_list as $task_num => $comp): ?>
                                    <th>
                                        <input type="number" min="1" max="100" class="hps-input" 
                                               data-category="WW" 
                                               data-task-number="<?= $task_num ?>" 
                                               data-component-id="<?= $comp['id'] ?? $task_num ?>"
                                               value="<?= (float)($comp['highest_possible_score'] ?? 100) ?>" 
                                               placeholder="WW<?= $task_num ?>" <?= $term_is_read_only ? 'readonly' : '' ?>>
                                    </th>
                                <?php endforeach; ?>
                                <th class="th-calc" style="width: 26px;">PS</th>
                                <th class="th-calc" style="width: 26px;">WS</th>

                                <!-- PT HPS Inputs -->
                                <?php foreach ($pt_list as $task_num => $comp): ?>
                                    <th>
                                        <input type="number" min="1" max="100" class="hps-input" 
                                               data-category="PT" 
                                               data-task-number="<?= $task_num ?>" 
                                               data-component-id="<?= $comp['id'] ?? ($task_num + 8) ?>"
                                               value="<?= (float)($comp['highest_possible_score'] ?? 100) ?>" 
                                               placeholder="PT<?= $task_num ?>" <?= $term_is_read_only ? 'readonly' : '' ?>>
                                    </th>
                                <?php endforeach; ?>
                                <th class="th-calc" style="width: 26px;">PS</th>
                                <th class="th-calc" style="width: 26px;">WS</th>

                                <!-- QA HPS Inputs -->
                                <?php foreach ($qa_list as $task_num => $comp): ?>
                                    <th>
                                        <input type="number" min="1" max="100" class="hps-input" 
                                               data-category="QA" 
                                               data-task-number="<?= $task_num ?>" 
                                               data-component-id="<?= $comp['id'] ?? ($task_num + 14) ?>"
                                               value="<?= (float)($comp['highest_possible_score'] ?? 100) ?>" 
                                               placeholder="QA<?= $task_num ?>" <?= $term_is_read_only ? 'readonly' : '' ?>>
                                    </th>
                                <?php endforeach; ?>
                                <th class="th-calc" style="width: 26px;">PS</th>
                                <th class="th-calc" style="width: 26px;">WS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- MALE SECTION -->
                            <tr class="group-header">
                                <td colspan="<?= $total_grid_columns ?>">MALE LEARNERS</td>
                            </tr>
                            <?php if (empty($males)): ?>
                                <tr>
                                    <td colspan="<?= $total_grid_columns ?>" style="padding: 10px !important; color: #94a3b8;">No male students registered in this section.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($males as $index => $student): ?>
                                    <tr class="student-row" data-student-id="<?= $student['id'] ?>">
                                        <td class="col-sticky-name" title="<?= htmlspecialchars($student['name']) ?>">
                                            <?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?>
                                        </td>
                                        
                                        <!-- Dynamic Written Works Inputs -->
                                        <?php foreach ($ww_list as $task_num => $comp): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" 
                                                       data-category="WW" 
                                                       data-task-number="<?= $task_num ?>"
                                                       data-component-id="<?= $comp['id'] ?? $task_num ?>" 
                                                       data-student-id="<?= $student['id'] ?>"
                                                       value="<?= htmlspecialchars((string)($draft_scores[$student['id']][$comp['id'] ?? ''] ?? $saved_scores[$student['id']][$comp['id'] ?? ''] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                       <?= $term_is_read_only ? 'readonly' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="ps-cell cell-calc" data-category="WW">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="WW" style="font-weight: 600;">0.00</td>

                                        <!-- Dynamic Performance Tasks Inputs -->
                                        <?php foreach ($pt_list as $task_num =>$comp): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" 
                                                       data-category="PT" 
                                                       data-task-number="<?= $task_num ?>"
                                                       data-component-id="<?= $comp['id'] ?? ($task_num + 8) ?>" 
                                                       data-student-id="<?= $student['id'] ?>"
                                                       value="<?= htmlspecialchars((string)($draft_scores[$student['id']][$comp['id'] ?? ''] ?? $saved_scores[$student['id']][$comp['id'] ?? ''] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                       <?= $term_is_read_only ? 'readonly' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="ps-cell cell-calc" data-category="PT">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="PT" style="font-weight: 600;">0.00</td>

                                        <!-- Dynamic Quarterly Exam Inputs -->
                                        <?php foreach ($qa_list as $task_num =>$comp): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" 
                                                       data-category="QA" 
                                                       data-task-number="<?= $task_num ?>"
                                                       data-component-id="<?= $comp['id'] ?? ($task_num + 14) ?>" 
                                                       data-student-id="<?= $student['id'] ?>"
                                                       value="<?= htmlspecialchars((string)($draft_scores[$student['id']][$comp['id'] ?? ''] ?? $saved_scores[$student['id']][$comp['id'] ?? ''] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                       <?= $term_is_read_only ? 'readonly' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="ps-cell cell-calc" data-category="QA">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="QA" style="font-weight: 600;">0.00</td>

                                        <!-- Transmuted Results -->
                                        <td class="initial-grade-cell cell-calc" style="font-weight: 600;">0.00</td>
                                        <td class="transmuted-grade-cell cell-transmuted">60</td>
                                        <td class="descriptor-cell" style="font-size: 0.68rem; color: #64748b;">DNM</td>
                                        <td>
                                            <span class="remark-cell badge-remark failed">Failed</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- FEMALE SECTION -->
                            <tr class="group-header">
                                <td colspan="<?= $total_grid_columns ?>">FEMALE LEARNERS</td>
                            </tr>
                            <?php if (empty($females)): ?>
                                <tr>
                                    <td colspan="<?= $total_grid_columns ?>" style="padding: 10px !important; color: #94a3b8;">No female students registered in this section.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($females as $index =>$student): ?>
                                    <tr class="student-row" data-student-id="<?= $student['id'] ?>">
                                        <td class="col-sticky-name" title="<?= htmlspecialchars($student['name']) ?>">
                                            <?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?>
                                        </td>
                                        
                                        <!-- Dynamic Written Works Inputs -->
                                        <?php foreach ($ww_list as $task_num =>$comp): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" 
                                                       data-category="WW" 
                                                       data-task-number="<?= $task_num ?>"
                                                       data-component-id="<?= $comp['id'] ?? $task_num ?>" 
                                                       data-student-id="<?= $student['id'] ?>"
                                                       value="<?= htmlspecialchars((string)($draft_scores[$student['id']][$comp['id'] ?? ''] ?? $saved_scores[$student['id']][$comp['id'] ?? ''] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                       <?= $term_is_read_only ? 'readonly' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="ps-cell cell-calc" data-category="WW">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="WW" style="font-weight: 600;">0.00</td>

                                        <!-- Dynamic Performance Tasks Inputs -->
                                        <?php foreach ($pt_list as $task_num =>$comp): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" 
                                                       data-category="PT" 
                                                       data-task-number="<?= $task_num ?>"
                                                       data-component-id="<?= $comp['id'] ?? ($task_num + 8) ?>" 
                                                       data-student-id="<?= $student['id'] ?>"
                                                       value="<?= htmlspecialchars((string)($draft_scores[$student['id']][$comp['id'] ?? ''] ?? $saved_scores[$student['id']][$comp['id'] ?? ''] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                       <?= $term_is_read_only ? 'readonly' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="ps-cell cell-calc" data-category="PT">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="PT" style="font-weight: 600;">0.00</td>

                                        <!-- Dynamic Quarterly Exam Inputs -->
                                        <?php foreach ($qa_list as $task_num =>$comp): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" 
                                                       data-category="QA" 
                                                       data-task-number="<?= $task_num ?>"
                                                       data-component-id="<?= $comp['id'] ?? ($task_num + 14) ?>" 
                                                       data-student-id="<?= $student['id'] ?>"
                                                       value="<?= htmlspecialchars((string)($draft_scores[$student['id']][$comp['id'] ?? ''] ?? $saved_scores[$student['id']][$comp['id'] ?? ''] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                       <?= $term_is_read_only ? 'readonly' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="ps-cell cell-calc" data-category="QA">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="QA" style="font-weight: 600;">0.00</td>

                                        <!-- Transmuted Results -->
                                        <td class="initial-grade-cell cell-calc" style="font-weight: 600;">0.00</td>
                                        <td class="transmuted-grade-cell cell-transmuted">60</td>
                                        <td class="descriptor-cell" style="font-size: 0.68rem; color: #64748b;">DNM</td>
                                        <td>
                                            <span class="remark-cell badge-remark failed">Failed</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Handlers -->
    <script src="../../assets/js/app.js"></script>
    <script src="../../assets/js/ecr-grid.js?v=<?= filemtime(__DIR__ . '/../../assets/js/ecr-grid.js') ?>"></script>
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

        function switchSubjectAssignment(assignmentId) {
            if (assignmentId > 0) {
                window.location.href = `ecr-view.php?assignment_id=${assignmentId}&term=<?= $term ?>`;
            }
        }
    </script>
</body>

</html>