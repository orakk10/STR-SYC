<?php
session_start();
require_once 'db_config.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 1. Pull Sections for the filter dropdown
$sections_dropdown = $conn->query("SELECT id, section_name FROM sections ORDER BY section_name ASC");

// 2. Fetch all student records for dynamic client-side filtering
$query_str = "SELECT u.id, u.full_name, s.id AS section_id, s.section_name, s.grade_level 
              FROM users u 
              LEFT JOIN sections s ON u.section_id = s.id 
              WHERE u.role = 'student'
              ORDER BY s.grade_level ASC, s.section_name ASC, u.full_name ASC";

$result = $conn->query($query_str);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master List | STRAND-SYNC</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-900: #0f172a;
        }

        body { 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            background-color: var(--slate-50); 
            color: var(--slate-900);
            margin: 0;
            padding: 0;
        }

        .dashboard-wrapper { 
            display: flex; 
            height: 100vh; 
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .content-area {
            flex-grow: 1;
            margin-left: 260px;
            padding: 30px;
            width: 100%;
            max-width: 100%;
            height: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .list-card { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            border: 1px solid var(--slate-200);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
        }

        .master-list-card {
            flex: 1;
            height: auto;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .master-list-card .card-header,
        .master-list-card .controls {
            flex: 0 0 auto;
        }

        .card-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--slate-900);
            letter-spacing: -0.025em;
            margin: 0;
        }

        .controls {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 12px;
            background: var(--slate-100);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            align-items: end;
        }

        .controls label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--slate-600);
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .controls input, .controls select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--slate-200);
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--slate-700);
            background: white;
            box-sizing: border-box;
        }


        .table-container { 
            overflow-x: auto; 
            overflow-y: auto;
            margin-top: 10px;
            flex: 1;
            min-height: 0;
            border: 1px solid var(--slate-200);
            border-radius: 8px;
        }

        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0;
            table-layout: fixed;
        }

        th { 
            background-color: var(--slate-50);
            text-align: left; 
            padding: 12px 15px; 
            color: var(--slate-600); 
            font-size: 0.75rem; 
            font-weight: 600;
            text-transform: uppercase; 
            border-bottom: 2px solid var(--slate-200);
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td { 
            padding: 16px 15px; 
            border-bottom: 1px solid var(--slate-100); 
            font-size: 0.95rem;
            vertical-align: middle;
            color: var(--slate-700);
            background: white;
        }

   
        th:nth-child(1), td:nth-child(1) { width: 40%; }
        th:nth-child(2), td:nth-child(2) { width: 15%; }
        th:nth-child(3), td:nth-child(3) { width: 25%; }
        th:nth-child(4), td:nth-child(4) { width: 20%; text-align: right; }

        tr:hover td { 
            background-color: #fcfcfd; 
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            background: #eff6ff;
            color: #2563eb;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #dbeafe;
            transition: all 0.2s;
        }

        .btn-print:hover { 
            background: #2563eb; 
            color: white; 
            transform: translateY(-1px); 
        }

        .btn-tool {
            font-weight: 600;
            padding: 10px 20px;
        }

        @media (max-width: 1024px) {
            .dashboard-wrapper { 
                overflow: auto; 
                height: auto; 
            }
            
            .content-area { 
                margin-left: 0 !important; 
                width: 100%; 
                height: auto; 
                overflow: visible;
                padding: 15px; 
            }
            
            .controls { 
                grid-template-columns: 1fr; 
            }
            
            .master-list-card { 
                height: auto; 
                overflow: visible; 
            }
            
            .table-container { 
                overflow-y: visible; 
                max-height: none; 
            }
        }
    
        #sidebar:not(.active) ~ .content-area {
            margin-left: 0;
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

        .mobile-toggle {
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .mobile-toggle.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        @media (max-width: 1024px) {
            #sidebar.active .sidebar-close {
                display: block;
            }
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
                <li><a href="admin_master_list.php" class="active">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content-area">
            <div class="list-card master-list-card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h2>Master Student List</h2>
                    <div class="btn-group" style="display: flex; gap: 10px;">
                        <a href="admin_export_students.php" class="btn-tool btn-export" style="background: #10b981; color: white; text-decoration: none; padding: 10px 15px; border-radius: 8px; font-size: 0.85rem;">📥 Export CSV</a>
                        <button onclick="document.getElementById('studentsFile').click()" class="btn-tool" style="background: #f59e0b; color: white; border: none; padding: 10px 15px; border-radius: 8px; font-size: 0.85rem; cursor: pointer;">📤 Import CSV</button>
                        <input type="file" id="studentsFile" style="display:none" onchange="handleImport(this)">
                    </div>
                </div>

                <div class="controls">
                    <div>
                        <label>Search Name</label>
                        <input type="text" id="searchRealTime" placeholder="Type name to find instantly..." oninput="filterMasterList()">
                    </div>
                    
                    <div>
                        <label>Level</label>
                        <select id="gradeFilterRealTime" onchange="filterMasterList()">
                            <option value="">All Grades</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>

                    <div>
                        <label>Section</label>
                        <select id="sectionFilterRealTime" onchange="filterMasterList()">
                            <option value="">All Sections</option>
                            <?php 
                            $sections_dropdown->data_seek(0);
                            while($s = $sections_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['section_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="table-container">
                    <table id="masterStudentTable">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Grade</th>
                                <th>Section Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="student-row" 
                                    data-name="<?php echo htmlspecialchars(strtolower($row['full_name'])); ?>"
                                    data-grade="<?php echo htmlspecialchars($row['grade_level']); ?>"
                                    data-section="<?php echo htmlspecialchars($row['section_id']); ?>">
                                    <td><span style="font-weight: 600; color: var(--slate-900);"><?php echo htmlspecialchars($row['full_name']); ?></span></td>
                                    <td><span class="badge" style="background: var(--slate-100); padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;"><?php echo $row['grade_level']; ?></span></td>
                                    <td><?php echo htmlspecialchars($row['section_name'] ?: 'Unassigned'); ?></td>
                                    <td style="text-align: right;">
                                        <a href="print_report_card.php?student_id=<?php echo $row['id']; ?>" class="btn-print" target="_blank">Print SF9</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr id="initialEmptyRow"><td colspan="4" style="text-align: center; padding: 60px; color: #94a3b8;">No student records found in database.</td></tr>
                            <?php endif; ?>
                            
                            <tr id="noResultsFallbackRow" style="display: none;">
                                <td colspan="4" style="text-align: center; padding: 60px; color: #94a3b8;">
                                    No student records found matching your criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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

        // ============================================================================
        // REAL-TIME SEARCH ENGINE AND FILTERING
        // ============================================================================
        function filterMasterList() {
            const searchVal = document.getElementById('searchRealTime').value.toLowerCase().trim();
            const gradeVal = document.getElementById('gradeFilterRealTime').value;
            const sectionVal = document.getElementById('sectionFilterRealTime').value;
            
            const rows = document.querySelectorAll('.student-row');
            const fallbackRow = document.getElementById('noResultsFallbackRow');
            let visibleCounts = 0;

            rows.forEach(row => {
                const studentName = row.getAttribute('data-name');
                const studentGrade = row.getAttribute('data-grade');
                const studentSection = row.getAttribute('data-section');

                // Evaluate parameters concurrently
                const matchSearch = studentName.includes(searchVal);
                const matchGrade = (gradeVal === "") || (studentGrade === gradeVal);
                const matchSection = (sectionVal === "") || (studentSection === sectionVal);

                if (matchSearch && matchGrade && matchSection) {
                    row.style.display = "";
                    visibleCounts++;
                } else {
                    row.style.display = "none";
                }
            });

            if (rows.length > 0) {
                fallbackRow.style.display = (visibleCounts === 0) ? "" : "none";
            }
        }

        function handleImport(input) {
            if (!input.files[0]) return;
            const formData = new FormData();
            formData.append('import_file', input.files[0]);

            if(!confirm('Import students? This will update the Master List.')) {
                input.value = '';
                return;
            }

            fetch('admin_import_students.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') window.location.reload();
                })
                .catch(() => alert('Import processing error.'))
                .finally(() => { input.value = ''; });
        }
    </script>
</body>
</html>