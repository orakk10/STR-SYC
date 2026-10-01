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

// Infer Track based on strand_code or strand_name
$strand_identifier = ($teacher_data['strand_code'] ?? '') . ' ' . ($teacher_data['strand_name'] ?? '');
$is_tech_track = (stripos($strand_identifier, 'TVL') !== false || stripos($strand_identifier, 'ICT') !== false);

// 4. Fetch Active Student Roster by Gender
$male_students = [];
$female_students = [];

if ($section_id > 0) {
    // Fetch Male Roster
    $m_stmt = $pdo->prepare("
        SELECT u.id, u.username AS lrn, u.full_name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND (sp.gender = 'Male' OR sp.gender IS NULL)
        ORDER BY u.full_name ASC
    ");
    $m_stmt->execute(['section_id' => $section_id]);
    $male_students = $m_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Female Roster
    $f_stmt = $pdo->prepare("
        SELECT u.id, u.username AS lrn, u.full_name 
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.section_id = :section_id AND u.role = 'student' AND sp.gender = 'Female'
        ORDER BY u.full_name ASC
    ");
    $f_stmt->execute(['section_id' => $section_id]);
    $female_students = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
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
        /* Base Page Setup */
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }

        main.content {
            margin-left: 260px;
            padding: 30px;
            width: calc(100% - 260px);
            box-sizing: border-box;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #sidebar:not(.active) ~ main.content {
            margin-left: 0;
            width: 100%;
        }

        /* ECR Card & Header Custom Styling */
        .ecr-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        /* Fixed High Contrast Card Headers */
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
            gap: 15px;
        }

        .field-group label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
            white-space: nowrap;
        }

        .form-input {
            width: 65%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #f8fafc;
            font-size: 0.85rem;
        }

        /* Responsive Grid Layout */
        .input-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
        }

        /* Roster Display Styles */
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

        /* Mobile View Rules */
        @media (max-width: 992px) {
            main.content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 15px;
                padding-top: 60px;
            }

            .input-layout {
                grid-template-columns: 1fr;
            }

            .field-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .form-input {
                width: 100%;
            }

            .ecr-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .btn-proceed {
                width: 100%;
                text-align: center;
                box-sizing: border-box;
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
                <li><a href="faculty_dashboard.php">Dashboard</a></li>
                <li><a href="faculty_schedule.php">Manage Schedule</a></li>
                <li><a href="ecr-inputdata.php" class="active">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php">E-Class Record Hub</a></li>
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
                        <h2 style="margin: 0; color: #0f172a; font-size: 1.25rem;">Input Data Sheet for Electronic-Class Record (ECR)</h2>
                        <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.85rem;">Strengthened Senior High School System - SY 2026-2027</p>
                    </div>
                    <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" style="max-height: 50px;">
                </header>

                <div class="input-layout">
                    <!-- Left Column: School & Class Configuration -->
                    <div class="config-column">
                        <div class="ecr-card">
                            <div class="card-title">SCHOOL INFO</div>
                            <div class="card-content">
                                <div class="field-group">
                                    <label>REGION:</label>
                                    <input type="text" name="region" value="Region III" class="form-input" readonly>
                                </div>
                                <div class="field-group">
                                    <label>DIVISION:</label>
                                    <input type="text" name="division" value="Tarlac" class="form-input" readonly>
                                </div>
                                <div class="field-group">
                                    <label>SCHOOL ID:</label>
                                    <input type="text" name="school_id" value="301000" class="form-input" readonly>
                                </div>
                                <div class="field-group">
                                    <label>SCHOOL YEAR:</label>
                                    <input type="text" name="school_year" value="2026-2027" class="form-input" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="ecr-card">
                            <div class="card-title">CLASS INFO</div>
                            <div class="card-content">
                                <div class="field-group">
                                    <label>TEACHER:</label>
                                    <input type="text" name="teacher_name" value="<?= htmlspecialchars($teacher_data['full_name'] ?? 'Unassigned') ?>" class="form-input" readonly>
                                </div>
                                <div class="field-group">
                                    <label>TRACK:</label>
                                    <input type="text" value="<?= $is_tech_track ? 'TECHNICAL PROFESSIONAL / TVL' : 'ACADEMIC' ?>" class="form-input" readonly>
                                </div>
                                <div class="field-group">
                                    <label>SECTION:</label>
                                    <input type="text" name="section" value="<?= htmlspecialchars($teacher_data['section_name'] ?? 'Unassigned') ?>" class="form-input" readonly>
                                </div>
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
                                    <div class="gender-tag male-tag">MALE (<?= count($male_students) ?> Enrolled)</div>
                                    <div class="roster-list">
                                        <?php if (!empty($male_students)): ?>
                                            <?php foreach ($male_students as $idx => $student): ?>
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
                                    <div class="gender-tag female-tag">FEMALE (<?= count($female_students) ?> Enrolled)</div>
                                    <div class="roster-list">
                                        <?php if (!empty($female_students)): ?>
                                            <?php foreach ($female_students as $idx => $student): ?>
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
                            <a href="ecr-view.php" class="btn-proceed">Proceed to Class Hub & Grade Encoding →</a>
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
    </script>
</body>

</html>