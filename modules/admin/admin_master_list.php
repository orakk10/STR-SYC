<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

// 1. Pull Sections for the filter dropdown
$sections_dropdown = $conn->query("SELECT id, section_name FROM sections ORDER BY section_name ASC")->fetchAll();

// 2. Fetch all student records for dynamic client-side filtering
$query_str = "SELECT u.id, u.full_name, s.id AS section_id, s.section_name, s.grade_level 
              FROM users u 
              LEFT JOIN sections s ON u.section_id = s.id 
              WHERE u.role = 'student'
              ORDER BY s.grade_level ASC, s.section_name ASC, u.full_name ASC";

$students = $conn->query($query_str)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master List | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-900: #0f172a;
        }

        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: var(--slate-50);
            color: var(--slate-900);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .dashboard-wrapper {
            display: flex;
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        main.content {
            display: flex;
            flex-grow: 1;
            flex-direction: column;
            min-width: 0;
            width: 100%;
            max-width: 100%;
            height: 100vh;
            margin-left: 260px;
            padding: 30px;
            box-sizing: border-box;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        #sidebar:not(.active) ~ main.content {
            margin-left: 0;
        }

        .master-list-card {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-height: 0;
            padding: 25px;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--slate-200);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }

        .master-list-header {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .master-list-header h1 {
            margin: 0;
            color: var(--slate-900);
        }

        .btn-group {
            display: flex;
            flex: 0 0 auto;
            gap: 10px;
        }

        .btn-tool {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 10px 15px;
            border: 0;
            border-radius: 8px;
            color: #fff;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .btn-export {
            background: #10b981;
        }

        .btn-export:hover {
            background: #059669;
        }

        .btn-import {
            background: #f59e0b;
        }

        .btn-import:hover {
            background: #d97706;
        }

        .file-input-hidden {
            display: none;
        }

        .controls {
            display: grid;
            flex: 0 0 auto;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
            align-items: end;
            margin-bottom: 20px;
        }

        .controls label {
            display: block;
            margin-bottom: 6px;
            color: var(--slate-600);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .controls input,
        .controls select {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: var(--slate-700);
            font: inherit;
            font-size: 0.9rem;
            box-sizing: border-box;
        }

        .controls input:focus,
        .controls select:focus {
            outline: 2px solid rgba(37, 99, 235, 0.2);
            border-color: var(--primary);
        }

        .student-table-container {
            flex: 1;
            min-height: 0;
            width: 100%;
            overflow: auto;
            border: 1px solid var(--slate-200);
            border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 12px 15px;
            border-bottom: 2px solid var(--slate-200);
            background: var(--slate-50);
            color: var(--slate-600);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            padding: 14px 15px;
            border-bottom: 1px solid var(--slate-100);
            background: #fff;
            color: var(--slate-700);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        th:nth-child(1),
        td:nth-child(1) {
            width: 40%;
        }

        th:nth-child(2),
        td:nth-child(2) {
            width: 15%;
        }

        th:nth-child(3),
        td:nth-child(3) {
            width: 25%;
        }

        th:nth-child(4),
        td:nth-child(4) {
            width: 20%;
            text-align: right;
        }

        tbody tr:hover td {
            background: #fcfcfd;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .student-name {
            color: var(--slate-900);
            font-weight: 600;
        }

        .grade-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: var(--slate-100);
            color: var(--slate-700);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border: 1px solid #dbeafe;
            border-radius: 6px;
            background: #eff6ff;
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .btn-print:hover {
            background: var(--primary);
            color: #fff;
        }

        .empty-state {
            padding: 40px 20px;
            color: #94a3b8;
            text-align: center;
        }

        .sidebar-close {
            display: none;
            padding: 0 5px;
            border: 0;
            background: none;
            color: #fff;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
        }

        .sidebar {
            height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            box-sizing: border-box;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.25);
        }

        .mobile-toggle {
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .mobile-toggle.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            body {
                overflow: auto;
            }

            .dashboard-wrapper {
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }

            main.content {
                width: 100%;
                height: auto;
                min-height: 100vh;
                margin-left: 0 !important;
                padding: 20px;
                overflow: visible;
            }

            .master-list-card {
                flex: initial;
                min-height: 0;
                overflow: visible;
            }

            .student-table-container {
                flex: initial;
                min-height: auto;
                overflow-x: auto;
                overflow-y: visible;
            }

            .controls {
                grid-template-columns: 1fr 1fr;
            }

            .controls > div:first-child {
                grid-column: 1 / -1;
            }

            table {
                min-width: 680px;
            }

            #sidebar.active .sidebar-close {
                display: block;
            }
        }

        @media (max-width: 600px) {

            .master-list-card {
                padding: 18px;
            }

            .master-list-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .master-list-header h1 {
                font-size: 1.5rem;
            }

            .btn-group {
                width: 100%;
            }

            .btn-tool {
                flex: 1;
                padding: 10px 8px;
                font-size: 0.8rem;
            }

            .controls {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .controls > div:first-child {
                grid-column: auto;
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
                <li><a href="manage_assignments.php">Teaching Assignments</a></li>
                <li><a href="admin_schedule.php">Manage Schedules</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_master_list.php" class="active">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header master-list-header">
                <h1>Master Student List</h1>
                <div class="btn-group">
                    <button type="button" id="exportStudentsButton" class="btn-tool btn-export">📥 Export CSV</button>
                    <button type="button" class="btn-tool btn-import" onclick="document.getElementById('studentsFile').click()">📤 Import CSV</button>
                    <input type="file" id="studentsFile" class="file-input-hidden" onchange="handleImport(this)">
                </div>
            </header>

            <div class="table-container master-list-card">
                <div class="controls">
                    <div>
                        <label for="searchRealTime">Search Name</label>
                        <input type="text" id="searchRealTime" placeholder="Type name to find instantly..." oninput="filterMasterList()">
                    </div>
                    
                    <div>
                        <label for="gradeFilterRealTime">Level</label>
                        <select id="gradeFilterRealTime" onchange="filterMasterList()">
                            <option value="">All Grades</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>

                    <div>
                        <label for="sectionFilterRealTime">Section</label>
                        <select id="sectionFilterRealTime" onchange="filterMasterList()">
                            <option value="">All Sections</option>
                            <?php foreach ($sections_dropdown as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['section_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="student-table-container">
                    <table id="masterStudentTable">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Grade</th>
                                <th>Section Name</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($students): ?>
                            <?php foreach ($students as $row): ?>
                                <tr class="student-row" 
                                    data-student-id="<?php echo htmlspecialchars($row['id']); ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower($row['full_name'])); ?>"
                                    data-grade="<?php echo htmlspecialchars($row['grade_level']); ?>"
                                    data-section="<?php echo htmlspecialchars($row['section_id']); ?>">
                                    <td><span class="student-name"><?php echo htmlspecialchars($row['full_name']); ?></span></td>
                                    <td><span class="grade-badge"><?php echo htmlspecialchars($row['grade_level'] ?? ''); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['section_name'] ?: 'Unassigned'); ?></td>
                                    <td>
                                        <a href="print_report_card.php?student_id=<?php echo $row['id']; ?>" class="btn-print" target="_blank">Print SF9</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="initialEmptyRow"><td colspan="4" class="empty-state">No student records found in database.</td></tr>
                        <?php endif; ?>
                            
                            <tr id="noResultsFallbackRow" style="display: none;">
                                <td colspan="4" class="empty-state">
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
        document.getElementById('exportStudentsButton').addEventListener('click', function () {
            const visibleRows = Array.from(document.querySelectorAll('#masterStudentTable tbody tr.student-row'))
                .filter(row => row.style.display !== 'none');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_export_students.php';

            visibleRows.forEach(row => {
                const studentId = document.createElement('input');
                studentId.type = 'hidden';
                studentId.name = 'student_ids[]';
                studentId.value = row.dataset.studentId;
                form.appendChild(studentId);
            });

            document.body.appendChild(form);
            form.submit();
            form.remove();
        });

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