<?php
session_start();

// 1. Correct Path Resolution using __DIR__
require_once __DIR__ . '/../../config/database.php';

// Optional helper load (Gracefully falls back if class file isn't present)
$transmutation_file = __DIR__ . '/../../core/DepEdTransmutation.php';
if (file_exists($transmutation_file)) {
    require_once $transmutation_file;
}

// Instantiate PDO connection
$pdo = getDBConnection();

// 2. Access Control: Ensure only Faculty or Advisers can enter
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 3. Fetch Teacher Info & Assigned Section Details from DB
$teacher_query = $pdo->prepare("
    SELECT u.full_name, s.id as section_id, s.section_name, s.grade_level, st.strand_code, st.strand_name
    FROM users u
    LEFT JOIN sections s ON s.adviser_id = u.id
    LEFT JOIN strands st ON s.strand_id = st.id
    WHERE u.id = :user_id
");
$teacher_query->execute(['user_id' => $user_id]);
$teacher_data = $teacher_query->fetch();

$section_id = $teacher_data['section_id'] ?? 0;

// Helper Function for Letter Grades Fallback
if (!function_exists('getDepEdDescriptor')) {
    function getDepEdDescriptor(float $grade) {
        if (class_exists('DepEdTransmutation') && method_exists('DepEdTransmutation', 'getLetterGrade')) {
            return DepEdTransmutation::getLetterGrade($grade);
        }
        if ($grade >= 90) return 'Outstanding (O)';
        if ($grade >= 85) return 'Very Satisfactory (VS)';
        if ($grade >= 80) return 'Satisfactory (S)';
        if ($grade >= 75) return 'Fairly Satisfactory (FS)';
        return 'Did Not Meet Expectations (DNM)';
    }
}

// 4. Fetch Active Student Roster and Term Grades
$males = [];
$females = [];

if ($section_id > 0) {
    // Fetch Males with Term Summaries
    $m_stmt = $pdo->prepare("
        SELECT u.id, u.full_name as name,
               MAX(CASE WHEN ts.term = 1 THEN ts.transmuted_grade END) as term1,
               MAX(CASE WHEN ts.term = 2 THEN ts.transmuted_grade END) as term2,
               MAX(CASE WHEN ts.term = 3 THEN ts.transmuted_grade END) as term3
        FROM users u
        LEFT JOIN ecr_term_summaries ts ON u.id = ts.student_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND u.gender = 'Male'
        GROUP BY u.id, u.full_name
        ORDER BY u.full_name ASC
    ");
    $m_stmt->execute(['section_id' => $section_id]);
    $males = $m_stmt->fetchAll();

    // Fetch Females with Term Summaries
    $f_stmt = $pdo->prepare("
        SELECT u.id, u.full_name as name,
               MAX(CASE WHEN ts.term = 1 THEN ts.transmuted_grade END) as term1,
               MAX(CASE WHEN ts.term = 2 THEN ts.transmuted_grade END) as term2,
               MAX(CASE WHEN ts.term = 3 THEN ts.transmuted_grade END) as term3
        FROM users u
        LEFT JOIN ecr_term_summaries ts ON u.id = ts.student_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND u.gender = 'Female'
        GROUP BY u.id, u.full_name
        ORDER BY u.full_name ASC
    ");
    $f_stmt->execute(['section_id' => $section_id]);
    $females = $f_stmt->fetchAll();
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
</head>
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

        /* MAIN CONTENT */
        main.content {
            margin-left: 260px;
            padding: 30px;
            width: calc(100% - 260px);
            max-width: none;
            height: 100vh;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        /* SIDEBAR CLOSED */
        #sidebar:not(.active) ~ main.content {
            margin-left: 0;
            width: 100%;
        }
    </style>

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
                <li><a href="faculty_dashboard.php">Dashboard</a></li>
                <li><a href="faculty_schedule.php">Manage Schedule</a></li>
                <li><a href="ecr-inputdata.php">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php?term=1">Encode Term Grades</a></li>
                <li><a href="ecr-summary.php" class="active">ECR Summary</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <main class="content">
            <div class="ecr-container">
                <!-- Header Banner -->
                <header class="ecr-header">
                    <img src="../../assets/icons/kagawaran-logo.png" alt="Kagawaran Logo" class="logo-sm">
                    <div class="header-text">
                        <h2>Final Grades Summary</h2>
                        <p>Strengthened Senior High School Class Record — SY 2026–2027</p>
                    </div>
                    <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" class="logo-md">
                </header>

                <!-- Navigation Tabs -->
                <nav class="ecr-nav">
                    <a href="ecr-inputdata.php" class="nav-tab">INPUT DATA</a>
                    <a href="ecr-view.php?term=1" class="nav-tab">TERM 1</a>
                    <a href="ecr-view.php?term=2" class="nav-tab">TERM 2</a>
                    <a href="ecr-view.php?term=3" class="nav-tab">TERM 3</a>
                    <a href="ecr-summary.php" class="nav-tab tab-summary active">SUMMARY</a>
                </nav>

                <!-- Class Metadata Banner -->
                <div class="meta-banner">
                    <div class="meta-item"><span>GRADE & SECTION:</span> <?= htmlspecialchars(($teacher_data['grade_level'] ?? '') . ' - ' . ($teacher_data['section_name'] ?? 'Unassigned')) ?></div>
                    <div class="meta-item"><span>TEACHER:</span> <?= htmlspecialchars($teacher_data['full_name'] ?? 'N/A') ?></div>
                    <div class="meta-item"><span>STRAND:</span> <?= htmlspecialchars($teacher_data['strand_name'] ?? 'N/A') ?></div>
                </div>

                <!-- Tablet/Laptop Optimized Summary Matrix -->
                <div class="table-scroll-container">
                    <table class="ecr-grid summary-grid">
                        <thead>
                            <tr>
                                <th class="col-sticky student-col">LEARNERS' NAMES</th>
                                <th class="head-term">TERM 1</th>
                                <th class="head-term">TERM 2</th>
                                <th class="head-term">TERM 3</th>
                                <th class="head-avg">AVERAGE</th>
                                <th class="head-letter">DESCRIPTOR</th>
                                <th class="head-remarks">REMARKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Male Students Group -->
                            <tr class="group-header"><td colspan="7">MALE</td></tr>
                            <?php if (empty($males)): ?>
                                <tr class="empty-row"><td colspan="7">No male students configured. Add roster in Input Data tab.</td></tr>
                            <?php else: ?>
                                <?php foreach ($males as $index => $student): ?>
                                    <?php 
                                        $t1 = $student['term1'] ?? 0;
                                        $t2 = $student['term2'] ?? 0;
                                        $t3 = $student['term3'] ?? 0;
                                        
                                        $average = ($t1 && $t2 && $t3) ? round(($t1 + $t2 + $t3) / 3, 2) : 0;
                                        $letter = $average ? getDepEdDescriptor($average) : '-';
                                        $remarks = $average ? ($average >= 75 ? 'PASSED' : 'FAILED') : '-';
                                    ?>
                                    <tr class="summary-row" data-id="<?= $student['id'] ?>">
                                        <td class="col-sticky student-col"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                                        <td class="term-val"><?= $t1 ?: '-' ?></td>
                                        <td class="term-val"><?= $t2 ?: '-' ?></td>
                                        <td class="term-val"><?= $t3 ?: '-' ?></td>
                                        <td class="calc-val highlight-main"><?= $average ?: '-' ?></td>
                                        <td class="letter-val"><?= htmlspecialchars($letter) ?></td>
                                        <td class="remarks-val <?= strtolower($remarks) ?>"><?= $remarks ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Female Students Group -->
                            <tr class="group-header"><td colspan="7">FEMALE</td></tr>
                            <?php if (empty($females)): ?>
                                <tr class="empty-row"><td colspan="7">No female students configured. Add roster in Input Data tab.</td></tr>
                            <?php else: ?>
                                <?php foreach ($females as $index => $student): ?>
                                    <?php 
                                        $t1 = $student['term1'] ?? 0;
                                        $t2 = $student['term2'] ?? 0;
                                        $t3 = $student['term3'] ?? 0;
                                        
                                        $average = ($t1 && $t2 && $t3) ? round(($t1 + $t2 + $t3) / 3, 2) : 0;
                                        $letter = $average ? getDepEdDescriptor($average) : '-';
                                        $remarks = $average ? ($average >= 75 ? 'PASSED' : 'FAILED') : '-';
                                    ?>
                                    <tr class="summary-row" data-id="<?= $student['id'] ?>">
                                        <td class="col-sticky student-col"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                                        <td class="term-val"><?= $t1 ?: '-' ?></td>
                                        <td class="term-val"><?= $t2 ?: '-' ?></td>
                                        <td class="term-val"><?= $t3 ?: '-' ?></td>
                                        <td class="calc-val highlight-main"><?= $average ?: '-' ?></td>
                                        <td class="letter-val"><?= htmlspecialchars($letter) ?></td>
                                        <td class="remarks-val <?= strtolower($remarks) ?>"><?= $remarks ?></td>
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
    </script>
</body>

</html>