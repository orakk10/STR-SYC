<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (
    !isset($_SESSION['user_id']) ||
    !in_array(strtolower($_SESSION['role'] ?? ''), ['adviser', 'faculty'], true)
) {
    header('Location: ../../manifest/login.php');
    exit();
}

$schedule_stmt = $conn->prepare("
    SELECT DISTINCT
        sch.day_of_week,
        sch.start_time,
        sch.end_time,
        sch.room_number,
        sub.subject_name,
        sub.subject_code,
        sec.section_name
    FROM schedules sch
    JOIN subjects sub ON sub.id = sch.subject_id
    JOIN sections sec ON sec.id = sch.section_id
    JOIN subject_assignments sa
        ON sa.subject_id = sch.subject_id
        AND sa.section_id = sch.section_id
        AND sa.faculty_id = :faculty_id
    ORDER BY FIELD(sch.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
             sch.start_time ASC
");
$schedule_stmt->execute(['faculty_id' => $_SESSION['user_id']]);

$schedule_data = [];
foreach ($schedule_stmt->fetchAll(PDO::FETCH_ASSOC) as $class) {
    $schedule_data[$class['day_of_week']][] = $class;
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Variables for dynamic navigation
$nav_assignment_param = !empty($assignment_id) ? "?assignment_id={$assignment_id}" : "";
$nav_term_param = !empty($assignment_id) 
    ? "?assignment_id={$assignment_id}&term=" . ($term ?? 1) 
    : "?term=" . ($term ?? 1);

$user_role = strtolower($_SESSION['role'] ?? 'faculty');
$is_adviser_or_admin = in_array($user_role, ['adviser', 'admin'], true);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Class Schedule | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            height: auto;
            width: 100%;
            overflow: visible;
            position: relative;
        }

        main.content {
            flex-grow: 1;
            margin-left: 260px;
            padding: 30px;
            width: 100%;
            max-width: 100%;
            min-height: 100vh;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .day-column {
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .day-header {
            padding-bottom: 10px;
            margin: 0 0 15px;
            border-bottom: 2px solid #3b82f6;
            color: #1e293b;
            font-weight: 700;
        }

        .class-card {
            padding: 15px;
            margin-bottom: 12px;
            background: #ffffff;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .time-slot {
            display: block;
            margin-bottom: 5px;
            color: #3b82f6;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .subject-info {
            display: block;
            color: #0f172a;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .meta-info {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 5px;
            color: #64748b;
            font-size: 0.8rem;
        }

        .empty-day {
            padding: 20px;
            color: #94a3b8;
            font-size: 0.9rem;
            font-style: italic;
            text-align: center;
        }

        .mobile-toggle {
            display: none;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 999;
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 1.1rem;
            cursor: pointer;
            line-height: 1;
            margin-bottom: 15px;
            align-self: flex-start;
        }

        .sidebar-close {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0 5px;
            line-height: 1;
        }

        @media (max-width: 1024px) {
            html, body {
                margin: 0;
                padding: 0;
                width: 100%;
                min-height: 100%;
                height: auto;
                overflow-x: hidden;
                overflow-y: auto;
            }

            .dashboard-wrapper {
                display: block;
                width: 100%;
                min-height: 100vh;
                height: auto;
                overflow: visible;
                position: relative;
            }

            .sidebar {
                position: fixed;
                top: 0;
                left: -260px;
                width: 260px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s ease;
                overflow-y: auto;
            }

            .sidebar.active {
                left: 0;
            }

            .mobile-toggle {
                display: block;
                position: relative;
                z-index: 1000;
            }

            main.content {
                display: block;
                margin-left: 0 !important;
                width: 100%;
                max-width: 100%;
                height: auto !important;
                min-height: 100vh;
                padding: 15px;
                padding-top: 20px;
                box-sizing: border-box;
                overflow: visible !important;
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
    <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>

    <div class="dashboard-wrapper">
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
            <header class="content-header">
                <h1>My Class Schedule</h1>
                <p>Weekly timetable for your assigned classes.</p>
            </header>

            <div class="schedule-grid">
                <?php foreach ($days as $day): ?>
                    <section class="day-column">
                        <h2 class="day-header"><?= htmlspecialchars($day) ?></h2>
                        <?php if (!empty($schedule_data[$day])): ?>
                            <?php foreach ($schedule_data[$day] as $class): ?>
                                <article class="class-card">
                                    <span class="time-slot">
                                        <?= date('g:i A', strtotime($class['start_time'])) ?> -
                                        <?= date('g:i A', strtotime($class['end_time'])) ?>
                                    </span>
                                    <span class="subject-info">
                                        <?= htmlspecialchars($class['subject_name']) ?>
                                        (<?= htmlspecialchars($class['subject_code']) ?>)
                                    </span>
                                    <div class="meta-info">
                                        <span><?= htmlspecialchars($class['section_name']) ?></span>
                                        <span>Rm: <?= htmlspecialchars($class['room_number'] ?: 'TBA') ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-day">No classes scheduled</div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>