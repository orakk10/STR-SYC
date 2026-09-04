<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

$message = "";

// 2. Handle Form Submission with Room & Section Conflict Check
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_schedule'])) {
    $section_id = $_POST['section_id'] ?? null;
    $subject_id = $_POST['subject_id'] ?? null;
    $day        = $_POST['day_of_week'] ?? null;
    $start      = $_POST['start_time'] ?? null;
    $end        = $_POST['end_time'] ?? null;
    $room       = isset($_POST['room_number']) ? trim($_POST['room_number']) : null;

    if (!$section_id || !$subject_id || !$day || !$start || !$end || !$room) {
        $message = "missing_fields";
    } else {
        $check_query = "SELECT section_id, room_number FROM schedules 
                        WHERE day_of_week = ? 
                        AND (section_id = ? OR room_number = ?)
                        AND (start_time < ? AND end_time > ?)";
        
        $check_stmt = $conn->prepare($check_query);
        // $check_stmt->bind_param("sisss", $day, $section_id, $room, $end, $start);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $conflict = $result->fetch_assoc();
            $message = ($conflict['room_number'] === $room && $conflict['section_id'] != $section_id) ? "room_conflict" : "section_conflict";
        } else {
            $insert_query = "INSERT INTO schedules (section_id, subject_id, day_of_week, start_time, end_time, room_number) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            // $stmt->bind_param("iissss", $section_id, $subject_id, $day, $start, $end, $room);
            if ($stmt->execute()) { $message = "success"; } else { $message = "error"; }
        }
    }
}

// 3. Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM schedules WHERE id = $id");
    header("Location: admin_schedule.php?msg=deleted");
    exit();
}

// 4. Data for Stats & Select Inputs
$total_classes = $conn->query("SELECT COUNT(*) FROM schedules")->fetch_row()[0];
$total_rooms   = $conn->query("SELECT COUNT(DISTINCT room_number) FROM schedules")->fetch_row()[0];
$sections = $conn->query("SELECT id, section_name FROM sections ORDER BY section_name");

// 5. Fetch Table Data with Filters
$filter_section = $_GET['filter_section'] ?? null;
$sched_query = "SELECT sch.*, s.section_name, sub.subject_name, sub.subject_code 
                FROM schedules sch 
                JOIN sections s ON sch.section_id = s.id 
                JOIN subjects sub ON sch.subject_id = sub.id";
if ($filter_section) $sched_query .= " WHERE sch.section_id = " . intval($filter_section);
$sched_query .= " ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), start_time ASC";
$schedules = $conn->query($sched_query);
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
        .admin-layout { display: grid; grid-template-columns: 350px 1fr; gap: 25px; margin-top: 20px; align-items: start; }
        @media (max-width: 1100px) { .admin-layout { grid-template-columns: 1fr; } }

        .card { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.85rem; color: #475569; }
        .form-group select, .form-group input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
        
        .btn-primary { background: #2563eb; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #1d4ed8; }
        
        .status-msg { padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 600; }
        .msg-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .msg-conflict { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        
        .delete-btn { color: #ef4444; text-decoration: none; font-weight: 700; font-size: 0.8rem; }
        .table-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
    <div class="dashboard-wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header"><h3>STRAND-SYNC</h3></div>
            <ul class="menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_strands.php">Manage Strands</a></li>
                <li><a href="curriculum_guide.php">Manage Subjects</a></li>
                <li><a href="manage_sections.php">Manage Sections</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_schedule.php" class="active">Manage Schedule</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
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
                    <div class="stat-info"><h3>Total Classes</h3><div class="number"><?= $total_classes; ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fef2f2; color:#ef4444;">🏫</div>
                    <div class="stat-info"><h3>Active Rooms</h3><div class="number"><?= $total_rooms; ?></div></div>
                </div>
            </div>

            <div class="admin-layout">
                <div class="card">
                    <h3>Add New Entry</h3>
                    <?php if($message == "success"): ?><div class="status-msg msg-success">✔ Schedule saved successfully!</div><?php endif; ?>
                    <?php if($message == "section_conflict"): ?><div class="status-msg msg-conflict">⚠ Section is already busy during this time!</div><?php endif; ?>
                    <?php if($message == "room_conflict"): ?><div class="status-msg msg-conflict">⚠ Room is already occupied by another class!</div><?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Section</label>
                            <select name="section_id" id="sectionSelect" onchange="fetchSubjects(this.value)" required>
                                <option value="">Select Section</option>
                                <?php /* while($s = $sections->fetch_assoc()): ?>
                                    <option value="<?= $s['id']; ?>"><?= $s['section_name']; ?></option>
                                <?php endwhile; */ ?>
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
                                <?php /* $sections->data_seek(0); while($s = $sections->fetch_assoc()): ?>
                                    <option value="<?= $s['id']; ?>" <?= ($filter_section == $s['id']) ? 'selected' : ''; ?>><?= $s['section_name']; ?></option>
                                <?php endwhile; */ ?>
                            </select>
                        </form>
                    </div>
                    <table>
                        <thead>
                            <tr><th>Section</th><th>Day</th><th>Subject</th><th>Time</th><th>Room</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php /* if ($schedules->num_rows > 0): ?>
                                <?php while($row = $schedules->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= $row['section_name']; ?></strong></td>
                                    <td><?= $row['day_of_week']; ?></td>
                                    <td><?= $row['subject_name']; ?></td>
                                    <td><?= date("g:i A", strtotime($row['start_time'])); ?> - <?= date("g:i A", strtotime($row['end_time'])); ?></td>
                                    <td><span style="background:#f1f5f9; padding:4px 8px; border-radius:4px;"><?= $row['room_number']; ?></span></td>
                                    <td><a href="?delete=<?= $row['id']; ?>" class="delete-btn" onclick="return confirm('Remove this schedule?')">Delete</a></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;">No schedules found.</td></tr>
                            <?php endif; */ ?>
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
            fetch(`get_filtered_subjects.php?section_id=${sectionId}`)
                .then(response => response.json())
                .then(data => {
                    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                    if (data.length === 0) {
                        subjectSelect.innerHTML = '<option value="">No subjects found</option>';
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
                });
        }
    </script>
</body>
</html>