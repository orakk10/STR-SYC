<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

// --- FETCH RESET REQUESTS WITH ERROR CHECKING ---
$reset_count = (int) $conn->query("SELECT COUNT(*) FROM users WHERE reset_requested = 1")->fetchColumn();
// ------------------------------------------------

// 2. Pagination Logic
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

$total_results = (int) $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_pages = ceil($total_results / $limit);

// 3. Fetch Data for Stat Cards
$total_students = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_faculty  = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'faculty'")->fetchColumn();
$total_advisers = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'adviser'")->fetchColumn();
$total_sections = (int) $conn->query("SELECT COUNT(*) FROM sections")->fetchColumn();

// 4. Fetch Paginated User List
$users_list = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id DESC LIMIT $start, $limit");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
            overflow: hidden;
        }

        .table-container { 
            flex: 1; 
            min-height: 0; 
            overflow-y: auto; 
            overflow-x: auto; 
            width: 100%;
            box-sizing: border-box;
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

        .notification-banner {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 5px solid #f59e0b;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.3s ease-out;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            flex-shrink: 0;
        }
        @keyframes slideDown { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .notif-content h4 { margin: 0; color: #92400e; font-size: 1rem; }
        .notif-content p { margin: 3px 0 0; color: #b45309; font-size: 0.85rem; }
        
        .btn-reset-now { 
            background: #f59e0b; color: white; border: none; padding: 8px 16px; 
            border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.85rem;
            transition: background 0.2s;
        }
        .btn-reset-now:hover { background: #d97706; }

        /* Admin Tools Bar */
        .admin-actions-bar {
            background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;
            flex-shrink: 0;
        }
        .action-info h3 { margin: 0; color: #1e293b; font-size: 1.1rem; }
        .action-info p { margin: 4px 0 0; color: #64748b; font-size: 0.85rem; }
        .btn-group { display: flex; gap: 12px; }
        
        .btn-tool { padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border: none; }
        .btn-export { background: #10b981; color: white; }
        .btn-import { background: #f59e0b; color: white; }
        
        .pagination { margin-top: 20px; display: flex; justify-content: space-between; align-items: center; }
        .btn-page { padding: 6px 12px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; }
        .btn-page.active { background: #2563eb; color: white; border-color: #2563eb; }

        /* ============================================================================
           LAYERED TABLE STRETCH EXTENSIONS
           ============================================================================ */
        .table-container{
            display:flex;
            flex-direction:column;
            min-height:0;
        }

        .table-body-scroll{
            flex:1;
            min-height:0;
            overflow-y:auto;
            overflow-x:auto;
        }

        #userTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            table-layout: auto;
        }
        
        #userTable th, #userTable td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        #userTable thead th{
            position: sticky;
            top: 0;
            z-index: 2;
        }

        #userTable th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        #userTable tbody tr:hover {
            background-color: #f8fafc;
        }

        @media (max-width: 1024px) {
            body { overflow: auto; }
            
            main.content { 
                margin-left: 0 !important; 
                width: 100%; 
                height: auto; 
                overflow: visible;
                padding: 15px; 
            }

            .table-container { 
                overflow-y: visible; 
                max-height: none; 
            }

            #sidebar.active .sidebar-close {
                display: block;
            }

            /* ============================================================================
               LAYERED CSS FOR SHRINKING STAT CARDS ON MOBILE VIEWS
               ============================================================================ */
            .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important; 
                gap: 10px !important;                            
                margin-bottom: 15px !important;
            }

            .stat-card {
                display: flex !important;
                flex-direction: row !important;  
                align-items: center !important;
                padding: 10px 12px !important;   
                border-radius: 8px !important;
                margin-bottom: 0 !important;
            }

            .stat-card .stat-icon {
                font-size: 1.25rem !important;  
                margin-right: 10px !important;   
                margin-bottom: 0 !important;    
                padding: 6px !important;     
                min-width: 32px !important;
                height: 32px !important;
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
            }

            .stat-card .stat-info h3 {
                font-size: 0.75rem !important;  
                margin: 0 !important;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .stat-card .stat-info .number {
                font-size: 1.2rem !important;    
                line-height: 1.1 !important;
                margin-top: 2px !important;
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
                <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="manage_strands.php">Manage Strands</a></li>
                <li><a href="curriculum_guide.php">Curriculum Guide</a></li>
                <li><a href="manage_sections.php">Manage Sections</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Admin')[0]); ?></h1>
                <p>System Overview & Recent Activity</p>
            </header>

            <?php if ($reset_count > 0): ?>
                <div class="notification-banner">
                    <div class="notif-content">
                        <h4>⚠️ Password Reset Requests</h4>
                        <p>There are <strong><?php echo $reset_count; ?></strong> users requesting a password reset.</p>
                    </div>
                    <a href="manage_users.php?filter=reset_requests" class="btn-reset-now">Review Requests</a>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon student-icon">👥</div>
                    <div class="stat-info">
                        <h3>Students</h3>
                        <div class="number"><?php echo $total_students; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon faculty-icon">👨‍🏫</div>
                    <div class="stat-info">
                        <h3>Faculty</h3>
                        <div class="number"><?php echo $total_faculty; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon adviser-icon">📋</div>
                    <div class="stat-info">
                        <h3>Advisers</h3>
                        <div class="number"><?php echo $total_advisers; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon section-icon">🏫</div>
                    <div class="stat-info">
                        <h3>Sections</h3>
                        <div class="number"><?php echo $total_sections; ?></div>
                    </div>
                </div>
            </div>

           

            <div class="table-container">
                <div class="table-header-flex">
                    <h2>Recent Registrations</h2>
                    <div class="table-controls">
                        <input type="text" id="dashboardSearch" placeholder="Search name..." onkeyup="filterTable()">
                        <select id="roleFilter" onchange="filterTable()">
                            <option value="">All Roles</option>
                            <option value="Student">Student</option>
                            <option value="Faculty">Faculty</option>
                            <option value="Adviser">Adviser</option>
                        </select>
                    </div>
                </div>

                <div class="table-body-scroll">
                    <table id="userTable">
                        <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $row): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($row['username']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                            <td><span class="badge badge-<?php echo $row['role']; ?>"><?php echo ucfirst($row['role']); ?></span></td>
                            <td><span class="status-online">● Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="pagination">
                    <div class="page-info">
                        Showing <span><?php echo ($start + 1); ?></span> to <span><?php echo min($start + $limit, $total_results); ?></span> of <strong><?php echo $total_results; ?></strong>
                    </div>
                    <div class="page-buttons">
                        <?php if($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="btn-page">Prev</a>
                        <?php endif; ?>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="btn-page <?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="btn-page">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const burger = document.getElementById('mobileBurger');
            
            sidebar.classList.toggle('active');
            
            // Synchronous visibility handling for the hamburger button
            if (sidebar.classList.contains('active')) {
                burger.classList.add('hidden');
            } else {
                burger.classList.remove('hidden');
            }
        }

        function filterTable() {
            const searchTerm = document.getElementById('dashboardSearch').value.toLowerCase();
            const roleTerm = document.getElementById('roleFilter').value.toLowerCase();
            const rows = document.querySelectorAll("#userTable tbody tr");

            rows.forEach(row => {
                const name = row.cells[1].textContent.toLowerCase();
                const role = row.cells[2].textContent.toLowerCase();
                const matchesSearch = name.includes(searchTerm);
                const matchesRole = roleTerm === "" || role.includes(roleTerm);
                row.style.display = (matchesSearch && matchesRole) ? "" : "none";
            });
        }

        function exportCurrentTableView() {
            const table = document.getElementById('userTable');
            if (!table) return;

            let csvContent = [];
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                if (row.style.display === 'none') {
                    return;
                }

                const rowData = [];
                const cells = row.querySelectorAll('th, td');

                cells.forEach(cell => {
                    let text = cell.innerText.trim().replace(/^●\s*/, '');
                    text = text.replace(/"/g, '""');

                    if (text.includes(',') || text.includes('\n') || text.includes('"')) {
                        text = `"${text}"`;
                    }
                    rowData.push(text);
                });

                if (rowData.length > 0) {
                    csvContent.push(rowData.join(','));
                }
            });

            const csvString = csvContent.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', `Filtered_Users_Export_${new Date().toISOString().slice(0,10)}.csv`);
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function handleAdminImport(input) {
            if (!input.files[0]) return;
            const formData = new FormData();
            formData.append('import_file', input.files[0]);

            if(!confirm("Are you sure you want to bulk update users?")) {
                input.value = '';
                return;
            }

            fetch('admin_import_users.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') window.location.reload();
            })
            .catch(err => alert("An error occurred."))
            .finally(() => input.value = '');
        }
    </script>
</body>
</html>