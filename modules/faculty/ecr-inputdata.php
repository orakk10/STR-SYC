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

// Infer Track based on strand_code or strand_name
$strand_identifier = ($teacher_data['strand_code'] ?? '') . ' ' . ($teacher_data['strand_name'] ?? '');
$is_tech_track = (stripos($strand_identifier, 'TVL') !== false || stripos($strand_identifier, 'ICT') !== false);

// 4. Fetch Active Student Roster by Gender
$male_students = [];
$female_students = [];

if ($section_id > 0) {
    // Fetch Male Roster
    $m_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE section_id = :section_id AND role = 'student' AND gender = 'Male' ORDER BY full_name ASC");
    $m_stmt->execute(['section_id' => $section_id]);
    $male_students = $m_stmt->fetchAll();

    // Fetch Female Roster
    $f_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE section_id = :section_id AND role = 'student' AND gender = 'Female' ORDER BY full_name ASC");
    $f_stmt->execute(['section_id' => $section_id]);
    $female_students = $f_stmt->fetchAll();
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
                <li><a href="ecr-inputdata.php" class="active">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php?term=1">Encode Term Grades</a></li>
                <li><a href="ecr-summary.php">ECR Summary</a></li>
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
                        <h2>Input Data Sheet for Electronic-Class Record (ECR)</h2>
                        <p>Strengthened Senior High School System - SY 2026-2027</p>
                    </div>
                    <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" class="logo-md">
                </header>

                <!-- Navigation Tabs -->
                <nav class="ecr-nav">
                    <a href="ecr-inputdata.php" class="nav-tab active">INPUT DATA</a>
                    <a href="ecr-view.php?term=1" class="nav-tab">TERM 1</a>
                    <a href="ecr-view.php?term=2" class="nav-tab">TERM 2</a>
                    <a href="ecr-view.php?term=3" class="nav-tab">TERM 3</a>
                    <a href="ecr-summary.php" class="nav-tab tab-summary">SUMMARY</a>
                </nav>

                <form action="../../api/save-ecr-input.php" method="POST" class="input-layout">
                    <input type="hidden" name="section_id" value="<?= htmlspecialchars($section_id) ?>">

                    <!-- Left Column: School & Class Configuration -->
                    <div class="config-column">
                        <div class="ecr-card">
                            <div class="card-title">SCHOOL INFO</div>
                            <div class="card-content">
                                <div class="field-group">
                                    <label>REGION:</label>
                                    <input type="text" name="region" value="Region III" class="form-input" required>
                                </div>
                                <div class="field-group">
                                    <label>DIVISION:</label>
                                    <input type="text" name="division" value="Tarlac" class="form-input" required>
                                </div>
                                <div class="field-group">
                                    <label>SCHOOL ID:</label>
                                    <input type="text" name="school_id" value="301000" class="form-input" required>
                                </div>
                                <div class="field-group">
                                    <label>SCHOOL NAME:</label>
                                    <input type="text" name="school_name" placeholder="School Name" class="form-input" required>
                                </div>
                                <div class="field-group">
                                    <label>SCHOOL YEAR:</label>
                                    <input type="text" name="school_year" value="2026-2027" class="form-input" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="ecr-card">
                            <div class="card-title">CLASS & SUBJECT INFO</div>
                            <div class="card-content">
                                <div class="field-group">
                                    <label>TEACHER:</label>
                                    <input type="text" name="teacher_name" value="<?= htmlspecialchars($teacher_data['full_name'] ?? '') ?>" class="form-input" required>
                                </div>
                                <div class="field-group">
                                    <label>TRACK:</label>
                                    <select name="track" class="form-input" required>
                                        <option value="ACADEMIC" <?= !$is_tech_track ? 'selected' : '' ?>>ACADEMIC</option>
                                        <option value="TECHNICAL" <?= $is_tech_track ? 'selected' : '' ?>>TECHNICAL PROFESSIONAL / TVL</option>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label>GRADE LEVEL:</label>
                                    <select name="grade_level" class="form-input">
                                        <option value="11" <?= ($teacher_data['grade_level'] ?? '') == '11' ? 'selected' : '' ?>>Grade 11</option>
                                        <option value="12" <?= ($teacher_data['grade_level'] ?? '') == '12' ? 'selected' : '' ?>>Grade 12</option>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label>SECTION:</label>
                                    <input type="text" name="section" value="<?= htmlspecialchars($teacher_data['section_name'] ?? '') ?>" class="form-input" required>
                                </div>
                                <div class="field-group">
                                    <label>SUBJECT TYPE:</label>
                                    <select name="subject_type" class="form-input">
                                        <option value="Core">Core Subject</option>
                                        <option value="Applied">Applied Subject</option>
                                        <option value="Specialized">Specialized Subject</option>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label>SUBJECT:</label>
                                    <input type="text" name="subject_name" placeholder="Subject Title" class="form-input" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Dynamic Student Roster -->
                    <div class="roster-column">
                        <div class="ecr-card">
                            <div class="card-title">LEARNERS' ROSTER</div>
                            <div class="roster-tables">
                                <!-- Male Section -->
                                <div class="gender-block">
                                    <div class="gender-tag male-tag">MALE</div>
                                    <div class="roster-list">
                                        <?php for ($i = 1; $i <= 25; $i++): ?>
                                            <?php $student = $male_students[$i-1] ?? null; ?>
                                            <div class="roster-entry">
                                                <span class="entry-index"><?= $i ?></span>
                                                <input type="hidden" name="male_student_ids[]" value="<?= $student['id'] ?? '' ?>">
                                                <input type="text" name="male_students[]" 
                                                       value="<?= htmlspecialchars($student['full_name'] ?? '') ?>" 
                                                       placeholder="Last Name, First Name M.I." class="roster-field">
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- Female Section -->
                                <div class="gender-block">
                                    <div class="gender-tag female-tag">FEMALE</div>
                                    <div class="roster-list">
                                        <?php for ($i = 1; $i <= 25; $i++): ?>
                                            <?php $student = $female_students[$i-1] ?? null; ?>
                                            <div class="roster-entry">
                                                <span class="entry-index"><?= $i ?></span>
                                                <input type="hidden" name="female_student_ids[]" value="<?= $student['id'] ?? '' ?>">
                                                <input type="text" name="female_students[]" 
                                                       value="<?= htmlspecialchars($student['full_name'] ?? '') ?>" 
                                                       placeholder="Last Name, First Name M.I." class="roster-field">
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-save">Save Roster & Setup</button>
                    </div>
                </form>
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
    </script>
</body>

</html>