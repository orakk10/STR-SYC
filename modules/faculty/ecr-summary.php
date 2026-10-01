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
    header("Location: ../../login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
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
$teacher_data = $teacher_query->fetch(PDO::FETCH_ASSOC);

$section_id = $teacher_data['section_id'] ?? 0;

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
        return 'Did Not Meet Expectations';
    }
}

// 4. Fetch Active Student Roster and Term Grades
$males = [];
$females = [];

if ($section_id > 0) {
    // Fetch Males with Term Summaries
    $m_stmt = $pdo->prepare("
        SELECT u.id, u.full_name as name,
               ts.term1_transmuted as term1,
               ts.term2_transmuted as term2,
               ts.term3_transmuted as term3,
               ts.final_grade,
               ts.letter_grade,
               ts.remarks
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN ecr_term_summaries ts ON u.id = ts.student_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND (sp.gender = 'Male' OR sp.gender IS NULL)
        ORDER BY u.full_name ASC
    ");
    $m_stmt->execute(['section_id' => $section_id]);
    $males = $m_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Females with Term Summaries
    $f_stmt = $pdo->prepare("
        SELECT u.id, u.full_name as name,
               ts.term1_transmuted as term1,
               ts.term2_transmuted as term2,
               ts.term3_transmuted as term3,
               ts.final_grade,
               ts.letter_grade,
               ts.remarks
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN ecr_term_summaries ts ON u.id = ts.student_id
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

        #sidebar:not(.active) ~ main.content {
            margin-left: 0;
            width: 100%;
        }

        .remarks-val.passed { color: #15803d; font-weight: bold; }
        .remarks-val.failed { color: #b91c1c; font-weight: bold; }
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
                <li><a href="faculty_dashboard.php">Dashboard</a></li>
                <li><a href="faculty_schedule.php">Manage Schedule</a></li>
                <li><a href="ecr-inputdata.php">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php">E-Class Record Hub</a></li>
                <li><a href="ecr-summary.php" class="active">ECR Summary</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <main class="content">
            <div class="ecr-container">
                <!-- Header Banner -->
                <header class="ecr-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <img src="../../assets/icons/kagawaran-logo.png" alt="Kagawaran Logo" style="max-height: 50px;">
                    <div class="header-text" style="text-align: center;">
                        <h2 style="margin: 0; font-size: 1.25rem;">Final Grades Summary</h2>
                        <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.85rem;">Strengthened Senior High School Class Record — SY 2026–2027</p>
                    </div>
                    <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" style="max-height: 50px;">
                </header>

                <!-- Class Metadata Banner -->
                <div class="meta-banner" style="display: flex; gap: 20px; background: white; padding: 15px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 20px; font-size: 0.85rem;">
                    <div class="meta-item"><strong>GRADE & SECTION:</strong> <?= htmlspecialchars(($teacher_data['grade_level'] ?? '') . ' - ' . ($teacher_data['section_name'] ?? 'Unassigned')) ?></div>
                    <div class="meta-item"><strong>TEACHER:</strong> <?= htmlspecialchars($teacher_data['full_name'] ?? 'N/A') ?></div>
                    <div class="meta-item"><strong>STRAND:</strong> <?= htmlspecialchars($teacher_data['strand_name'] ?? 'N/A') ?></div>
                </div>

                <!-- Summary Grid Matrix -->
                <div class="table-scroll-container" style="overflow-x: auto; background: white; padding: 15px; border-radius: 8px; border: 1px solid #cbd5e1;">
                    <table class="ecr-grid summary-grid" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th class="col-sticky student-col" style="padding: 10px; border: 1px solid #cbd5e1; text-align: left;">LEARNERS' NAMES</th>
                                <th class="head-term" style="padding: 10px; border: 1px solid #cbd5e1; text-align: center;">TERM 1</th>
                                <th class="head-term" style="padding: 10px; border: 1px solid #cbd5e1; text-align: center;">TERM 2</th>
                                <th class="head-term" style="padding: 10px; border: 1px solid #cbd5e1; text-align: center;">TERM 3</th>
                                <th class="head-avg" style="padding: 10px; border: 1px solid #cbd5e1; text-align: center;">AVERAGE</th>
                                <th class="head-letter" style="padding: 10px; border: 1px solid #cbd5e1; text-align: center;">DESCRIPTOR</th>
                                <th class="head-remarks" style="padding: 10px; border: 1px solid #cbd5e1; text-align: center;">REMARKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Male Students Group -->
                            <tr class="group-header" style="background: #e2e8f0; font-weight: bold;"><td colspan="7" style="padding: 8px;">MALE</td></tr>
                            <?php if (empty($males)): ?>
                                <tr><td colspan="7" style="padding: 15px; text-align: center; color: #94a3b8;">No male students enrolled in this section.</td></tr>
                            <?php else: ?>
                                <?php foreach ($males as $index => $student): ?>
                                    <?php 
                                        $t1 = $student['term1'] ?? null;
                                        $t2 = $student['term2'] ?? null;
                                        $t3 = $student['term3'] ?? null;
                                        
                                        $validTerms = array_filter([$t1, $t2, $t3], fn($v) => $v !== null);
                                        $average = count($validTerms) > 0 ? round(array_sum($validTerms) / count($validTerms), 2) : null;
                                        
                                        $letter  = $average ? getDepEdDescriptor($average) : '-';
                                        $remarks = $average ? ($average >= 75 ? 'PASSED' : 'FAILED') : '-';
                                    ?>
                                    <tr class="summary-row" data-id="<?= $student['id'] ?>" style="border-bottom: 1px solid #f1f5f9;">
                                        <td class="col-sticky student-col" style="padding: 8px; font-weight: bold;"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                                        <td class="term-val" style="padding: 8px; text-align: center;"><?= $t1 ?? '-' ?></td>
                                        <td class="term-val" style="padding: 8px; text-align: center;"><?= $t2 ?? '-' ?></td>
                                        <td class="term-val" style="padding: 8px; text-align: center;"><?= $t3 ?? '-' ?></td>
                                        <td class="calc-val highlight-main" style="padding: 8px; text-align: center; font-weight: bold;"><?= $average ?? '-' ?></td>
                                        <td class="letter-val" style="padding: 8px; text-align: center;"><?= htmlspecialchars($letter) ?></td>
                                        <td class="remarks-val <?= strtolower($remarks) ?>" style="padding: 8px; text-align: center;"><?= $remarks ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Female Students Group -->
                            <tr class="group-header" style="background: #e2e8f0; font-weight: bold;"><td colspan="7" style="padding: 8px;">FEMALE</td></tr>
                            <?php if (empty($females)): ?>
                                <tr><td colspan="7" style="padding: 15px; text-align: center; color: #94a3b8;">No female students enrolled in this section.</td></tr>
                            <?php else: ?>
                                <?php foreach ($females as $index => $student): ?>
                                    <?php 
                                        $t1 = $student['term1'] ?? null;
                                        $t2 = $student['term2'] ?? null;
                                        $t3 = $student['term3'] ?? null;
                                        
                                        $validTerms = array_filter([$t1, $t2, $t3], fn($v) => $v !== null);
                                        $average = count($validTerms) > 0 ? round(array_sum($validTerms) / count($validTerms), 2) : null;
                                        
                                        $letter  = $average ? getDepEdDescriptor($average) : '-';
                                        $remarks = $average ? ($average >= 75 ? 'PASSED' : 'FAILED') : '-';
                                    ?>
                                    <tr class="summary-row" data-id="<?= $student['id'] ?>" style="border-bottom: 1px solid #f1f5f9;">
                                        <td class="col-sticky student-col" style="padding: 8px; font-weight: bold;"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                                        <td class="term-val" style="padding: 8px; text-align: center;"><?= $t1 ?? '-' ?></td>
                                        <td class="term-val" style="padding: 8px; text-align: center;"><?= $t2 ?? '-' ?></td>
                                        <td class="term-val" style="padding: 8px; text-align: center;"><?= $t3 ?? '-' ?></td>
                                        <td class="calc-val highlight-main" style="padding: 8px; text-align: center; font-weight: bold;"><?= $average ?? '-' ?></td>
                                        <td class="letter-val" style="padding: 8px; text-align: center;"><?= htmlspecialchars($letter) ?></td>
                                        <td class="remarks-val <?= strtolower($remarks) ?>" style="padding: 8px; text-align: center;"><?= $remarks ?></td>
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