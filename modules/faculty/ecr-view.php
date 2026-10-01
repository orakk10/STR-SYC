<?php
session_start();

// 1. Correct Path Resolution using __DIR__
require_once __DIR__ . '/../../config/database.php';

// Instantiate PDO connection
$pdo = getDBConnection();

// 2. Access Control: Ensure only Faculty or Advisers can enter
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser', 'admin'])) {
    header("Location: ../../manifest/login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Current Term & Assignment Handling
$term = isset($_GET['term']) ? (int)$_GET['term'] : 1;
if (!in_array($term, [1, 2, 3])) {
    $term = 1;
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

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

// 4. Fetch Dynamic Component Percentages for Active Assignment/Subject
$ww_percent = 20;
$pt_percent = 50;
$qa_percent = 30;
$subject_name = "";

if ($assignment_id > 0) {
    $weight_stmt = $pdo->prepare("
        SELECT sub.subject_name, sub.ww_percent, sub.pt_percent, sub.qa_percent, sa.section_id
        FROM subject_assignments sa
        JOIN subjects sub ON sa.subject_id = sub.id
        WHERE sa.id = :assignment_id
    ");
    $weight_stmt->execute(['assignment_id' => $assignment_id]);
    $subject_weight_data = $weight_stmt->fetch(PDO::FETCH_ASSOC);

    if ($subject_weight_data) {
        $ww_percent   = (float)($subject_weight_data['ww_percent'] ?? 20);
        $pt_percent   = (float)($subject_weight_data['pt_percent'] ?? 50);
        $qa_percent   = (float)($subject_weight_data['qa_percent'] ?? 30);
        $subject_name = $subject_weight_data['subject_name'] ?? "";
        if ($subject_weight_data['section_id']) {
            $section_id = $subject_weight_data['section_id'];
        }
    }
}

// 5. Fetch Active Student Roster by Gender
$males = [];
$females = [];

if ($section_id > 0) {
    // Fetch Male Roster
    $m_stmt = $pdo->prepare("
        SELECT u.id, u.full_name AS name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND (sp.gender = 'Male' OR sp.gender IS NULL)
        ORDER BY u.full_name ASC
    ");
    $m_stmt->execute(['section_id' => $section_id]);
    $males = $m_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Female Roster
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

        .subject-badge {
            background-color: #e0e7ff;
            color: #3730a3;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-left: 10px;
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
                <li><a href="faculty_dashboard.php">Dashboard</a></li>
                <li><a href="faculty_schedule.php">Manage Schedule</a></li>
                <li><a href="ecr-inputdata.php">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=<?= $term ?>" class="active">Encode Term Grades</a></li>
                <li><a href="ecr-summary.php">ECR Summary</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <main class="content">
            <div class="ecr-container">
                <!-- Flexible Banner Header -->
                <header class="ecr-header">
                    <div class="brand-block">
                        <img src="../../assets/icons/kagawaran-logo.png" alt="Kagawaran Logo" class="logo-sm">
                        <div class="header-text">
                            <h2>
                                SHS Class Record — Term <?= $term ?>
                                <?php if ($subject_name): ?>
                                    <span class="subject-badge"><?= htmlspecialchars($subject_name) ?></span>
                                <?php endif; ?>
                            </h2>
                            <p>DepEd Order No. 8, s. 2015 Framework | SY 2026–2027</p>
                        </div>
                    </div>
                    <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" class="logo-md">
                </header>

                <!-- Navigation Tabs -->
                <nav class="ecr-nav">
                    <a href="ecr-inputdata.php" class="nav-tab">INPUT DATA</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=1" class="nav-tab <?= $term === 1 ? 'active' : '' ?>">TERM 1</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=2" class="nav-tab <?= $term === 2 ? 'active' : '' ?>">TERM 2</a>
                    <a href="ecr-view.php?assignment_id=<?= $assignment_id ?>&term=3" class="nav-tab <?= $term === 3 ? 'active' : '' ?>">TERM 3</a>
                    <a href="ecr-summary.php" class="nav-tab tab-summary">SUMMARY</a>
                </nav>

                <!-- Mobile Scrollable Table Wrapper -->
                <div class="table-responsive-wrapper" style="overflow-x: auto; background: white; padding: 15px; border-radius: 8px; border: 1px solid #cbd5e1;">
                    <table class="ecr-grid" 
                           data-ww="<?= $ww_percent ?>" 
                           data-pt="<?= $pt_percent ?>" 
                           data-qa="<?= $qa_percent ?>" 
                           style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th rowspan="3" class="col-sticky student-col" style="padding: 8px; border: 1px solid #cbd5e1;">LEARNERS' NAMES</th>
                                <th colspan="8" class="head-ww" style="padding: 8px; border: 1px solid #cbd5e1; background: #f0f9ff;">
                                    WRITTEN WORKS (<?= $ww_percent ?>%)
                                </th>
                                <th colspan="6" class="head-pt" style="padding: 8px; border: 1px solid #cbd5e1; background: #f0fdf4;">
                                    PERFORMANCE (<?= $pt_percent ?>%)
                                </th>
                                <th colspan="6" class="head-qa" style="padding: 8px; border: 1px solid #cbd5e1; background: #fefce8;">
                                    EXAMS (<?= $qa_percent ?>%)
                                </th>
                                <th rowspan="3" class="col-calc" style="padding: 8px; border: 1px solid #cbd5e1;">Initial</th>
                                <th rowspan="3" class="col-calc" style="padding: 8px; border: 1px solid #cbd5e1;">Transmuted</th>
                                <th rowspan="3" class="col-calc" style="padding: 8px; border: 1px solid #cbd5e1;">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="group-header" style="background: #e2e8f0; font-weight: bold;">
                                <td colspan="24" style="padding: 8px;">MALE</td>
                            </tr>
                            <?php if (empty($males)): ?>
                                <tr>
                                    <td colspan="24" style="padding: 10px; text-align: center; color: #94a3b8;">No male students registered.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($males as $index => $student): ?>
                                    <tr class="student-row" data-id="<?= $student['id'] ?>" style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 8px; font-weight: bold;"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                                        <td colspan="20" style="padding: 8px; text-align: center; color: #94a3b8;">Score cells ready</td>
                                        <td style="padding: 8px; text-align: center;">0.00</td>
                                        <td style="padding: 8px; text-align: center; font-weight: bold;">60</td>
                                        <td style="padding: 8px; text-align: center;">Did Not Meet</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <tr class="group-header" style="background: #e2e8f0; font-weight: bold;">
                                <td colspan="24" style="padding: 8px;">FEMALE</td>
                            </tr>
                            <?php if (empty($females)): ?>
                                <tr>
                                    <td colspan="24" style="padding: 10px; text-align: center; color: #94a3b8;">No female students registered.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($females as $index => $student): ?>
                                    <tr class="student-row" data-id="<?= $student['id'] ?>" style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 8px; font-weight: bold;"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                                        <td colspan="20" style="padding: 8px; text-align: center; color: #94a3b8;">Score cells ready</td>
                                        <td style="padding: 8px; text-align: center;">0.00</td>
                                        <td style="padding: 8px; text-align: center; font-weight: bold;">60</td>
                                        <td style="padding: 8px; text-align: center;">Did Not Meet</td>
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
    <script src="../../assets/js/ecr-grid.js"></script>
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