<?php
session_start();

// 1. Correct Path Resolution using __DIR__
require_once __DIR__ . '/../../config/database.php';

// Optional helper load
$transmutation_file = __DIR__ . '/../../core/DepEdTransmutation.php';
if (file_exists($transmutation_file)) {
    require_once $transmutation_file;
}

// Instantiate PDO connection
$pdo = getDBConnection();

// 2. Access Control: Ensure only Faculty or Advisers can enter
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    header("Location: ../../manifest/login.php");
    exit();
}

$user_id   =$_SESSION['user_id'];
$user_role =$_SESSION['role'];

// 3. Subject Assignment & Section Query Parameters
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

// Fetch Active Faculty Subject Load
$assignments_stmt =$pdo->prepare("
    SELECT sa.id AS assignment_id, sub.subject_name, sub.subject_code, sec.id AS section_id, sec.section_name, sec.grade_level
    FROM subject_assignments sa
    JOIN subjects sub ON sa.subject_id = sub.id
    JOIN sections sec ON sa.section_id = sec.id
    WHERE sa.faculty_id = :faculty_id
    ORDER BY sec.grade_level ASC, sec.section_name ASC, sub.subject_name ASC
");
$assignments_stmt->execute(['faculty_id' =>$user_id]);
$my_assignments =$assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($assignment_id === 0 && !empty($my_assignments)) {
    $assignment_id = (int)$my_assignments[0]['assignment_id'];
}

// Fetch Active Assignment Metadata
$subject_name = "";
$section_id   = 0;
$section_name = "";
$strand_code  = "N/A";

if ($assignment_id > 0) {
    $info_stmt =$pdo->prepare("
        SELECT sub.subject_name, sa.section_id, sec.section_name, sec.grade_level, st.strand_code
        FROM subject_assignments sa
        JOIN subjects sub ON sa.subject_id = sub.id
        JOIN sections sec ON sa.section_id = sec.id
        LEFT JOIN strands st ON sec.strand_id = st.id
        WHERE sa.id = :assignment_id
    ");
    $info_stmt->execute(['assignment_id' =>$assignment_id]);
    $info_data =$info_stmt->fetch(PDO::FETCH_ASSOC);

    if ($info_data) {
        $subject_name =$info_data['subject_name'] ?? "";
        $section_id   = (int)$info_data['section_id'];$section_name = "Grade " . $info_data['grade_level'] . " - " . $info_data['section_name'];
        $strand_code  =$info_data['strand_code'] ?? "N/A";
    }
}

// Helper Function for Letter Grades Fallback
if (!function_exists('getDepEdDescriptor')) {
    function getDepEdDescriptor(float $grade) {
        if (class_exists('DepEdTransmutation') && method_exists('DepEdTransmutation', 'getLetterGrade')) {
            return DepEdTransmutation::getLetterGrade($grade);
        }
        if ($grade >= 90) return 'O';
        if ($grade >= 85) return 'VS';
        if ($grade >= 80) return 'S';
        if ($grade >= 75) return 'FS';
        return 'DNM';
    }
}

// 4. Fetch Active Student Roster and Term Grades
$males   = [];$females = [];

if ($section_id > 0) {
    // Fetch Males with Term Summaries
    $m_stmt =$pdo->prepare("
        SELECT u.id, u.full_name AS name,
               ts.term1_transmuted AS term1,
               ts.term2_transmuted AS term2,
               ts.term3_transmuted AS term3,
               ts.final_grade,
               ts.letter_grade,
               ts.remarks
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN ecr_term_summaries ts ON u.id = ts.student_id AND ts.assignment_id = :assignment_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND (sp.gender = 'Male' OR sp.gender IS NULL)
        ORDER BY u.full_name ASC
    ");
    $m_stmt->execute(['section_id' => $section_id, 'assignment_id' =>$assignment_id]);
    $males =$m_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Females with Term Summaries
    $f_stmt =$pdo->prepare("
        SELECT u.id, u.full_name AS name,
               ts.term1_transmuted AS term1,
               ts.term2_transmuted AS term2,
               ts.term3_transmuted AS term3,
               ts.final_grade,
               ts.letter_grade,
               ts.remarks
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN ecr_term_summaries ts ON u.id = ts.student_id AND ts.assignment_id = :assignment_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND sp.gender = 'Female'
        ORDER BY u.full_name ASC
    ");
    $f_stmt->execute(['section_id' => $section_id, 'assignment_id' =>$assignment_id]);
    $females =$f_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECR Final Grades Summary | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
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
            color: white; 
            padding: 7px 14px; 
            border-radius: 6px; 
            border: none; 
            font-weight: 600; 
            font-size: 0.8rem;
            cursor: pointer; 
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action:hover { 
            background-color: #1d4ed8; 
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }

        .ecr-control-strip {
            padding: 10px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .selector-box {
            display: flex;
            align-items: center;
            gap: 10px;
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

        .roster-info-pill {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
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
            font-size: 0.82rem;
        }

        table.ecr-grid th, 
        table.ecr-grid td {
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            vertical-align: middle;
            box-sizing: border-box;
            padding: 8px 12px;
        }

        table.ecr-grid thead th {
            position: sticky;
            top: 0;
            z-index: 20;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            background: #f8fafc;
            color: #475569;
        }

        .col-sticky-name { 
            position: sticky; 
            left: 0; 
            background-color: #ffffff; 
            z-index: 30; 
            width: 240px;
            min-width: 240px;
            text-align: left !important;
            padding: 8px 16px !important;
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
        }

        tr.group-header td {
            background: #f1f5f9;
            font-weight: 700;
            color: #475569;
            text-align: left !important;
            padding: 6px 16px !important;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        tr.summary-row:hover td {
            background-color: #f8fafc;
        }

        tr.summary-row:hover .col-sticky-name {
            background-color: #f8fafc;
        }

        .cell-final {
            font-weight: 700;
            color: #2563eb;
            background-color: #eff6ff;
        }

        .badge-remark {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.72rem;
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
    <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>

    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="faculty_dashboard.php" onclick="toggleSidebar()">Dashboard</a></li>
                <li><a href="faculty_schedule.php" onclick="toggleSidebar()">Manage Schedule</a></li>
                <li><a href="ecr-inputdata.php" onclick="toggleSidebar()">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php?assignment_id=<?= $assignment_id ?>" onclick="toggleSidebar()">Encode Term Grades</a></li>
                <li><a href="ecr-summary.php?assignment_id=<?= $assignment_id ?>" class="active" onclick="toggleSidebar()">ECR Summary</a></li>
                <li><a href="../../manifest/logout.php" class="logout" onclick="toggleSidebar()">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <main class="content">
            <div class="ecr-card-master">
                
                <!-- Master Header Toolbar -->
                <header class="ecr-header-toolbar">
                    <div class="header-title-group">
                        <h2>
                            Final Grades Summary
                            <?php if ($subject_name): ?>
                                <span class="subject-badge"><?= htmlspecialchars($subject_name) ?></span>
                            <?php endif; ?>
                        </h2>
                    </div>
                    <div>
                        <button onclick="window.print()" class="btn-action">Print Summary</button>
                    </div>
                </header>

                <!-- Subject Assignment Selector Strip -->
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
                    </div>
                    <?php if ($section_name): ?>
                        <div class="roster-info-pill">
                            Active Roster: <span><?= htmlspecialchars($section_name) ?></span> (Strand: <?= htmlspecialchars($strand_code) ?>)
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Navigation Tab Strip -->
                <nav class="ecr-tab-bar">
                    <a href="ecr-inputdata.php" class="tab-link">INPUT DATA</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=1" class="tab-link">TERM 1</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=2" class="tab-link">TERM 2</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=3" class="tab-link">TERM 3</a>
                    <a href="ecr-summary.php?assignment_id=<?= $assignment_id ?>" class="tab-link active">SUMMARY</a>
                </nav>

                <!-- Summary Grid Matrix -->
                <div class="table-viewport-container">
                    <table class="ecr-grid">
                        <thead>
                            <tr>
                                <th class="col-sticky-name">LEARNERS' NAMES</th>
                                <th>TERM 1</th>
                                <th>TERM 2</th>
                                <th>TERM 3</th>
                                <th>FINAL GRADE</th>
                                <th>DESCRIPTOR</th>
                                <th>REMARKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- MALE LEARNERS -->
                            <tr class="group-header">
                                <td colspan="7">MALE LEARNERS</td>
                            </tr>
                            <?php if (empty($males)): ?>
                                <tr>
                                    <td colspan="7" style="padding: 12px !important; color: #94a3b8; text-align: center;">No male students enrolled in this section.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($males as $index =>$student): ?>
                                    <?php 
                                        $t1 =$student['term1'] ?? null;
                                        $t2 =$student['term2'] ?? null;
                                        $t3 =$student['term3'] ?? null;
                                        
                                        $validTerms = array_filter([$t1,$t2, $t3], fn($v) => $v !== null);$average = count($validTerms) > 0 ? round(array_sum($validTerms) / count($validTerms), 2) : ($student['final_grade'] ?? null);
                                        
                                        $letter  =$average ? getDepEdDescriptor($average) : '-';$remarks = $average ? ($average >= 75 ? 'PASSED' : 'FAILED') : '-';
                                    ?>
                                    <tr class="summary-row" data-id="<?= $student['id'] ?>">
                                        <td class="col-sticky-name" title="<?= htmlspecialchars($student['name']) ?>">
                                            <?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?>
                                        </td>
                                        <td><?= $t1 !== null ? number_format($t1, 0) : '-' ?></td>
                                        <td><?= $t2 !== null ? number_format($t2, 0) : '-' ?></td>
                                        <td><?= $t3 !== null ? number_format($t3, 0) : '-' ?></td>
                                        <td class="cell-final"><?= $average !== null ? number_format($average, 2) : '-' ?></td>
                                        <td style="color: #64748b; font-weight: 600;"><?= htmlspecialchars($letter) ?></td>
                                        <td>
                                            <?php if ($remarks === 'PASSED'): ?>
                                                <span class="badge-remark passed">Passed</span>
                                            <?php elseif ($remarks === 'FAILED'): ?>
                                                <span class="badge-remark failed">Failed</span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- FEMALE LEARNERS -->
                            <tr class="group-header">
                                <td colspan="7">FEMALE LEARNERS</td>
                            </tr>
                            <?php if (empty($females)): ?>
                                <tr>
                                    <td colspan="7" style="padding: 12px !important; color: #94a3b8; text-align: center;">No female students enrolled in this section.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($females as $index =>$student): ?>
                                    <?php 
                                        $t1 =$student['term1'] ?? null;
                                        $t2 =$student['term2'] ?? null;
                                        $t3 =$student['term3'] ?? null;
                                        
                                        $validTerms = array_filter([$t1,$t2, $t3], fn($v) => $v !== null);$average = count($validTerms) > 0 ? round(array_sum($validTerms) / count($validTerms), 2) : ($student['final_grade'] ?? null);
                                        
                                        $letter  =$average ? getDepEdDescriptor($average) : '-';$remarks = $average ? ($average >= 75 ? 'PASSED' : 'FAILED') : '-';
                                    ?>
                                    <tr class="summary-row" data-id="<?= $student['id'] ?>">
                                        <td class="col-sticky-name" title="<?= htmlspecialchars($student['name']) ?>">
                                            <?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?>
                                        </td>
                                        <td><?= $t1 !== null ? number_format($t1, 0) : '-' ?></td>
                                        <td><?= $t2 !== null ? number_format($t2, 0) : '-' ?></td>
                                        <td><?= $t3 !== null ? number_format($t3, 0) : '-' ?></td>
                                        <td class="cell-final"><?= $average !== null ? number_format($average, 2) : '-' ?></td>
                                        <td style="color: #64748b; font-weight: 600;"><?= htmlspecialchars($letter) ?></td>
                                        <td>
                                            <?php if ($remarks === 'PASSED'): ?>
                                                <span class="badge-remark passed">Passed</span>
                                            <?php elseif ($remarks === 'FAILED'): ?>
                                                <span class="badge-remark failed">Failed</span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">-</span>
                                            <?php endif; ?>
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

    <!-- JS Scripts -->
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

        function switchSubjectAssignment(assignmentId) {
            if (assignmentId > 0) {
                window.location.href = `ecr-summary.php?assignment_id=${assignmentId}`;
            }
        }
    </script>
</body>

</html>