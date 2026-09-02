<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$session_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';
if ($session_role !== 'student') {
    header("Location: login.php?error=unauthorized_role");
    exit();
}

$user_id = $_SESSION['user_id'];

$info_query = "SELECT u.full_name, s.section_name 
               FROM users u 
               LEFT JOIN sections s ON u.section_id = s.id 
               WHERE u.id = ?";
$stmt = $conn->prepare($info_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();


$grades_query = "
    SELECT 
        s.subject_name,
        s.subject_code,
        s.grade_level,
        s.semester,
        g.quarter1_grade, 
        g.quarter2_grade, 
        g.final_grade, 
        g.remarks
    FROM grades g
    INNER JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ?
    ORDER BY 
        CASE 
            WHEN s.grade_level LIKE '%11%' THEN 1 
            WHEN s.grade_level LIKE '%12%' THEN 2 
            ELSE 3 
        END ASC,
        CASE 
            WHEN s.semester LIKE '%1st%' THEN 1 
            WHEN s.semester LIKE '%2nd%' THEN 2 
            ELSE 3 
        END ASC,
        s.subject_name ASC
";

$stmt_g = $conn->prepare($grades_query);
$stmt_g->bind_param("i", $user_id);
$stmt_g->execute();
$raw_grades = $stmt_g->get_result();

$grades_matrix = [];
while ($row = $raw_grades->fetch_assoc()) {
    $gl = $row['grade_level'];
    $sem = $row['semester'];
    $grades_matrix[$gl][$sem][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades | STRAND-SYNC</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .dashboard-wrapper {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        main.content {
            flex-grow: 1;
            margin-left: 260px;
            padding: 30px;
            width: 100%;
            max-width: 100%;
            height: 100vh;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        /* Level Grouping Containers */
        .grade-level-section {
            background: white;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .level-header {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 20px 0;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .semester-block {
            margin-top: 25px;
        }

        .semester-title {
            background: #f8fafc;
            padding: 10px 16px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #475569;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #2563eb;
            width: fit-content;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .grade-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            table-layout: fixed;
        }

        .col-subject {
            width: 40%;
        }

        .col-qtr {
            width: 12%;
        }

        .col-final {
            width: 12%;
        }

        .col-remarks {
            width: 24%;
        }

        .grade-table th {
            background: #1e293b;
            color: #f8fafc;
            padding: 14px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: center;
        }

        .grade-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #334155;
            text-align: center;
        }

        .text-left {
            text-align: left !important;
        }

        .pl-25 {
            padding-left: 25px !important;
        }

        .grade-table tr:hover {
            background-color: #f8fafc;
        }

        .subject-title {
            display: block;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.95rem;
        }

        .subject-sub {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .final-cell {
            background-color: #f8fafc;
            font-weight: 800;
            color: #0f172a;
        }

        .status-badge {
            font-weight: 700;
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        .status-passed {
            color: #059669;
            background: #ecfdf5;
        }

        .status-failed {
            color: #dc2626;
            background: #fef2f2;
        }

        .status-pending {
            color: #64748b;
            background: #f1f5f9;
            font-weight: 500;
        }

        .btn-download {
            background: #2563eb;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .mobile-toggle.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .sidebar-close {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            body {
                overflow: auto;
            }

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
            }

            main.content {
                margin-left: 0 !important;
                width: 100%;
                height: auto;
                padding: 15px;
            }

            .grade-table {
                table-layout: auto;
            }

            #sidebar.active .sidebar-close {
                display: block;
            }
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
                <li><a href="student_dashboard.php">My Dashboard</a></li>
                <li><a href="student_announcements.php">Announcements</a></li>
                <li><a href="student_grades.php" class="active">My Grades</a></li>
                <li><a href="student_profile.php">Account Settings</a></li>
                <li><a href="logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px;">
                <div class="header-left-group" style="display: flex; align-items: center; gap: 12px;">
                    <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>
                    <div>
                        <h1 style="margin: 0 0 5px 0; font-size: 1.75rem; color: #0f172a; font-weight: 700;">Academic Performance History</h1>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                            Current Section: <span style="color: #2563eb; font-weight: 600;"><?php echo htmlspecialchars($student['section_name'] ?? 'Unassigned'); ?></span>
                        </p>
                    </div>
                </div>
                <a href="print_report_card.php?student_id=<?php echo $user_id; ?>" target="_blank" class="btn-download">
                    <span>📥</span> Print SF9 Copy
                </a>
            </header>

            <?php if (!empty($grades_matrix)): ?>
                <?php foreach ($grades_matrix as $grade_level => $semesters): ?>
                    <div class="grade-level-section">
                        <div class="level-header">
                            <span>🎓</span> Academic Records: Grade <?php echo htmlspecialchars($grade_level); ?>
                        </div>

                        <?php foreach ($semesters as $semester => $subjects): ?>
                            <div class="semester-block">
                                <div class="semester-title"><?php echo htmlspecialchars($semester); ?> Semester</div>
                                <div class="table-container">
                                    <table class="grade-table">
                                        <thead>
                                            <tr>
                                                <th class="col-subject text-left pl-25">Subject Details</th>
                                                <th class="col-qtr">1st Qtr</th>
                                                <th class="col-qtr">2nd Qtr</th>
                                                <th class="col-final">Final</th>
                                                <th class="col-remarks">Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subjects as $row): ?>
                                                <tr>
                                                    <td class="text-left pl-25">
                                                        <span class="subject-title"><?php echo htmlspecialchars($row['subject_name']); ?></span>
                                                        <span class="subject-sub"><?php echo htmlspecialchars($row['subject_code']); ?></span>
                                                    </td>
                                                    <td><?php echo ($row['quarter1_grade'] > 0) ? number_format($row['quarter1_grade'], 1) : '-'; ?></td>
                                                    <td><?php echo ($row['quarter2_grade'] > 0) ? number_format($row['quarter2_grade'], 1) : '-'; ?></td>
                                                    <td class="final-cell"><?php echo ($row['final_grade'] > 0) ? number_format($row['final_grade'], 1) : '-'; ?></td>
                                                    <td>
                                                        <?php
                                                        $final = $row['final_grade'] ?? 0;
                                                        $remarks = $row['remarks'];

                                                        if (empty($remarks)) {
                                                            if ($final >= 75.0) $remarks = 'Passed';
                                                            elseif ($final > 0 && $final < 75.0) $remarks = 'Failed';
                                                            else $remarks = 'Pending';
                                                        }

                                                        $status_class = 'status-pending';
                                                        if ($remarks == 'Passed') $status_class = 'status-passed';
                                                        if ($remarks == 'Failed') $status_class = 'status-failed';
                                                        ?>
                                                        <span class="status-badge <?php echo $status_class; ?>">
                                                            <?php echo $remarks; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="grade-level-section" style="text-align: center; padding: 50px; color: #94a3b8;">
                    No verified academic records found for this user account.
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const burger = document.getElementById('mobileBurger');
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                if (burger) burger.classList.add('hidden');
            } else {
                if (burger) burger.classList.remove('hidden');
            }
        }
    </script>
</body>

</html>