<?php
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];


$stmt = $conn->prepare("SELECT section_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$section_id = $stmt->get_result()->fetch_assoc()['section_id'] ?? null;

$schedule_data = [];
if ($section_id) {
    $sched_query = "
        SELECT 
            sch.day_of_week, 
            sch.start_time, 
            sch.end_time, 
            sch.room_number,
            sub.subject_name, 
            sub.subject_code,
            u.full_name AS teacher_name
        FROM schedules sch
        JOIN subjects sub ON sch.subject_id = sub.id
        LEFT JOIN subject_assignments sa ON (sa.subject_id = sub.id AND sa.section_id = ?)
        LEFT JOIN users u ON sa.faculty_id = u.id
        WHERE sch.section_id = ?
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time ASC
    ";
    
    $stmt_s = $conn->prepare($sched_query);
    $stmt_s->bind_param("ii", $section_id, $section_id);
    $stmt_s->execute();
    $result = $stmt_s->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $schedule_data[$row['day_of_week']][] = $row;
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule | STRAND-SYNC</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .schedule-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .day-column { background: #f8fafc; border-radius: 12px; padding: 15px; border: 1px solid #e2e8f0; }
        .day-header { border-bottom: 2px solid #3b82f6; padding-bottom: 10px; margin-bottom: 15px; color: #1e293b; font-weight: 700; }
        
        .class-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #3b82f6;
        }
        .time-slot { font-size: 0.8rem; color: #3b82f6; font-weight: 600; display: block; margin-bottom: 5px; }
        .subject-info { font-size: 0.95rem; font-weight: 700; color: #0f172a; display: block; }
        .meta-info { font-size: 0.8rem; color: #64748b; margin-top: 5px; display: flex; justify-content: space-between; }
        
        .empty-day { text-align: center; color: #94a3b8; padding: 20px; font-size: 0.9rem; font-style: italic; }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>

    <div class="dashboard-wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header"><h3>STRAND-SYNC</h3></div>
            <ul class="menu">
                <li><a href="student_dashboard.php" >My Dashboard</a></li>
                <li><a href="student_announcements.php">Announcements</a></li>
                <li><a href="student_grades.php">My Grades</a></li>
                <li><a href="student_schedule.php" class="active">Class Schedule</a></li>
                <li><a href="student_profile.php">Account Settings</a></li>
                <li><a href="logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1>Class Schedule</h1>
                <p>Weekly timetable for your section.</p>
            </header>

            <div class="schedule-grid">
                <?php foreach ($days as $day): ?>
                    <div class="day-column">
                        <div class="day-header"><?php echo $day; ?></div>
                        
                        <?php if (isset($schedule_data[$day])): ?>
                            <?php foreach ($schedule_data[$day] as $class): ?>
                                <div class="class-card">
                                    <span class="time-slot">
                                        <?php echo date("g:i A", strtotime($class['start_time'])); ?> - 
                                        <?php echo date("g:i A", strtotime($class['end_time'])); ?>
                                    </span>
                                    <span class="subject-info"><?php echo htmlspecialchars($class['subject_name']); ?></span>
                                    <div class="meta-info">
                                        <span>Rm: <?php echo htmlspecialchars($class['room_number'] ?: 'TBA'); ?></span>
                                        <span><?php echo htmlspecialchars($class['teacher_name'] ?: 'TBA'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-day">No classes scheduled</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
    </script>
</body>
</html>