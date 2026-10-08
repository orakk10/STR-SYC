<?php
session_start();

// 1. Path Resolution & Database Connection
require_once __DIR__ . '/../../config/database.php';
$pdo = getDBConnection();

// 2. Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    header("Location: ../../manifest/login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 3. Term & Assignment Query Parameters
$term = isset($_GET['term']) ? (int)$_GET['term'] : 1;
if (!in_array($term, [1, 2, 3])) {
    $term = 1;
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

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
    SELECT sa.id AS assignment_id, sub.subject_name, sub.subject_code, sec.section_name, sec.grade_level
    FROM subject_assignments sa
    JOIN subjects sub ON sa.subject_id = sub.id
    JOIN sections sec ON sa.section_id = sec.id
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

if ($assignment_id > 0) {
    $weight_stmt = $pdo->prepare("
        SELECT sub.subject_name, sub.type AS subject_type, sa.section_id, sec.section_name, sec.grade_level
        FROM subject_assignments sa
        JOIN subjects sub ON sa.subject_id = sub.id
        JOIN sections sec ON sa.section_id = sec.id
        WHERE sa.id = :assignment_id
    ");
    $weight_stmt->execute(['assignment_id' => $assignment_id]);
    $subject_weight_data = $weight_stmt->fetch(PDO::FETCH_ASSOC);

    if ($subject_weight_data) {
        $weights      = getComponentWeights($subject_weight_data['subject_type'] ?? 'Core');
        $ww_weight    = $weights['ww'];
        $pt_weight    = $weights['pt'];
        $qa_weight    =$weights['qa'];
        $subject_name =$subject_weight_data['subject_name'] ?? "";
        $section_id   = (int)$subject_weight_data['section_id'];$section_name = "Grade " . $subject_weight_data['grade_level'] . " - " . $subject_weight_data['section_name'];
    }
}

// 6. Fetch Section Students (Split by Gender)
$males   = [];$females = [];

if ($section_id > 0) {
    $m_stmt =$pdo->prepare("
        SELECT u.id, u.full_name AS name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND (sp.gender = 'Male' OR sp.gender IS NULL)
        ORDER BY u.full_name ASC
    ");
    $m_stmt->execute(['section_id' =>$section_id]);
    $males =$m_stmt->fetchAll(PDO::FETCH_ASSOC);

    $f_stmt =$pdo->prepare("
        SELECT u.id, u.full_name AS name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND sp.gender = 'Female'
        ORDER BY u.full_name ASC
    ");
    $f_stmt->execute(['section_id' =>$section_id]);
    $females =$f_stmt->fetchAll(PDO::FETCH_ASSOC);
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
            display: block; 
            height: 100vh; 
            width: 100%; 
            position: relative;
        }

        main.content { 
            margin-left: 0; 
            padding: 12px; 
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
            padding: 12px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
        }

        .header-title-group h2 {
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

        @media (max-width: 992px) {
            main.content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 10px;
                padding-top: 50px;
            }
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
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="faculty_dashboard.php" onclick="toggleSidebar()">Dashboard</a></li>
                <li><a href="faculty_schedule.php" onclick="toggleSidebar()">Manage Schedule</a></li>
                <li><a href="ecr-inputdata.php" onclick="toggleSidebar()">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=<?= $term ?>" class="active" onclick="toggleSidebar()">Encode Term Grades</a></li>
                <li><a href="ecr-summary.php" onclick="toggleSidebar()">ECR Summary</a></li>
                <li><a href="../../manifest/logout.php" class="logout" onclick="toggleSidebar()">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <div class="ecr-card-master">
                
                <header class="ecr-header-toolbar">
                    <div class="header-title-group">
                        <h2>
                            SHS Class Record — Term <?= $term ?>
                            <?php if ($subject_name): ?>
                                <span class="subject-badge"><?= htmlspecialchars($subject_name) ?></span>
                            <?php endif; ?>
                        </h2>
                    </div>
                </header>

                <div class="ecr-control-strip">
                    <div class="selector-box">
                        <label for="assignmentSelect">Assigned Subject:</label>
                        <select id="assignmentSelect" onchange="switchSubjectAssignment(this.value)">
                            <?php if (empty($my_assignments)): ?>
                                <option value="0">No teaching subjects assigned</option>
                            <?php else: ?>
                                <?php foreach ($my_assignments as$assign): ?>
                                    <option value="<?= $assign['assignment_id'] ?>" <?= $assign['assignment_id'] ==$assignment_id ? 'selected' : '' ?>>
                                        Grade <?= htmlspecialchars($assign['grade_level'] . ' - ' .$assign['section_name']) ?> | <?= htmlspecialchars($assign['subject_name'] . ' (' .$assign['subject_code'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>

                        <!-- Action Buttons Beside Assigned Subject Select -->
                        <div class="control-action-buttons">
                            <button id="saveConfigBtn" class="btn-action btn-secondary">Save HPS Setup</button>
                            <button id="saveScoresBtn" class="btn-action">Save ECR Scores</button>
                        </div>
                    </div>

                    <!-- Standard Flow Active Roster Text -->
                    <?php if ($section_name): ?>
                        <div class="roster-info-pill">
                            Active Roster: <span><?= htmlspecialchars($section_name) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <nav class="ecr-tab-bar">
                    <a href="ecr-inputdata.php" class="tab-link">INPUT DATA</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=1" class="tab-link <?= $term === 1 ? 'active' : '' ?>">TERM 1</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=2" class="tab-link <?= $term === 2 ? 'active' : '' ?>">TERM 2</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=3" class="tab-link <?= $term === 3 ? 'active' : '' ?>">TERM 3</a>
                    <a href="ecr-summary.php?assignment_id=<?= $assignment_id ?>" class="tab-link">SUMMARY</a>
                </nav>

                <div class="table-viewport-container">
                    <table class="ecr-grid">
                        <thead>
                            <tr>
                                <th rowspan="2" class="col-sticky-name">LEARNERS' NAMES</th>
                                <th colspan="8" class="th-ww">
                                    WRITTEN WORKS (<?= $ww_weight * 100 ?>%)
                                </th>
                                <th colspan="2" class="th-ww th-calc">WW CALC</th>
                                <th colspan="6" class="th-pt">
                                    PERFORMANCE TASKS (<?= $pt_weight * 100 ?>%)
                                </th>
                                <th colspan="2" class="th-pt th-calc">PT CALC</th>
                                <th colspan="3" class="th-qa">
                                    EXAMS (<?= $qa_weight * 100 ?>%)
                                </th>
                                <th colspan="2" class="th-qa th-calc">QA CALC</th>
                                <th rowspan="2" class="th-final" style="width: 44px;">Initial</th>
                                <th rowspan="2" class="th-final" style="width: 48px;">Trans.</th>
                                <th rowspan="2" class="th-final" style="width: 44px;">Desc.</th>
                                <th rowspan="2" class="th-final" style="width: 54px;">Remarks</th>
                            </tr>

                            <tr class="hps-row">
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <th>
                                        <input type="number" min="1" max="100" class="hps-input" data-category="WW" data-task-number="<?= $i ?>" data-component-id="<?= $i ?>" placeholder="WW<?= $i ?>">
                                    </th>
                                <?php endfor; ?>
                                <th class="th-calc" style="width: 26px;">PS</th>
                                <th class="th-calc" style="width: 26px;">WS</th>

                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <th>
                                        <input type="number" min="1" max="100" class="hps-input" data-category="PT" data-task-number="<?= $i ?>" data-component-id="<?= 8 + $i ?>" placeholder="PT<?= $i ?>">
                                    </th>
                                <?php endfor; ?>
                                <th class="th-calc" style="width: 26px;">PS</th>
                                <th class="th-calc" style="width: 26px;">WS</th>

                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <th>
                                        <input type="number" min="1" max="100" class="hps-input" data-category="QA" data-task-number="<?= $i ?>" data-component-id="<?= 14 + $i ?>" placeholder="QA<?= $i ?>">
                                    </th>
                                <?php endfor; ?>
                                <th class="th-calc" style="width: 26px;">PS</th>
                                <th class="th-calc" style="width: 26px;">WS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- MALE SECTION -->
                            <tr class="group-header">
                                <td colspan="27">MALE LEARNERS</td>
                            </tr>
                            <?php if (empty($males)): ?>
                                <tr>
                                    <td colspan="27" style="padding: 10px !important; color: #94a3b8;">No male students registered in this section.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($males as $index =>$student): ?>
                                    <tr class="student-row" data-student-id="<?= $student['id'] ?>">
                                        <td class="col-sticky-name" title="<?= htmlspecialchars($student['name']) ?>">
                                            <?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?>
                                        </td>
                                        
                                        <!-- Written Works (1-8) -->
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" data-category="WW" data-component-id="<?= $i ?>" data-student-id="<?= $student['id'] ?>">
                                            </td>
                                        <?php endfor; ?>
                                        <td class="ps-cell cell-calc" data-category="WW">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="WW" style="font-weight: 600;">0.00</td>

                                        <!-- Performance Tasks (1-6) -->
                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" data-category="PT" data-component-id="<?= 8 + $i ?>" data-student-id="<?= $student['id'] ?>">
                                            </td>
                                        <?php endfor; ?>
                                        <td class="ps-cell cell-calc" data-category="PT">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="PT" style="font-weight: 600;">0.00</td>

                                        <!-- Quarterly Exam (1-3) -->
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" data-category="QA" data-component-id="<?= 14 + $i ?>" data-student-id="<?= $student['id'] ?>">
                                            </td>
                                        <?php endfor; ?>
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
                                <td colspan="27">FEMALE LEARNERS</td>
                            </tr>
                            <?php if (empty($females)): ?>
                                <tr>
                                    <td colspan="27" style="padding: 10px !important; color: #94a3b8;">No female students registered in this section.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($females as $index =>$student): ?>
                                    <tr class="student-row" data-student-id="<?= $student['id'] ?>">
                                        <td class="col-sticky-name" title="<?= htmlspecialchars($student['name']) ?>">
                                            <?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?>
                                        </td>
                                        
                                        <!-- Written Works (1-8) -->
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" data-category="WW" data-component-id="<?= $i ?>" data-student-id="<?= $student['id'] ?>">
                                            </td>
                                        <?php endfor; ?>
                                        <td class="ps-cell cell-calc" data-category="WW">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="WW" style="font-weight: 600;">0.00</td>

                                        <!-- Performance Tasks (1-6) -->
                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" data-category="PT" data-component-id="<?= 8 + $i ?>" data-student-id="<?= $student['id'] ?>">
                                            </td>
                                        <?php endfor; ?>
                                        <td class="ps-cell cell-calc" data-category="PT">0.00</td>
                                        <td class="ws-cell cell-calc" data-category="PT" style="font-weight: 600;">0.00</td>

                                        <!-- Quarterly Exam (1-3) -->
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <td>
                                                <input type="number" min="0" max="100" class="score-input" data-category="QA" data-component-id="<?= 14 + $i ?>" data-student-id="<?= $student['id'] ?>">
                                            </td>
                                        <?php endfor; ?>
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
    <script src="../../assets/js/ecr-grid.js"></script>
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