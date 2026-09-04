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
// $query = "SELECT a.*, s.strand_name 
//           FROM archived_students a 
//           LEFT JOIN strands s ON a.strand_id = s.id 
//           ORDER BY a.batch_year DESC, a.last_name ASC";
// $archive_list = $conn->query($query);

// Fetch unique years and strands for the filter dropdowns
$years_query = $conn->query("SELECT DISTINCT batch_year FROM archived_students ORDER BY batch_year DESC");
$strands_query = $conn->query("SELECT * FROM strands ORDER BY strand_name ASC");
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

        .filter-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 200px; }
        .filter-group label { font-size: 0.75rem; font-weight: bold; color: #64748b; text-transform: uppercase; }
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
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-restore-action:hover { background: #4f46e5; }

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
        .btn-run-archive:hover { background: #dc2626; transform: scale(1.02); }

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

            .filter-bar { flex-direction: column; align-items: stretch; }
            .archive-banner { flex-direction: column; text-align: center; gap: 20px; }
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

            <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                    Batch archive completed successfully. Records moved to storage.
                </div>
            <?php endif; ?>

            <div class="archive-banner">
                <div>
                    <h3 style="margin: 0; font-size: 1.2rem;">Run End-of-Year Archive</h3>
                    <p style="margin: 5px 0 0; opacity: 0.8; font-size: 0.9rem;">This will move all active students to the graduate records for the year <?php echo date("Y"); ?>.</p>
                </div>
                <form action="process_archive.php" method="POST" onsubmit="return confirm('CRITICAL: This will move all current students to the archive. Proceed?')">
                    <button type="submit" name="trigger_archive" class="btn-run-archive">📦 Start Archiving</button>
                </form>
            </div>

            <div class="filter-bar">
                <div class="filter-group" style="flex: 2;">
                    <label>Quick Search</label>
                    <input type="text" id="archiveSearch" placeholder="Search by name, LRN, or email..." onkeyup="runFilters()">
                </div>
                <div class="filter-group">
                    <label>Batch Year</label>
                    <select id="batchFilter" onchange="runFilters()">
                        <option value="">All Batches</option>
                       <?php /* while($y = $years_query->fetch_assoc()): ?>
                            <option value="<?php echo $y['batch_year']; ?>">Batch <?php echo $y['batch_year']; ?></option>
                       <?php endwhile; */ ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Strand</label>
                    <select id="strandFilter" onchange="runFilters()">
                        <option value="">All Strands</option>
                        <?php /* while($s = $strands_query->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($s['strand_name']); ?>"><?php echo htmlspecialchars($s['strand_name']); ?></option>
                        <?php endwhile; */ ?>
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
                        </tr>
                    </thead>
                    <tbody id="archiveTableBody">
                        <?php /* if($archive_list && $archive_list->num_rows > 0): ?>
                            <?php while($row = $archive_list->fetch_assoc()): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['last_name'] . ", " . $row['first_name']); ?></td>
                                <td><span class="strand-cell"><?php echo htmlspecialchars($row['strand_name'] ?? 'Unassigned'); ?></span></td>
                                <td><span class="batch-year-tag"><?php echo $row['batch_year']; ?></span></td>
                                <td style="text-align: right;">
                                    <a href="process_restore.php?id=<?php echo $row['id']; ?>" 
                                       class="btn-restore-action" 
                                       onclick="return confirm('Restore this student to active status?')">🔄 Restore</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color: #64748b;">No archived records found.</td></tr>
                        <?php endif; */ ?>
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
                if (row.cells.length < 4) return;

                const rowText = row.innerText.toLowerCase();
                const rowBatch = row.querySelector('.batch-year-tag').innerText;
                const rowStrand = row.querySelector('.strand-cell').innerText.toLowerCase();

                const matchesSearch = rowText.includes(search);
                const matchesBatch = (batch === "") || (rowBatch === batch);
                const matchesStrand = (strand === "") || (rowStrand.includes(strand));

                row.style.display = (matchesSearch && matchesBatch && matchesStrand) ? "" : "none";
            });
        }
    </script>
</body>
</html>