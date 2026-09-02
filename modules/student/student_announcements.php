<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT section_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sec_result = $stmt->get_result()->fetch_assoc();
$sec_id = $sec_result['section_id'] ?? 0;

$ann_query = "
    SELECT a.*, u.full_name as teacher_name 
    FROM announcements a
    JOIN users u ON a.sender_id = u.id
    WHERE (
        (a.target_type = 'section' AND a.section_id = ?)
        OR (a.target_type = 'subject' AND a.subject_id IN (
            SELECT subject_id FROM subject_assignments WHERE section_id = ?
        ))
    )
    AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY a.created_at DESC";

$stmt_all = $conn->prepare($ann_query);
$stmt_all->bind_param("ii", $sec_id, $sec_id);
$stmt_all->execute();
$all_ann = $stmt_all->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements | STRAND-SYNC</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body { 
            margin: 0;
            padding: 0;
            overflow: hidden; 
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

        .ann-card { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            border-left: 5px solid #6366f1; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
            transition: transform 0.2s ease;
        }
        .ann-card:hover { transform: translateX(5px); }
        
        .ann-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px; 
        }

        /* Target Tags */
        .tag { font-size: 0.7rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .tag-section { background: #e0e7ff; color: #4338ca; }
        .tag-subject { background: #fef3c7; color: #92400e; }

        .ann-body { color: #475569; line-height: 1.6; font-size: 0.95rem; }
        .ann-footer { 
            margin-top: 20px; 
            padding-top: 12px; 
            border-top: 1px solid #f1f5f9; 
            font-size: 0.85rem; 
            color: #64748b; 
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

        @media (max-width: 1024px) {
            body { overflow: auto; }
            
            .sidebar { position: fixed; left: -260px; transition: 0.3s; z-index: 1050; }
            .sidebar.active { left: 0; }
            
            .mobile-toggle { 
                display: block; 
            }

            .header-left-group {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            main.content { 
                margin-left: 0 !important; 
                width: 100%; 
                height: auto; 
                overflow-y: visible;
                padding: 15px; 
                padding-top: 20px;
            }

            .ann-header h2 { font-size: 1.1rem; }
            .ann-card { padding: 15px; }

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

    <div class="dashboard-wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="student_dashboard.php">My Dashboard</a></li>
                <li><a href="student_announcements.php" class="active">Announcements</a></li>
                <li><a href="student_grades.php">My Grades</a></li>
                <li><a href="student_profile.php">Account Settings</a></li>
                <li><a href="logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header" style="margin-top: 10px;">
                <div class="header-left-group">
                    <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>
                    <h1 style="margin: 0;">Announcement Inbox</h1>
                </div>
                <p style="margin: 5px 0 0;">Viewing messages from the last 30 days.</p>
            </header>

            <div class="inbox-container" style="margin-top: 25px;">
                <?php if ($all_ann->num_rows > 0): ?>
                    <?php while($ann = $all_ann->fetch_assoc()): ?>
                        <article class="ann-card">
                            <div class="ann-header">
                                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                                    <span class="tag <?php echo ($ann['target_type'] == 'section') ? 'tag-section' : 'tag-subject'; ?>">
                                        <?php echo $ann['target_type']; ?>
                                    </span>
                                    <h2 style="margin: 0; color: #1e293b; font-size: 1.25rem;"><?php echo htmlspecialchars($ann['title']); ?></h2>
                                </div>
                                <time style="font-size: 0.8rem; color: #94a3b8; font-weight: 500; white-space: nowrap;">
                                    <?php echo date('M d, Y', strtotime($ann['created_at'])); ?>
                                </time>
                            </div>

                            <div class="ann-body">
                                <?php echo nl2br(htmlspecialchars($ann['message'])); ?>
                            </div>

                            <div class="ann-footer">
                                Posted by: <strong style="color: #334155;"><?php echo htmlspecialchars($ann['teacher_name']); ?></strong>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 80px 20px; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px;">
                        <span style="font-size: 2.5rem; display: block; margin-bottom: 10px;">📩</span>
                        <p style="color: #94a3b8; font-weight: 500; margin: 0;">No announcements found in the last 30 days.</p>
                    </div>
                <?php endif; ?>
            </div>
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