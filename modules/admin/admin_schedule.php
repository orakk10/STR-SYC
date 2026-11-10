<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";

// 2. Handle Form Submission with Room & Section Conflict Check
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_schedule'])) {
    $section_id = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT) ?: 0;
    $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT) ?: 0;
    $day        = $_POST['day_of_week'] ?? '';
    $start      = $_POST['start_time'] ?? '';
    $end        = $_POST['end_time'] ?? '';
    $room       = trim($_POST['room_number'] ?? '');
    $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    if (!$section_id || !$subject_id || $room === '') {
        $message = "missing_fields";
    } elseif (!in_array($day, $valid_days, true)) {
        $message = "invalid_day";
    } elseif (
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
        || $end <= $start
    ) {
        $message = "invalid_time";
    } elseif (strlen($room) > 50) {
        $message = "invalid_room";
    } else {
        $assignment_stmt = $conn->prepare("
            SELECT sa.faculty_id
            FROM sections sec
            INNER JOIN subject_assignments sa
                ON sa.section_id = sec.id
                AND sa.academic_year = sec.academic_year
            WHERE sec.id = :section_id AND sa.subject_id = :subject_id
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'section' AND ra.resource_id = sec.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'assignment' AND ra.resource_id = sa.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'subject' AND ra.resource_id = sa.subject_id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'strand' AND ra.resource_id = sec.strand_id
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM subjects active_subject
                    INNER JOIN resource_archives subject_strand_archive
                        ON subject_strand_archive.resource_type = 'strand'
                        AND subject_strand_archive.resource_id = active_subject.strand_id
                    WHERE active_subject.id = sa.subject_id
                )
            LIMIT 1
        ");
        $assignment_stmt->execute([
            ':section_id' => $section_id,
            ':subject_id' => $subject_id,
        ]);
        $faculty_id = $assignment_stmt->fetchColumn();

        if ($faculty_id === false) {
            $message = "invalid_subject";
        } else {
            $check_query = "
                SELECT sch.section_id, sch.room_number,
                    EXISTS (
                        SELECT 1
                        FROM sections scheduled_section
                        INNER JOIN subject_assignments scheduled_assignment
                            ON scheduled_assignment.section_id = scheduled_section.id
                            AND scheduled_assignment.academic_year = scheduled_section.academic_year
                        WHERE scheduled_section.id = sch.section_id
                            AND scheduled_assignment.subject_id = sch.subject_id
                            AND scheduled_assignment.faculty_id = :faculty_id_select
                            AND NOT EXISTS (
                                SELECT 1 FROM resource_archives ra
                                WHERE ra.resource_type = 'section' AND ra.resource_id = scheduled_section.id
                            )
                            AND NOT EXISTS (
                                SELECT 1 FROM resource_archives ra
                                WHERE ra.resource_type = 'strand' AND ra.resource_id = scheduled_section.strand_id
                            )
                            AND NOT EXISTS (
                                SELECT 1 FROM resource_archives ra
                                WHERE ra.resource_type = 'assignment' AND ra.resource_id = scheduled_assignment.id
                            )
                    ) AS faculty_conflict
                FROM schedules sch
                WHERE sch.day_of_week = :day
                    AND NOT EXISTS (
                        SELECT 1 FROM resource_archives ra
                        WHERE ra.resource_type = 'schedule' AND ra.resource_id = sch.id
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM resource_archives ra
                        WHERE ra.resource_type = 'section' AND ra.resource_id = sch.section_id
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM resource_archives ra
                        WHERE ra.resource_type = 'subject' AND ra.resource_id = sch.subject_id
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM sections archived_strand_section
                        INNER JOIN resource_archives strand_archive
                            ON strand_archive.resource_type = 'strand'
                            AND strand_archive.resource_id = archived_strand_section.strand_id
                        WHERE archived_strand_section.id = sch.section_id
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM subjects archived_strand_subject
                        INNER JOIN resource_archives strand_archive
                            ON strand_archive.resource_type = 'strand'
                            AND strand_archive.resource_id = archived_strand_subject.strand_id
                        WHERE archived_strand_subject.id = sch.subject_id
                    )
                    AND (
                        sch.section_id = :section_id
                        OR sch.room_number = :room
                        OR EXISTS (
                            SELECT 1
                            FROM sections scheduled_section
                            INNER JOIN subject_assignments scheduled_assignment
                                ON scheduled_assignment.section_id = scheduled_section.id
                                AND scheduled_assignment.academic_year = scheduled_section.academic_year
                            WHERE scheduled_section.id = sch.section_id
                                AND scheduled_assignment.subject_id = sch.subject_id
                                AND scheduled_assignment.faculty_id = :faculty_id_match
                                AND NOT EXISTS (
                                    SELECT 1 FROM resource_archives ra
                                    WHERE ra.resource_type = 'section' AND ra.resource_id = scheduled_section.id
                                )
                                AND NOT EXISTS (
                                    SELECT 1 FROM resource_archives ra
                                    WHERE ra.resource_type = 'strand' AND ra.resource_id = scheduled_section.strand_id
                                )
                                AND NOT EXISTS (
                                    SELECT 1 FROM resource_archives ra
                                    WHERE ra.resource_type = 'assignment' AND ra.resource_id = scheduled_assignment.id
                                )
                        )
                    )
                    AND sch.start_time < :end_time AND sch.end_time > :start_time
                LIMIT 1
            ";

            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([
                ':day' => $day,
                ':section_id' => $section_id,
                ':room' => $room,
                ':end_time' => $end,
                ':start_time' => $start,
                ':faculty_id_select' => $faculty_id,
                ':faculty_id_match' => $faculty_id,
            ]);
            $conflict = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                if ((int) $conflict['section_id'] === $section_id) {
                    $message = "section_conflict";
                } elseif (strcasecmp($conflict['room_number'], $room) === 0) {
                    $message = "room_conflict";
                } elseif ((int) $conflict['faculty_conflict'] === 1) {
                    $message = "faculty_conflict";
                }
            } else {
                try {
                    $conn->beginTransaction();

                    $insert_query = "INSERT INTO schedules (section_id, subject_id, day_of_week, start_time, end_time, room_number) VALUES (:section_id, :subject_id, :day, :start_time, :end_time, :room)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->execute([
                        ':section_id' => $section_id,
                        ':subject_id' => $subject_id,
                        ':day' => $day,
                        ':start_time' => $start,
                        ':end_time' => $end,
                        ':room' => $room,
                    ]);
                    $schedule_id = (int)$conn->lastInsertId();

                    $logStmt = $conn->prepare("
                        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
                        VALUES (:user_id, :action, 'schedules', :ip_address)
                    ");
                    $logStmt->execute([
                        'user_id'    => (int)$_SESSION['user_id'],
                        'action'     => "Created schedule {$schedule_id} for section {$section_id}, subject {$subject_id}.",
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    ]);

                    $conn->commit();
                    $message = 'success';
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    error_log("Schedule creation failed: " . $e->getMessage());
                    $message = "save_failed";
                }
            }
        }
    }
}

// 4. Data for Stats & Select Inputs
$activeSchedulesFilter = "
    NOT EXISTS (
        SELECT 1 FROM resource_archives ra
        WHERE ra.resource_type = 'schedule' AND ra.resource_id = schedules.id
    )
    AND EXISTS (
        SELECT 1
        FROM sections active_section
        WHERE active_section.id = schedules.section_id
            AND NOT EXISTS (
                SELECT 1 FROM resource_archives ra
                WHERE ra.resource_type = 'section' AND ra.resource_id = active_section.id
            )
            AND NOT EXISTS (
                SELECT 1 FROM resource_archives ra
                WHERE ra.resource_type = 'strand' AND ra.resource_id = active_section.strand_id
            )
    )
    AND EXISTS (
        SELECT 1
        FROM subjects active_subject
        WHERE active_subject.id = schedules.subject_id
            AND NOT EXISTS (
                SELECT 1 FROM resource_archives ra
                WHERE ra.resource_type = 'subject' AND ra.resource_id = active_subject.id
            )
            AND NOT EXISTS (
                SELECT 1 FROM resource_archives ra
                WHERE ra.resource_type = 'strand' AND ra.resource_id = active_subject.strand_id
            )
    )
    AND EXISTS (
        SELECT 1
        FROM subject_assignments active_assignment
        WHERE active_assignment.section_id = schedules.section_id
            AND active_assignment.subject_id = schedules.subject_id
            AND NOT EXISTS (
                SELECT 1 FROM resource_archives ra
                WHERE ra.resource_type = 'assignment' AND ra.resource_id = active_assignment.id
            )
    )";
$total_classes = (int) $conn->query("SELECT COUNT(*) FROM schedules WHERE {$activeSchedulesFilter}")->fetchColumn();
$total_rooms   = (int) $conn->query("SELECT COUNT(DISTINCT room_number) FROM schedules WHERE {$activeSchedulesFilter}")->fetchColumn();
$sections = $conn->query("
    SELECT s.id, s.section_name
    FROM sections s
    WHERE NOT EXISTS (
        SELECT 1 FROM resource_archives ra
        WHERE ra.resource_type = 'section' AND ra.resource_id = s.id
    )
    AND (
        s.strand_id IS NULL OR NOT EXISTS (
            SELECT 1 FROM resource_archives ra
            WHERE ra.resource_type = 'strand' AND ra.resource_id = s.strand_id
        )
    )
    ORDER BY s.section_name
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Table Data with Filters
$filter_section = isset($_GET['filter_section']) ? (int) $_GET['filter_section'] : null;
$sched_query = "SELECT sch.*, s.section_name, sub.subject_name, sub.subject_code 
                FROM schedules sch 
                JOIN sections s ON sch.section_id = s.id 
                JOIN subjects sub ON sch.subject_id = sub.id
                WHERE NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'schedule' AND ra.resource_id = sch.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'section' AND ra.resource_id = s.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'strand' AND ra.resource_id = s.strand_id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'subject' AND ra.resource_id = sub.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'strand' AND ra.resource_id = s.strand_id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM resource_archives ra
                    WHERE ra.resource_type = 'strand' AND ra.resource_id = sub.strand_id
                )
                AND (
                    sub.strand_id IS NULL OR NOT EXISTS (
                        SELECT 1 FROM resource_archives ra
                        WHERE ra.resource_type = 'strand' AND ra.resource_id = sub.strand_id
                    )
                )
                AND EXISTS (
                    SELECT 1
                    FROM subject_assignments sa
                    WHERE sa.section_id = sch.section_id
                        AND sa.subject_id = sch.subject_id
                        AND NOT EXISTS (
                            SELECT 1 FROM resource_archives ra
                            WHERE ra.resource_type = 'assignment' AND ra.resource_id = sa.id
                        )
                )";
$params = [];
if ($filter_section > 0) {
    $sched_query .= " AND sch.section_id = :section_id";
    $params[':section_id'] = $filter_section;
}
$sched_query .= " ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time ASC";
$sched_stmt = $conn->prepare($sched_query);
$sched_stmt->execute($params);
$schedules = $sched_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        /* Layered Sidebar Fixes */
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
            height: auto;
            box-sizing: border-box;
            display: flex; 
            flex-direction: column; 
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: visible;
        }

        .table-container { 
            height: auto;
            overflow-y: visible;
            overflow-x: auto; 
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .mobile-toggle {
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 999;
        }

        .mobile-toggle.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
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

        .sidebar {
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.25);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.4);
        }

       @media (max-width: 1024px) {
            html,
            body {
                height: auto;
                min-height: 100%;
                overflow-x: hidden;
                overflow-y: auto;
            }

            .dashboard-wrapper {
                display: flex;
                flex-direction: column;
                width: 100%;
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }

            main.content {
                margin-left: 0 !important;
                width: 100%;
                height: auto;
                min-height: 100vh;
                overflow: visible;
                padding: 15px;
                box-sizing: border-box;
            }

            .admin-layout {
                grid-template-columns: 1fr;
                width: 100%;
            }

            .table-container {
                width: 100%;
                max-height: none;
                height: auto;
                overflow-x: auto;
                overflow-y: visible;
                box-sizing: border-box;
            }

            #sidebar.active .sidebar-close {
                display: block;
            }
        }

        #sidebar:not(.active) ~ main.content { 
            margin-left: 0; 
            width: 100%; 
        }
        .admin-layout { 
            display: grid; 
            grid-template-columns: 350px 1fr; 
            gap: 25px; margin-top: 20px; 
            align-items: start; 
        }
        @media (max-width: 1100px) { 
            .admin-layout { grid-template-columns: 1fr; } 
        }
        .content-header h1 { 
            margin: 0; 
            font-size: 1.70rem; 
            color: #111827; 
        }

        .card { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            border: 1px solid #e5e7eb; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
        }
        .form-group { 
            margin-bottom: 15px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 0.85rem; 
            color: #475569; 
        }
        .form-group select, .form-group input { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            font-family: inherit; 
        }
        
        .btn-primary { 
            background: #2563eb; 
            color: white; 
            border: none; 
            padding: 12px; 
            width: 100%; 
            border-radius: 8px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: 0.2s; 
        }
        .btn-primary:hover { 
            background: #1d4ed8; 
        }
        
        .status-msg { 
            padding: 12px; 
            border-radius: 8px; 
            margin-bottom: 15px; 
            font-size: 0.85rem; 
            font-weight: 600; 
        }
        .msg-success { 
            background: #f0fdf4; 
            color: #16a34a; 
            border: 1px solid #bbf7d0; 
        }
        .msg-conflict { 
            background: #fef2f2; 
            color: #dc2626; 
            border: 1px solid #fecaca; 
        }
        
        .delete-btn { 
            color: #ef4444; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 0.8rem; 
        }
        .table-header-flex { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
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
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_strands.php">Manage Strands</a></li>
                <li><a href="curriculum_guide.php">Manage Subjects</a></li>
                <li><a href="manage_sections.php">Manage Sections</a></li>
                <li><a href="manage_assignments.php">Teaching Assignments</a></li>
                <li><a href="admin_schedule.php" class="active">Manage Schedules</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1>Schedule Management</h1>
                <p>Assign subjects to sections and rooms while avoiding conflicts.</p>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#eff6ff; color:#3b82f6;">📅</div>
                    <div class="stat-info"><h3>Total Classes</h3><div class="number"><?= htmlspecialchars((string) $total_classes); ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fef2f2; color:#ef4444;">🏫</div>
                    <div class="stat-info"><h3>Active Rooms</h3><div class="number"><?= htmlspecialchars((string) $total_rooms); ?></div></div>
                </div>
            </div>

            <div class="admin-layout">
                <div class="card">
                    <h3>Add New Entry</h3>
                    <?php if($message == "success"): ?><div class="status-msg msg-success">✔ Schedule saved successfully!</div><?php endif; ?>
                    <?php if($message == "section_conflict"): ?><div class="status-msg msg-conflict">⚠ Section is already busy during this time!</div><?php endif; ?>
                    <?php if($message == "room_conflict"): ?><div class="status-msg msg-conflict">⚠ Room is already occupied by another class!</div><?php endif; ?>
                    <?php if($message == "faculty_conflict"): ?><div class="status-msg msg-conflict">⚠ The assigned teacher already has a class during this time!</div><?php endif; ?>
                    <?php if($message == "invalid_subject"): ?><div class="status-msg msg-conflict">⚠ Assign this subject to the selected section for its academic year before scheduling it.</div><?php endif; ?>
                    <?php if($message == "invalid_time"): ?><div class="status-msg msg-conflict">⚠ End time must be later than start time.</div><?php endif; ?>
                    <?php if($message == "invalid_day"): ?><div class="status-msg msg-conflict">⚠ Select a valid day for the schedule.</div><?php endif; ?>
                    <?php if($message == "invalid_room"): ?><div class="status-msg msg-conflict">⚠ Room number must be 50 characters or fewer.</div><?php endif; ?>
                    <?php if($message == "missing_fields"): ?><div class="status-msg msg-conflict">⚠ Complete all required schedule fields.</div><?php endif; ?>
                    <?php if(($_GET['msg'] ?? '') === 'archived'): ?><div class="status-msg msg-success">✔ Schedule archived and sent to the super admin for review.</div><?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Section</label>
                            <select name="section_id" id="sectionSelect" onchange="fetchSubjects(this.value)" required>
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subject</label>
                            <select name="subject_id" id="subjectSelect" required disabled>
                                <option value="">Select a section first</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Day of Week</label>
                            <select name="day_of_week" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <div class="form-group" style="flex:1;"><label>Start</label><input type="time" name="start_time" required></div>
                            <div class="form-group" style="flex:1;"><label>End</label><input type="time" name="end_time" required></div>
                        </div>
                        <div class="form-group"><label>Room Number</label><input type="text" name="room_number" placeholder="e.g., Room 301" required></div>
                        <button type="submit" name="add_schedule" class="btn-primary">Save Schedule</button>
                    </form>
                </div>

                <div class="table-container">
                    <div class="table-header-flex">
                        <h2>Timetable Overview</h2>
                        <form method="GET">
                            <select name="filter_section" onchange="this.form.submit()" style="padding:8px; border-radius:6px; border:1px solid #ddd;">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?= $s['id']; ?>" <?= ($filter_section == (int) $s['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($s['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <table>
                        <thead>
                            <tr><th>Section</th><th>Day</th><th>Subject</th><th>Time</th><th>Room</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($schedules)): ?>
                                <?php foreach ($schedules as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['section_name']); ?></strong></td>
                                    <td><?= htmlspecialchars($row['day_of_week']); ?></td>
                                    <td><?= htmlspecialchars($row['subject_name']); ?></td>
                                    <td><?= date("g:i A", strtotime($row['start_time'])); ?> - <?= date("g:i A", strtotime($row['end_time'])); ?></td>
                                    <td><span style="background:#f1f5f9; padding:4px 8px; border-radius:4px;"><?= htmlspecialchars($row['room_number']); ?></span></td>
                                    <td>
                                        <form method="POST" action="archive_resource.php" onsubmit="return confirm('Archive this schedule? The super admin can restore or permanently delete it.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="resource_type" value="schedule">
                                            <input type="hidden" name="resource_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="filter_section" value="<?= (int) $filter_section ?>">
                                            <button type="submit" class="delete-btn">Archive</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;">No schedules found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }

        function fetchSubjects(sectionId) {
            const subjectSelect = document.getElementById('subjectSelect');
            if (!sectionId) {
                subjectSelect.innerHTML = '<option value="">Select a section first</option>';
                subjectSelect.disabled = true;
                return;
            }
            subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';
            subjectSelect.disabled = true;
            fetch(`get_filtered_subjects.php?section_id=${sectionId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Could not load the assigned subjects.');
                    }
                    return response.json();
                })
                .then(data => {
                    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                    if (data.length === 0) {
                        subjectSelect.innerHTML = '<option value="">No assigned subjects for this section/year</option>';
                        subjectSelect.disabled = true;
                    } else {
                        data.forEach(sub => {
                            let option = document.createElement('option');
                            option.value = sub.id;
                            option.textContent = `${sub.subject_name} (${sub.subject_code})`;
                            subjectSelect.appendChild(option);
                        });
                        subjectSelect.disabled = false;
                    }
                })
                .catch(error => {
                    subjectSelect.innerHTML = '<option value="">Unable to load subjects</option>';
                    subjectSelect.disabled = true;
                    console.error(error);
                });
        }
    </script>
</body>
</html>