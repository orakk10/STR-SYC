<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

// Fetch archived students with Strand details
$query = "SELECT a.*, s.strand_name 
           FROM archived_students a 
           LEFT JOIN strands s ON a.strand_id = s.id 
           ORDER BY a.batch_year DESC, a.full_name ASC";
$archive_list = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique years and strands for the filter dropdowns
$years_query = $conn->query("SELECT DISTINCT batch_year FROM archived_students ORDER BY batch_year DESC")->fetchAll(PDO::FETCH_ASSOC);
$strands_query = $conn->query("SELECT * FROM strands ORDER BY strand_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graduate Archive | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        /* Base Viewport Layout Setup */
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

        /* Archive-Specific Styling */
        .batch-year-tag { 
            background: #dbeafe; 
            color: #1e40af; 
            padding: 4px 10px; 
            border-radius: 999px; 
            font-weight: bold; 
            font-size: 0.75rem; 
        }
        
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filter-group { 
            display: flex; 
            flex-direction: column; 
            gap: 5px; flex: 1; 
            min-width: 200px; 
        }

        .filter-group label { 
            font-size: 0.75rem; 
            font-weight: bold; 
            color: #64748b; 
            text-transform: uppercase; 
        }

        .filter-group input, .filter-group select { 
            padding: 10px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-size: 0.9rem;
        }

        .btn-restore-action {
            background: #6366f1;
            color: white;
            padding: 6px 12px;
            border: 0;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-restore-action:hover { 
            background: #4f46e5; 
        }

        .archive-banner {
            background: #1e293b;
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-run-archive {
            background: #ef4444;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            transition: transform 0.1s;
        }
        .btn-run-archive:hover { 
            background: #dc2626; 
            transform: scale(1.02); 
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


        /* Viewport Breakpoints Override Rules */
        @media (max-width: 1024px) {
            body { overflow: auto; }
            
            .sidebar { 
                position: fixed; 
                left: -260px; 
                transition: 0.3s; 
                z-index: 1050; 
            }
            .sidebar.active { left: 0; }
            
            .mobile-toggle { 
                display: block; 
                position: fixed; 
                top: 15px; 
                left: 15px; 
                z-index: 1100; 
                background: #2563eb; 
                color: white; 
                border: none; 
                padding: 10px 15px; 
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

            .filter-bar { 
                flex-direction: column; 
                align-items: stretch; 
            }
            .archive-banner { 
                flex-direction: column; 
                text-align: center; 
                gap: 20px; 
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
                <li><a href="manage_assignments.php">Teaching Assignments</a></li>
                <li><a href="admin_schedule.php">Manage Schedule</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php" class="active">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1>Graduate Archive</h1>
                <p>Manage and search historical records of graduated students.</p>
            </header>

            <?php if (isset($_GET['status'])): ?>
                <?php
                    $statusMessages = [
                        'success' => ['Grade 12 archive completed. Grade 11 students were left active; archived accounts were retained as alumni.', '#dcfce7', '#166534', '#bbf7d0'],
                        'restored' => ['Student account restored successfully.', '#dcfce7', '#166534', '#bbf7d0'],
                        'error' => ['The requested archive operation could not be completed. No records were changed.', '#fee2e2', '#991b1b', '#fecaca']
                    ];
                    $status = $statusMessages[$_GET['status']] ?? null;
                ?>
                <?php if ($status): ?>
                    <div role="status" style="background: <?= $status[1] ?>; color: <?= $status[2] ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid <?= $status[3] ?>;">
                        <?= htmlspecialchars($status[0], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="archive-banner">
                <div>
                    <h3 style="margin: 0; font-size: 1.2rem;">Run End-of-Year Archive</h3>
                    <p style="margin: 5px 0 0; opacity: 0.8; font-size: 0.9rem;">This archives currently active Grade 12 students for batch year <?php echo date("Y"); ?>. Grade 11 students remain active. Archived accounts are retained and changed to alumni.</p>
                </div>
                <form action="process_archive.php" method="POST" onsubmit="return confirm('Archive all currently active Grade 12 students as alumni for <?= date('Y') ?>? Grade 11 students will remain active.')">
                    <button type="submit" name="trigger_archive" class="btn-run-archive">📦 Start Archiving</button>
                </form>
            </div>

            <div class="filter-bar">
                <div class="filter-group" style="flex: 2;">
                    <label>Quick Search</label>
                    <input type="text" id="archiveSearch" placeholder="Search by name, LRN, or username..." onkeyup="runFilters()">
                </div>
                <div class="filter-group">
                    <label>Batch Year</label>
                    <select id="batchFilter" onchange="runFilters()">
                        <option value="">All Batches</option>
                       <?php foreach ($years_query as $y): ?>
                            <option value="<?php echo $y['batch_year']; ?>">Batch <?php echo $y['batch_year']; ?></option>
                       <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Strand</label>
                    <select id="strandFilter" onchange="runFilters()">
                        <option value="">All Strands</option>
                        <?php foreach ($strands_query as $s): ?>
                            <option value="<?php echo htmlspecialchars($s['strand_name']); ?>"><?php echo htmlspecialchars($s['strand_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr style="text-align: left;">
                            <th>LRN / Username</th>
                            <th>Student Name</th>
                            <th>Strand</th>
                            <th>Batch</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="archiveTableBody">
                        <?php if (!empty($archive_list)): ?>
                            <?php foreach ($archive_list as $row): ?>
                            <tr data-archive-row>
                                <td style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($row['lrn'] ?: ($row['username'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="strand-cell"><?php echo htmlspecialchars($row['strand_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><span class="batch-year-tag"><?php echo htmlspecialchars((string)($row['batch_year'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td style="text-align: right;">
                                    <form action="process_restore.php" method="POST" onsubmit="return confirm('Restore this student account to active student status?')">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="btn-restore-action">🔄 Restore</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr data-empty-row><td colspan="5" style="text-align:center; padding: 40px; color: #64748b;">No archived records found.</td></tr>
                        <?php endif; ?>
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

        function runFilters() {
            const search = document.getElementById('archiveSearch').value.toLowerCase();
            const batch = document.getElementById('batchFilter').value;
            const strand = document.getElementById('strandFilter').value.toLowerCase();
            const rows = document.querySelectorAll("#archiveTableBody tr");

            rows.forEach(row => {
                // Ignore empty-state row if it exists
                if (!row.hasAttribute('data-archive-row')) return;

                const rowText = row.innerText.toLowerCase();
                const rowBatch = row.querySelector('.batch-year-tag')?.innerText || '';
                const rowStrand = row.querySelector('.strand-cell')?.innerText.toLowerCase() || '';

                const matchesSearch = rowText.includes(search);
                const matchesBatch = (batch === "") || (rowBatch === batch);
                const matchesStrand = (strand === "") || (rowStrand.includes(strand));

                row.style.display = (matchesSearch && matchesBatch && matchesStrand) ? "" : "none";
            });
        }
    </script>
</body>
</html>