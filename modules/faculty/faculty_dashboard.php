<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

$pdo = getDBConnection();

// Authorization Guard
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['adviser', 'faculty', 'admin'])) {
    header("Location: ../../manifest/login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$success_msg = "";

// 1. Process Announcement Broadcast
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $title      = trim($_POST['title'] ?? '');
    $message    = trim($_POST['message'] ?? '');
    $target_raw = $_POST['target_id'] ?? '';

    $parts = explode('_', $target_raw);
    $type  = $parts[0] ?? '';
    $t_id  = $parts[1] ?? null;

    $sec_id_val = ($type === 'section') ? $t_id : null;
    $sub_id_val = ($type === 'subject') ? $t_id : null;

    if (!empty($title) && !empty($message) && !empty($type)) {
        $ins = $pdo->prepare("
            INSERT INTO announcements (sender_id, title, message, target_type, section_id, subject_id, created_at) 
            VALUES (:sender_id, :title, :message, :target_type, :section_id, :subject_id, NOW())
        ");
        
        $posted = $ins->execute([
            'sender_id'   => $user_id,
            'title'       => $title,
            'message'     => $message,
            'target_type' => $type,
            'section_id'  => $sec_id_val,
            'subject_id'  => $sub_id_val
        ]);

        if ($posted) {
            $success_msg = "Announcement broadcasted successfully to students!";
        }
    }
}

// 2. Fetch Advisory Section (if role is Adviser)
$section_data = null;
if ($user_role === 'adviser') {
    $section_query = $pdo->prepare("
        SELECT s.id, s.section_name, s.grade_level, st.strand_name 
        FROM sections s
        JOIN strands st ON s.strand_id = st.id
        WHERE s.adviser_id = :adviser_id
        LIMIT 1
    ");
    $section_query->execute(['adviser_id' => $user_id]);
    $section_data = $section_query->fetch(PDO::FETCH_ASSOC);
}

// 3. Fetch Teacher's Assigned Teaching Load
$load_query = $pdo->prepare("
    SELECT 
        sa.id AS assignment_id,
        sa.subject_id,
        sa.section_id,
        s.subject_name, 
        s.subject_code, 
        s.units, 
        s.grade_level AS sub_grade_level, 
        sec.section_name, 
        sec.grade_level AS sec_grade_level
    FROM subject_assignments sa
    JOIN subjects s ON sa.subject_id = s.id
    JOIN sections sec ON sa.section_id = sec.id
    WHERE sa.faculty_id = :faculty_id
    ORDER BY 
        CASE 
            WHEN s.grade_level LIKE '%11%' THEN 1 
            WHEN s.grade_level LIKE '%12%' THEN 2 
            ELSE 3 
        END ASC,
        sec.section_name ASC,
        s.subject_name ASC
");
$load_query->execute(['faculty_id' => $user_id]);
$teaching_load_result = $load_query->fetchAll(PDO::FETCH_ASSOC);

// Categorize Teaching Load by Grade Level
$categorized_load = ['Grade 11' => [], 'Grade 12' => [], 'Other' => []];
foreach ($teaching_load_result as $load) {
    if (strpos($load['sub_grade_level'], '11') !== false) {
        $categorized_load['Grade 11'][] = $load;
    } elseif (strpos($load['sub_grade_level'], '12') !== false) {
        $categorized_load['Grade 12'][] = $load;
    } else {
        $categorized_load['Other'][] = $load;
    }
}

// 4. Fetch Enrolled Student Count for Advisory Section
$student_count = 0;
if ($section_data) {
    $sec_id = $section_data['id'];
    $count_query = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE section_id = :sec_id AND role = 'student'");
    $count_query->execute(['sec_id' => $sec_id]);
    $student_count = $count_query->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* Layered Dashboard Page Specific Overrides */
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

        .btn-table-action {
            display: inline-block;
            background-color: #4f46e5;
            color: #ffffff;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .btn-table-action:hover {
            background-color: #4338ca;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 600;
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
                <li><a href="faculty_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="faculty_schedule.php">Manage Schedule</a></li>
                <li><a href="ecr-inputdata.php">ECR Setup & Roster</a></li>
                <li><a href="ecr-view.php?term=1">Encode Term Grades</a></li>
                <li><a href="ecr-summary.php">ECR Summary</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header" style="margin-bottom: 25px;">
                <h1>Teacher Overview</h1>
                <p>Logged in as: <strong><?php echo ucfirst(htmlspecialchars($user_role)); ?></strong></p>
            </header>

            <?php if ($success_msg): ?>
                <div class="alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>

            <!-- Announcement Section -->
            <section class="announcement-section" style="margin-bottom: 30px;">
                <h2>Broadcast Announcement</h2>
                <div class="announcement-form" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group">
                                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 5px;">Subject / Title</label>
                                <input type="text" name="title" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="e.g., Exam Coverage or Meeting Schedule" required>
                            </div>
                            <div class="form-group">
                                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 5px;">Target Recipients</label>
                                <select name="target_id" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                                    <option value="">Select Audience...</option>
                                    <?php if ($section_data): ?>
                                        <optgroup label="Advisory Section">
                                            <option value="section_<?php echo $section_data['id']; ?>">
                                                Section: <?php echo htmlspecialchars($section_data['section_name']); ?> (All Students)
                                            </option>
                                        </optgroup>
                                    <?php endif; ?>

                                    <optgroup label="My Teaching Classes">
                                        <?php foreach ($categorized_load as $g_title => $items): ?>
                                            <?php foreach ($items as $item): ?>
                                                <option value="subject_<?php echo $item['subject_id']; ?>">
                                                    [<?php echo htmlspecialchars($item['sec_grade_level']); ?>] <?php echo htmlspecialchars($item['subject_name']); ?> - <?php echo htmlspecialchars($item['section_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 5px;">Message Details</label>
                            <textarea name="message" class="form-control" rows="3" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="Type your announcement message here..." required></textarea>
                        </div>

                        <button type="submit" name="post_announcement" style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;">Broadcast to Students</button>
                    </form>
                </div>
            </section>

            <!-- Advisory Stat Cards -->
            <?php if ($section_data): ?>
                <section style="margin-bottom: 30px;">
                    <h2>Advisory Section Overview</h2>
                    <div class="stats-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <h3 style="margin: 0; font-size: 1.2rem;"><?php echo htmlspecialchars($section_data['section_name']); ?></h3>
                            <p style="color: #64748b; font-size: 0.9rem; margin-top: 5px;">Grade <?php echo htmlspecialchars($section_data['grade_level']); ?> - <?php echo htmlspecialchars($section_data['strand_name']); ?></p>
                        </div>
                        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <h3 style="margin: 0; font-size: 1.2rem;"><?php echo (int)$student_count; ?> Students</h3>
                            <p style="color: #64748b; font-size: 0.9rem; margin-top: 5px;">Total Enrolled Learners</p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Teaching Load Tables -->
            <section style="margin-bottom: 30px;">
                <h2>My Teaching Load</h2>
                <div class="category-container">
                    <?php
                    $has_active_load = false;
                    foreach ($categorized_load as $grade_title => $loads):
                        if (empty($loads)) continue;
                        $has_active_load = true;
                    ?>
                        <div class="category-heading" style="font-weight: 700; color: #1e293b; margin: 15px 0 10px 0;">
                            <span>📅</span> <?php echo htmlspecialchars($grade_title); ?> Mapped Assignments
                        </div>

                        <div class="table-container" style="background: white; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                            <table class="load-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                                        <th style="width: 15%; padding: 10px;">Subject Code</th>
                                        <th style="width: 45%; padding: 10px;">Subject Name</th>
                                        <th style="width: 15%; padding: 10px; text-align: center;">Section</th>
                                        <th style="width: 15%; padding: 10px; text-align: center;">Units</th>
                                        <th style="width: 10%; padding: 10px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loads as $load): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 10px; font-family: monospace; font-weight: 700; color: #4f46e5;"><?php echo htmlspecialchars($load['subject_code']); ?></td>
                                            <td style="padding: 10px; font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($load['subject_name']); ?></td>
                                            <td style="padding: 10px; text-align: center; font-weight: 700; color: #334155;"><?php echo htmlspecialchars($load['section_name']); ?></td>
                                            <td style="padding: 10px; text-align: center;">
                                                <span style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">
                                                    <?php echo htmlspecialchars($load['units'] ?? '0'); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 10px; text-align: center;">
                                                <!-- Fixed routing link to pass assignment_id -->
                                                <a href="ecr-inputdata.php?assignment_id=<?php echo $load['assignment_id']; ?>&term=Term+1" class="btn-table-action">Enter Grades</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$has_active_load): ?>
                        <div class="table-container" style="background: white; text-align: center; padding: 40px; color: #94a3b8; font-weight: 500; border-radius: 12px; border: 1px solid #e2e8f0;">
                            You have not been assigned any subject classes to teach yet.
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="../../assets/js/app.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const burger  = document.getElementById('mobileBurger');

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