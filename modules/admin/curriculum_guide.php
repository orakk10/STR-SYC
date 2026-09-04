<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

// 2. Fetch Strands for Dropdowns
$strands_list = $conn->query("SELECT id, strand_name FROM strands ORDER BY strand_name ASC")->fetchAll();

// 3. Fetch Subjects and group by Strand -> Grade -> Semester
$query = "SELECT s.*, st.strand_name 
          FROM subjects s 
          JOIN strands st ON s.strand_id = st.id 
          ORDER BY st.strand_name ASC, s.grade_level ASC, s.semester ASC";
$subjects_list = $conn->query($query)->fetchAll();

$curriculum = [];
foreach ($subjects_list as $row) {
    $curriculum[$row['strand_name']][$row['grade_level']][$row['semester']][] = $row;
}
$first_strand = !empty($curriculum) ? array_key_first($curriculum) : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Curriculum Guide | STRAND-SYNC</title>
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
            position: relative; 
            overflow: hidden;
        }
        
        /* Sidebar Styles Alignment */
        .menu li a.active { background: #2563eb; color: white; border-radius: 8px; }
        .menu li a.logout { color: #f87171; border-top: 1px solid #334155; margin-top: 20px; }

        /* Fluid Content Area Configuration */
        main.content { 
            flex-grow: 1; 
            margin-left: 260px; /* Default Desktop Reserved Width */
            padding: 30px; 
            background: #f8fafc; 
            height: 100vh;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden; 
        }

        /* Scrollable Data Viewport Area */
        #curriculumContainer {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
        }
        
        /* Tab System */
        .tab-container { 
            display: flex; gap: 8px; border-bottom: 2px solid #e5e7eb; 
            margin-bottom: 25px; overflow-x: auto; flex-shrink: 0;
        }
        .strand-tab { 
            padding: 12px 20px; cursor: pointer; border: none; background: none; 
            font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; 
            white-space: nowrap; transition: 0.2s; font-size: 0.9rem;
        }
        .strand-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
        .strand-content { display: none; }
        .strand-content.active { display: block; animation: slideDown 0.3s ease-out; }

        @keyframes slideDown { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Action Management Bar Configuration */
        .admin-actions-bar {
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            margin-bottom: 25px;
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
            border: 1px solid #e5e7eb;
            flex-shrink: 0;
        }
        .action-info h3 { margin: 0; color: #1e293b; font-size: 1.1rem; }
        .action-info p { margin: 4px 0 0; color: #64748b; font-size: 0.85rem; }

        /* Grade Cards Structure */
        .grade-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .grade-card { background: white; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .grade-header { background: #1e293b; color: white; padding: 14px 20px; font-weight: 600; }
        .semester-section { padding: 20px; border-bottom: 1px solid #f1f5f9; }
        .sem-badge { display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 4px 8px; border-radius: 6px; margin-bottom: 10px; }
        .sem-1 { background: #eff6ff; color: #2563eb; }
        .sem-2 { background: #fff7ed; color: #ea580c; }

        /* Tables Formatting */
        .subject-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .subject-table td { padding: 12px 0; border-bottom: 1px solid #f8fafc; }
        .sub-code { font-weight: 700; color: #64748b; width: 90px; font-size: 0.8rem; }
        .sub-name { color: #334155; font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .action-group { display: flex; justify-content: flex-end; gap: 8px; }
        .action-btn { width: 30px; height: 30px; border: 1px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; }

        /* Modal Layout Structures */
        .modal-overlay { position: fixed; inset: 0; background: rgba(30, 41, 59, 0.6); display: flex; align-items: center; justify-content: center; z-index: 2000; }
        .modal-overlay.hidden { display: none; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 420px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; outline: none; }

        /* --- BURGER TOGGLE LAYERING MODIFICATIONS --- */
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

        /* Viewport Breakpoint Overrides */
        @media (max-width: 1024px) { 
            body { overflow: auto; }

            main.content { 
                margin-left: 0 !important; 
                width: 100%; 
                height: auto; 
                overflow: visible;
                padding: 15px; 
            }

            #curriculumContainer {
                overflow-y: visible;
                max-height: none;
            }

            .grade-grid { grid-template-columns: 1fr !important; } 
            .admin-actions-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
            .btn-group, .btn-tool { width: 100% !important; justify-content: center; }
            
            #sidebar.active .sidebar-close {
                display: block;
            }
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .content, .semester-section, .grade-card {
                padding: 10px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important; 
            }

            .sidebar { z-index: 9999; }
            .sidebar:not(.active) { transform: translateX(-100%); position: absolute; }

            .subject-table, .subject-table tbody { display: block !important; width: 100% !important; }
            .subject-table tr {
                display: grid !important;
                grid-template-columns: 70px 1fr 92px !important; 
                align-items: center !important;
                width: 100% !important;
                padding: 10px 0 !important;
                border-bottom: 1px solid #f1f5f9;
            }

            .sub-code { font-size: 10px !important; white-space: nowrap; overflow: hidden; text-overflow: clip; }
            .sub-name { font-size: 12px !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; padding: 0 5px !important; min-width: 0 !important; }
            .sub-actions { display: flex !important; justify-content: flex-end !important; width: 92px !important; padding-left: 4px !important; }
            .action-group { display: flex !important; gap: 6px !important; justify-content: flex-end !important; flex-wrap: nowrap !important; }
            .action-btn { width: 34px !important; height: 34px !important; flex: 0 0 34px !important; font-size: 14px !important; position: relative !important; z-index: 1 !important; }
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
                <li><a href="curriculum_guide.php" class="active">Curriculum Guide</a></li>
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
                <h1>Curriculum Guide</h1>
                <p>Structure academic subjects per strand.</p>
            </header>

            <div class="tab-container">
                <?php foreach($curriculum as $name => $data): ?>
                    <button class="strand-tab <?php echo ($name === $first_strand) ? 'active' : ''; ?>" 
                            onclick="switchTab(this, 'tab-<?php echo md5($name); ?>')">
                        <?php echo htmlspecialchars($name); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="admin-actions-bar">
                <div class="action-info">
                    <h3>Subject Management</h3>
                    <p>Add new subject entries to the current curriculum structure.</p>
                </div>
                <div class="btn-group">
                    <button class="btn-tool btn-export" onclick="prepareNewSubject()" style="padding: 10px 22px; border:none; border-radius:8px; font-weight:600; cursor:pointer;">➕ Add New Subject</button>
                </div>
            </div>

            <div id="curriculumContainer">
                <?php foreach($curriculum as $name => $grades): ?>
                    <div id="tab-<?php echo md5($name); ?>" class="strand-content <?php echo ($name === $first_strand) ? 'active' : ''; ?>">
                        <div class="grade-grid">
                            <?php ksort($grades); foreach($grades as $lvl => $sems): ?>
                                <div class="grade-card">
                                    <div class="grade-header">Grade <?php echo $lvl; ?></div>
                                    <?php ksort($sems); foreach($sems as $s_num => $subs): ?>
                                        <div class="semester-section">
                                            <span class="sem-badge <?php echo ($s_num === '1st') ? 'sem-1' : 'sem-2'; ?>">
                                                <?php echo ($s_num === '1st') ? '1st Semester' : '2nd Semester'; ?>
                                            </span>
                                            <table class="subject-table">
                                                <?php foreach($subs as $s): ?>
                                                    <tr>
                                                        <td class="sub-code"><?php echo htmlspecialchars($s['subject_code']); ?></td>
                                                        <td class="sub-name"><?php echo htmlspecialchars($s['subject_name']); ?></td>
                                                        <td class="sub-actions">
                                                            <div class="action-group">
                                                                <button class="action-btn edit-btn" onclick='openEditModal(<?php echo json_encode($s); ?>)'>✎</button>
                                                                <button class="action-btn delete-btn" onclick="deleteSubject(<?php echo $s['id']; ?>)">🗑</button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <div id="subjectModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0; color: #1e293b;">Subject Details</h3>
            <hr style="border:0; border-top: 1px solid #f1f5f9; margin: 15px 0;">
            <form id="subjectForm" method="POST">
                <input type="hidden" name="subject_id" id="subject_id">
                <div class="form-group">
                    <label>Assigned Strand</label>
                    <select name="strand_id" id="strand_id" required>
                        <?php foreach ($strands_list as $st): ?>
                            <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['strand_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;"><label>Grade</label>
                        <select name="grade_level" id="grade_level"><option value="11">11</option><option value="12">12</option></select>
                    </div>
                    <div class="form-group" style="flex:1;"><label>Semester</label>
                        <select name="semester" id="semester"><option value="1st">1st</option><option value="2nd">2nd</option></select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="subject_code" id="subject_code" required>
                </div>
                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="subject_name" id="subject_name" required>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" onclick="closeModal('subjectModal')" style="padding:10px 18px; border:none; background:#f1f5f9; color:#64748b; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-tool btn-export" style="border:none; padding:10px 20px;">Save Subject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() { 
            const sidebar = document.getElementById('sidebar');
            const burger = document.getElementById('mobileBurger');
            
            sidebar.classList.toggle('active'); 
            
            // Synchronized state tracker toggling visibility
            if (sidebar.classList.contains('active')) {
                burger.classList.add('hidden');
            } else {
                burger.classList.remove('hidden');
            }
        }

        function switchTab(btn, id) {
            document.querySelectorAll('.strand-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.strand-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(id).classList.add('active');
        }
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
        function prepareNewSubject() {
            const form = document.getElementById('subjectForm');
            form.reset();
            form.action = "save_subject.php";
            document.getElementById('modalTitle').innerText = "Add New Subject";
            openModal('subjectModal');
        }
        function openEditModal(data) {
            const form = document.getElementById('subjectForm');
            form.action = "update_subject.php";
            document.getElementById('modalTitle').innerText = "Edit Subject";
            document.getElementById('subject_id').value = data.id;
            document.getElementById('strand_id').value = data.strand_id;
            document.getElementById('grade_level').value = data.grade_level;
            document.getElementById('semester').value = data.semester;
            document.getElementById('subject_code').value = data.subject_code;
            document.getElementById('subject_name').value = data.subject_name;
            openModal('subjectModal');
        }
        function deleteSubject(id) { if(confirm("Permanently delete this subject?")) window.location.href = `delete_subject.php?id=${id}`; }
    </script>
</body>
</html>