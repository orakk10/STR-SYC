<?php
session_start();

// 1. Correct Path Resolution using __DIR__
require_once __DIR__ . '/../../config/database.php';

// Instantiate PDO connection
$pdo = getDBConnection();

// 2. Access Control: Ensure only Faculty or Advisers can enter
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['faculty', 'adviser'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Current Term Handling (Default to Term 1 if not set or invalid)
$term = isset($_GET['term']) ? (int)$_GET['term'] : 1;
if (!in_array($term, [1, 2, 3])) {
    $term = 1;
}

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

// 4. Fetch Active Student Roster by Gender
$males = [];
$females = [];

if ($section_id > 0) {
    // Fetch Male Roster
    $m_stmt = $pdo->prepare("SELECT id, full_name as name FROM users WHERE section_id = :section_id AND role = 'student' AND gender = 'Male' ORDER BY full_name ASC");
    $m_stmt->execute(['section_id' => $section_id]);
    $males = $m_stmt->fetchAll();

    // Fetch Female Roster
    $f_stmt = $pdo->prepare("SELECT id, full_name as name FROM users WHERE section_id = :section_id AND role = 'student' AND gender = 'Female' ORDER BY full_name ASC");
    $f_stmt->execute(['section_id' => $section_id]);
    $females = $f_stmt->fetchAll();
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
                <li><a href="ecr-inputdata.php">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php?term=<?= $term ?>" class="active">Encode Term Grades</a></li>
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
                            <h2>SHS Class Record — Term <?= $term ?></h2>
                            <p>DepEd Order No. 8, s. 2015 Framework | SY 2026–2027</p>
                        </div>
                    </div>
                    <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" class="logo-md">
                </header>

                <!-- Mobile Touch-Scroll Navigation -->
                <nav class="ecr-nav">
                    <a href="ecr-inputdata.php" class="nav-tab">INPUT DATA</a>
                    <a href="ecr-view.php?term=1" class="nav-tab <?= $term === 1 ? 'active' : '' ?>">TERM 1</a>
                    <a href="ecr-view.php?term=2" class="nav-tab <?= $term === 2 ? 'active' : '' ?>">TERM 2</a>
                    <a href="ecr-view.php?term=3" class="nav-tab <?= $term === 3 ? 'active' : '' ?>">TERM 3</a>
                    <a href="ecr-summary.php" class="nav-tab tab-summary">SUMMARY</a>
                </nav>

                <!-- Mobile Scrollable Table Wrapper -->
                <div class="table-responsive-wrapper">
                    <table class="ecr-grid">
                        <thead>
                            <tr>
                                <th rowspan="3" class="col-sticky student-col">LEARNERS' NAMES</th>
                                <th colspan="8" class="head-ww">WRITTEN WORKS (20%)</th>
                                <th colspan="6" class="head-pt">PERFORMANCE (50%)</th>
                                <th colspan="6" class="head-qa">EXAMS (30%)</th>
                                <th rowspan="3" class="col-calc">Initial</th>
                                <th rowspan="3" class="col-calc">Transmuted</th>
                                <th rowspan="3" class="col-calc">Grade</th>
                            </tr>
                            <tr>
                                <th>1</th>
                                <th>2</th>
                                <th>3</th>
                                <th>4</th>
                                <th>5</th>
                                <th>Total</th>
                                <th>PS</th>
                                <th>WS</th>
                                <th>1</th>
                                <th>2</th>
                                <th>3</th>
                                <th>Total</th>
                                <th>PS</th>
                                <th>WS</th>
                                <th>SA1</th>
                                <th>SA2</th>
                                <th>TE</th>
                                <th>Total</th>
                                <th>PS</th>
                                <th>WS</th>
                            </tr>
                            <tr class="hps-row">
                                <td>HIGHEST POSSIBLE SCORE</td>
                                <td><input type="number" class="grid-cell hps" id="hps_ww1" value="0"></td>
                                <td><input type="number" class="grid-cell hps" id="hps_ww2" value="0"></td>
                                <td><input type="number" class="grid-cell hps" id="hps_ww3" value="0"></td>
                                <td><input type="number" class="grid-cell hps" id="hps_ww4" value="0"></td>
                                <td><input type="number" class="grid-cell hps" id="hps_ww5" value="0"></td>
                                <td id="hps_ww_total" class="calc-val">0</td>
                                <td class="calc-val">100</td>
                                <td class="calc-val">20%</td>

                                <td><input type="number" class="grid-cell hps" id="hps_pt1" value="0"></td>
                                <td><input type="number" class="grid-cell hps" id="hps_pt2" value="0"></td>
                                <td><input type="number" class="grid-cell hps" id="hps_pt3" value="0"></td>
                                <td id="hps_pt_total" class="calc-val">0</td>
                                <td class="calc-val">100</td>
                                <td class="calc-val">50%</td>

                                <td><input type="number" class="grid-cell hps" id="hps_qa1" value="0"></td>
                                <td><input type="number" class="grid-cell hps" id="hps_qa2" value="0"></td>
                                <td><input type="number" class="grid-cell hps" id="hps_qa3" value="0"></td>
                                <td id="hps_qa_total" class="calc-val">0</td>
                                <td class="calc-val">100</td>
                                <td class="calc-val">30%</td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="group-header">
                                <td colspan="24">MALE</td>
                            </tr>
                            <?php if (empty($males)): ?>
                                <tr class="empty-row">
                                    <td colspan="24">No male students registered.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($males as $index => $student): ?>
                                    <tr class="student-row" data-id="<?= $student['id'] ?>">
                                        <td class="col-sticky student-col"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td class="total-ww calc-val">0</td>
                                        <td class="ps-ww calc-val">0.00</td>
                                        <td class="ws-ww calc-val">0.00%</td>

                                        <td><input type="number" class="grid-cell score pt"></td>
                                        <td><input type="number" class="grid-cell score pt"></td>
                                        <td><input type="number" class="grid-cell score pt"></td>
                                        <td class="total-pt calc-val">0</td>
                                        <td class="ps-pt calc-val">0.00</td>
                                        <td class="ws-pt calc-val">0.00%</td>

                                        <td><input type="number" class="grid-cell score qa"></td>
                                        <td><input type="number" class="grid-cell score qa"></td>
                                        <td><input type="number" class="grid-cell score qa"></td>
                                        <td class="total-qa calc-val">0</td>
                                        <td class="ps-qa calc-val">0.00</td>
                                        <td class="ws-qa calc-val">0.00%</td>

                                        <td class="initial-grade calc-val highlight">0.00</td>
                                        <td class="transmuted-grade calc-val highlight-main">60</td>
                                        <td class="letter-grade calc-val">Did Not Meet</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <tr class="group-header">
                                <td colspan="24">FEMALE</td>
                            </tr>
                            <?php if (empty($females)): ?>
                                <tr class="empty-row">
                                    <td colspan="24">No female students registered.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($females as $index => $student): ?>
                                    <tr class="student-row" data-id="<?= $student['id'] ?>">
                                        <td class="col-sticky student-col"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td><input type="number" class="grid-cell score ww"></td>
                                        <td class="total-ww calc-val">0</td>
                                        <td class="ps-ww calc-val">0.00</td>
                                        <td class="ws-ww calc-val">0.00%</td>

                                        <td><input type="number" class="grid-cell score pt"></td>
                                        <td><input type="number" class="grid-cell score pt"></td>
                                        <td><input type="number" class="grid-cell score pt"></td>
                                        <td class="total-pt calc-val">0</td>
                                        <td class="ps-pt calc-val">0.00</td>
                                        <td class="ws-pt calc-val">0.00%</td>

                                        <td><input type="number" class="grid-cell score qa"></td>
                                        <td><input type="number" class="grid-cell score qa"></td>
                                        <td><input type="number" class="grid-cell score qa"></td>
                                        <td class="total-qa calc-val">0</td>
                                        <td class="ps-qa calc-val">0.00</td>
                                        <td class="ws-qa calc-val">0.00%</td>

                                        <td class="initial-grade calc-val highlight">0.00</td>
                                        <td class="transmuted-grade calc-val highlight-main">60</td>
                                        <td class="letter-grade calc-val">Did Not Meet</td>
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