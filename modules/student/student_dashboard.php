<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Case-insensitive role fallback verification
$session_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';
if ($session_role !== 'student') {
    header("Location: login.php?error=unauthorized_role");
    exit();
}

$user_id = $_SESSION['user_id'];

$query = "SELECT u.full_name, u.username as lrn, u.section_id, s.id as sec_id, s.section_name, s.grade_level, str.strand_name 
          FROM users u 
          LEFT JOIN sections s ON u.section_id = s.id 
          LEFT JOIN strands str ON s.strand_id = str.id
          WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student_data = $stmt->get_result()->fetch_assoc();

$current_section_id = $student_data['sec_id'] ?? null;
$current_grade_level = $student_data['grade_level'] ?? null;

$matrix_query = "
    SELECT g.quarter1_grade, g.quarter2_grade, g.final_grade, g.remarks,
           sub.subject_name, sub.subject_code, sub.grade_level, sub.semester
    FROM grades g
    INNER JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.student_id = ?
    ORDER BY 
        CASE 
            WHEN sub.grade_level LIKE '%11%' THEN 1 
            WHEN sub.grade_level LIKE '%12%' THEN 2 
            ELSE 3 
        END ASC,
        CASE 
            WHEN sub.semester LIKE '%1st%' THEN 1 
            WHEN sub.semester LIKE '%2nd%' THEN 2 
            ELSE 3 
        END ASC,
        sub.subject_name ASC";

$matrix_stmt = $conn->prepare($matrix_query);

if (!$matrix_stmt) {
    die("Database Schema Error: " . $conn->error);
}

$matrix_stmt->bind_param("i", $user_id);
$matrix_stmt->execute();
$matrix_res = $matrix_stmt->get_result();

$academic_matrix = [];
$total_subjects = 0;
$passed_subjects = 0;
$total_graded_value = 0;
$graded_subjects_count = 0;

while ($row = $matrix_res->fetch_assoc()) {
    $g_level = $row['grade_level'];
    $sem = $row['semester'];

    $row['teacher_name'] = 'Assigned Faculty';

    $academic_matrix[$g_level][$sem][] = $row;

    if ($current_grade_level && strpos($g_level, (string)$current_grade_level) !== false) {
        $total_subjects++;
        $final = $row['final_grade'];
        if ($final > 0) {
            $total_graded_value += $final;
            $graded_subjects_count++;
            if ($final >= 75) {
                $passed_subjects++;
            }
        }
    }
}

$general_average = ($graded_subjects_count > 0) ? ($total_graded_value / $graded_subjects_count) : 0.00;
$progress_percentage = ($total_subjects > 0) ? round(($passed_subjects / $total_subjects) * 100) : 0;

if (!$current_section_id) {
    $total_subjects = 0;
    $progress_percentage = 0;
}

$is_already_registered = false;
$reg_check = $conn->prepare("SELECT id FROM grade12_registrations WHERE student_id = ?");
if ($reg_check) {
    $reg_check->bind_param("i", $user_id);
    $reg_check->execute();
    if ($reg_check->get_result()->num_rows > 0) {
        $is_already_registered = true;
    }
}

$announcements = null;
if ($current_section_id) {
    $ann_query = "
        SELECT a.*, u.full_name as teacher_name 
        FROM announcements a
        JOIN users u ON a.sender_id = u.id
        WHERE (a.target_type = 'section' AND a.section_id = ?)
        OR (a.target_type = 'subject' AND a.subject_id IN (
            SELECT subject_id FROM subject_assignments WHERE section_id = ?
        ))
        ORDER BY a.created_at DESC LIMIT 3";
    $stmt_ann = $conn->prepare($ann_query);
    $stmt_ann->bind_param("ii", $current_section_id, $current_section_id);
    $stmt_ann->execute();
    $announcements = $stmt_ann->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | STRAND-SYNC</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
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
            flex-grow: 1;
            margin-left: 260px;
            padding: 40px;
            width: calc(100% - 260px);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .welcome-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);
            position: relative;
            overflow: hidden;
        }

        .progress-container {
            margin-top: 25px;
            max-width: 500px;
        }

        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: #94a3b8;
        }

        .progress-track {
            background: rgba(255, 255, 255, 0.1);
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            width: 100%;
        }

        .progress-bar {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            height: 100%;
            border-radius: 999px;
            width: 0%;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .registration-card {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .registration-details h3 {
            margin: 0 0 6px 0;
            color: #1e3a8a;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .registration-details p {
            margin: 0;
            color: #1e40af;
            font-size: 0.95rem;
        }

        .btn-register {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
        }

        .registration-complete-badge {
            background: #dcfce7;
            color: #15803d;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            border: 1px solid #bbf7d0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            background: #f1f5f9;
            color: #3b82f6;
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-card.success-theme .stat-icon {
            background: #ecfdf5;
            color: #10b981;
        }

        .stat-card.warning-theme .stat-icon {
            background: #fffbeb;
            color: #f59e0b;
        }

        .stat-info h3 {
            margin: 4px 0 0 0;
            font-size: 1.6rem;
            color: #0f172a;
            font-weight: 700;
        }

        .stat-info small {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-info p {
            font-size: 0.85rem;
            color: #64748b;
            margin: 2px 0 0 0;
        }

        .dashboard-layout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .panel-box {
            background: white;
            padding: 25px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .panel-title {
            font-size: 1.1rem;
            margin: 0 0 20px 0;
            color: #0f172a;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
        }

        .panel-title h2 {
            font-size: 1.25rem;
            margin: 0;
        }

        .semester-block {
            margin-top: 20px;
        }

        .semester-title {
            background: #f8fafc;
            padding: 8px 14px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #475569;
            border-radius: 6px;
            margin-bottom: 12px;
            border-left: 3px solid #2563eb;
        }

        .ann-item {
            padding: 16px;
            border-radius: 10px;
            background: #f8fafc;
            margin-bottom: 12px;
            border-left: 4px solid #cbd5e1;
        }

        .ann-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .ann-tag {
            font-size: 0.65rem;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 700;
        }

        .tag-section {
            background: #e0f2fe;
            color: #0369a1;
        }

        .tag-subject {
            background: #fef3c7;
            color: #b45309;
        }

        .subject-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .subject-item {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .subject-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
        }

        .quarter-breakdown {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 4px;
        }

        .grade-pill {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            background: #f1f5f9;
            color: #475569;
        }

        .grade-pill.passed {
            background: #dcfce7;
            color: #15803d;
        }

        .grade-pill.failed {
            background: #fee2e2;
            color: #b91c1c;
        }

        .mobile-toggle {
            display: none;
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 1.1rem;
            cursor: pointer;
        }

        .sidebar-close {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        @media (max-width: 1200px) {
            .dashboard-layout-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: -260px;
                transition: 0.3s;
                z-index: 1050;
            }

            .sidebar.active {
                left: 0;
            }

            .mobile-toggle {
                display: block;
                margin-bottom: 20px;
                align-self: flex-start;
            }

            main.content {
                margin-left: 0 !important;
                width: 100%;
                padding: 20px;
            }

            #sidebar.active .sidebar-close {
                display: block;
            }
        }

        #sidebar:not(.active)~main.content {
            margin-left: 0;
            width: 100%;
        }
    </style>
</head>

<body>

    <div class="dashboard-wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="student_dashboard.php" class="active">My Dashboard</a></li>
                <li><a href="student_announcements.php">Announcements</a></li>
                <li><a href="student_grades.php">My Grades</a></li>
                <li><a href="student_profile.php">Account Settings</a></li>
                <li><a href="logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>

            <div class="welcome-card">
                <h1 style="margin: 0 0 8px 0; font-size: 2rem; font-weight: 700;">Hello, <?php echo htmlspecialchars(explode(' ', $student_data['full_name'] ?? 'Student')[0]); ?>!</h1>
                <p style="margin: 0; color: #cbd5e1; font-size: 1rem;">Track your performance evaluations and standard class milestones.</p>

                <div class="progress-container">
                    <div class="progress-labels">
                        <span>Active Year Completion Rate</span>
                        <strong><?php echo $progress_percentage; ?>% Passed</strong>
                    </div>
                    <div class="progress-track">
                        <div class="progress-bar" id="academicProgressBar"></div>
                    </div>
                </div>
            </div>

            <?php if ($current_grade_level == 11 && $progress_percentage === 100): ?>
                <div class="registration-card">
                    <div class="registration-details">
                        <h3>🎉 New Notification: Grade 11 Cleared</h3>
                        <p>You are now cleared to early register your tracker intent for Grade 12 tracking operations.</p>
                    </div>
                    <div class="registration-actions">
                        <?php if ($is_already_registered): ?>
                            <span class="registration-complete-badge">✓ Intent Registered</span>
                        <?php else: ?>
                            <a href="register_grade12.php" class="btn-register">Register for Grade 12</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <small>Current Placement</small>
                        <h3><?php echo $current_grade_level ? 'Grade ' . $current_grade_level : 'Unassigned'; ?></h3>
                        <p><?php echo htmlspecialchars($student_data['section_name'] ?? 'Awaiting Promotion'); ?></p>
                    </div>
                </div>

                <div class="stat-card success-theme">
                    <div class="stat-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <small>Active Term Average</small>
                        <h3><?php echo ($general_average > 0) ? number_format($general_average, 2) : 'N/A'; ?></h3>
                        <p><?php echo ($general_average >= 75) ? 'Passing Status' : 'No Active Grades'; ?></p>
                    </div>
                </div>

                <div class="stat-card warning-theme">
                    <div class="stat-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v6a2 2 0 012-2m14-6V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <small>Strand Tracking</small>
                        <h3 style="font-size: 1.15rem; margin-top: 8px;"><?php echo htmlspecialchars($student_data['strand_name'] ?? 'General Track'); ?></h3>
                        <p>LRN: <?php echo htmlspecialchars($student_data['lrn'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>

            <div class="dashboard-layout-grid">
                <div class="main-stats-column">
                    <?php if (!empty($academic_matrix)): ?>
                        <?php foreach ($academic_matrix as $grade_level => $semesters): ?>
                            <section class="panel-box">
                                <div class="panel-title">
                                    <h2>Academic Records: <?php echo htmlspecialchars($grade_level); ?></h2>
                                </div>

                                <?php foreach ($semesters as $semester => $subjects): ?>
                                    <div class="semester-block">
                                        <div class="semester-title"><?php echo htmlspecialchars($semester); ?> Semester</div>
                                        <div class="subject-list">
                                            <?php foreach ($subjects as $sub):
                                                $grade = $sub['final_grade'];
                                                $grade_class = '';
                                                $grade_text = 'No Grade';

                                                if ($grade > 0) {
                                                    $grade_text = number_format($grade, 2);
                                                    $grade_class = ($grade >= 75) ? 'passed' : 'failed';
                                                }
                                            ?>
                                                <div class="subject-item">
                                                    <div>
                                                        <span class="subject-badge"><?php echo htmlspecialchars($sub['subject_code']); ?></span>
                                                        <strong style="display:block; margin-top: 8px; color: #0f172a;"><?php echo htmlspecialchars($sub['subject_name']); ?></strong>
                                                        <div class="quarter-breakdown">
                                                            Q1: <?php echo $sub['quarter1_grade'] > 0 ? number_format($sub['quarter1_grade'], 1) : '--'; ?> |
                                                            Q2: <?php echo $sub['quarter2_grade'] > 0 ? number_format($sub['quarter2_grade'], 1) : '--'; ?>
                                                        </div>
                                                    </div>
                                                    <div style="text-align: right;">
                                                        <span class="grade-pill <?php echo $grade_class; ?>"><?php echo $grade_text; ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <section class="panel-box">
                            <p style="color: #64748b; text-align: center; padding: 20px;">No encoded rows found for this account.</p>
                        </section>
                    <?php endif; ?>
                </div>

                <div class="secondary-feed-column">
                    <section class="panel-box">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="font-size: 1.1rem; margin: 0; color: #0f172a; font-weight: 700;">Latest Bulletins</h2>
                        </div>

                        <?php if ($announcements && $announcements->num_rows > 0): ?>
                            <?php while ($ann = $announcements->fetch_assoc()): ?>
                                <div class="ann-item">
                                    <div class="ann-meta">
                                        <span class="ann-tag <?php echo ($ann['target_type'] == 'section') ? 'tag-section' : 'tag-subject'; ?>">
                                            <?php echo htmlspecialchars($ann['target_type']); ?>
                                        </span>
                                        <small style="color: #94a3b8;"><?php echo date('M d', strtotime($ann['created_at'])); ?></small>
                                    </div>
                                    <strong style="display: block; color: #1e293b; font-size: 0.9rem; margin-bottom: 4px;"><?php echo htmlspecialchars($ann['title']); ?></strong>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #64748b; font-size: 0.9rem; margin: 0;">No notifications active.</p>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const burger = document.getElementById('mobileBurger');
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                if (burger) burger.style.display = 'none';
            } else {
                if (burger) burger.style.display = 'block';
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(() => {
                const progressBar = document.getElementById('academicProgressBar');
                if (progressBar) progressBar.style.width = '<?php echo $progress_percentage; ?>%';
            }, 150);
        });
    </script>
</body>

</html>