<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

// Fetch logs with user names
$query = "SELECT l.*, u.full_name, u.role 
          FROM activity_logs l 
          JOIN users u ON l.user_id = u.id 
          ORDER BY l.created_at DESC LIMIT 100";
$logs = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        /* Base Viewport Layout Setup */
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

        /* Fluid Content Area Configuration */
        main.content { 
            flex-grow: 1;
            margin-left: 260px; /* Desktop Sidebar Width */
            padding: 30px;
            width: 100%;
            max-width: 100%;
            height: 100vh; 
            box-sizing: border-box;
            display: flex; 
            flex-direction: column; 
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        /* Scrollable Data Viewport Container */
        .table-container { 
            flex: 1; 
            min-height: 0; 
            overflow-y: auto; 
            overflow-x: auto; 
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        /* --- BURGER TOGGLE UTILITIES --- */
        .mobile-toggle {
            display: none;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 999;
        }

        /* Hide burger when sidebar menu slides open */
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

        /* Viewport Breakpoints Override Rules */
        @media (max-width: 1024px) {
            body { overflow: auto; }
            
            .sidebar { position: fixed; left: -260px; transition: 0.3s; z-index: 1050; }
            .sidebar.active { left: 0; }
            
            .mobile-toggle { 
                display: block; 
                position: fixed; 
                top: 10px; 
                left: 10px; 
                z-index: 1100; 
                background: #2563eb; 
                color: white; 
                border: none; 
                padding: 8px; 
                border-radius: 4px; 
            }

            main.content { 
                margin-left: 0 !important; 
                width: 100%; 
                height: auto; 
                overflow: visible;
                padding: 15px; 
                padding-top: 60px;
            }

            .table-container { 
                overflow-y: visible; 
                max-height: none; 
                padding: 15px;
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
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_strands.php">Manage Strands</a></li>
                <li><a href="curriculum_guide.php">Curriculum Guide</a></li>
                <li><a href="manage_sections.php">Manage Sections</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php" class="active">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1>System Activity Logs</h1>
                <p>Tracking all major changes across the platform</p>
            </header>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Specific Action</th>
                            <th>Table</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php /* while($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                            <td><span class="role-badge <?php echo strtolower($row['role']); ?>"><?php echo ucfirst($row['role']); ?></span></td>
                            <td style="max-width: 420px;">⌁ <?php echo htmlspecialchars($row['action']); ?></td>
                            <td><code><?php echo htmlspecialchars($row['affected_table']); ?></code></td>
                        </tr>
                        <?php endwhile; */ ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

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