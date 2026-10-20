<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

// 2. Active Curriculum Filter Selection
$curriculum_version =$_GET['version'] ?? 'Strengthened';
if (!in_array($curriculum_version, ['Legacy', 'Strengthened'])) {$curriculum_version = 'Strengthened';
}

// 3. Fetch Strands for Dropdowns
$strands_list =$conn->query("SELECT id, strand_name FROM strands ORDER BY strand_name ASC")->fetchAll();

// 4. Fetch Subjects Filtered by Selected Curriculum Version
$query = "SELECT s.*, COALESCE(st.strand_name, 'Universal Core / All Tracks') AS strand_name 
          FROM subjects s 
          LEFT JOIN strands st ON s.strand_id = st.id 
          WHERE s.curriculum_version = :version 
          ORDER BY strand_name ASC, s.grade_level ASC, s.`term` ASC";

$stmt = $conn->prepare($query);
$stmt->execute(['version' =>$curriculum_version]);
$subjects_list =$stmt->fetchAll();

$curriculum = [];
foreach ($subjects_list as $row) {$curriculum[$row['strand_name']][$row['grade_level']][$row['term']][] =$row;
}
$first_strand = !empty($curriculum) ? array_key_first($curriculum) : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Guide | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body { 
            margin: 0; padding: 0; overflow: hidden; 
            font-family: 'Inter', system-ui, sans-serif; background-color: #f8fafc;
        }

        .dashboard-wrapper { display: flex; height: 100vh; width: 100%; position: relative; overflow: hidden; }
        .menu li a.active { background: #2563eb; color: white; border-radius: 8px; }
        .menu li a.logout { color: #f87171; border-top: 1px solid #334155; margin-top: 20px; }

        main.content { 
            flex-grow: 1; margin-left: 260px; padding: 25px 30px; background: #f8fafc; 
            height: 100vh; width: 100%; box-sizing: border-box; display: flex; flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; 
        }

        #curriculumContainer { flex: 1; min-height: 0; overflow-y: auto; padding-right: 4px; }
        
        .version-switcher {
            display: flex; gap: 10px; background: #e2e8f0; padding: 4px; border-radius: 10px; margin-bottom: 15px; width: fit-content;
        }
        .version-btn {
            padding: 8px 16px; border-radius: 8px; border: none; font-weight: 600; font-size: 0.85rem; cursor: pointer; color: #475569; background: transparent; transition: 0.2s;
        }
        .version-btn.active { background: #ffffff; color: #2563eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

        .tab-container { 
            display: flex; gap: 8px; border-bottom: 2px solid #e5e7eb; 
            margin-bottom: 20px; overflow-x: auto; flex-shrink: 0;
        }
        .strand-tab { 
            padding: 10px 18px; cursor: pointer; border: none; background: none; 
            font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; 
            white-space: nowrap; transition: 0.2s; font-size: 0.88rem;
        }
        .strand-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
        .strand-content { display: none; }
        .strand-content.active { display: block; animation: slideDown 0.3s ease-out; }

        @keyframes slideDown { from { transform: translateY(-8px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .admin-actions-bar {
            background: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; flex-shrink: 0;
        }
        .action-info h3 { margin: 0; color: #1e293b; font-size: 1.05rem; }
        .action-info p { margin: 2px 0 0; color: #64748b; font-size: 0.82rem; }

        .grade-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .grade-card { background: white; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .grade-header { background: #1e293b; color: white; padding: 12px 18px; font-weight: 600; }
        .semester-section { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; }
        
        .sem-badge { display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; margin-bottom: 8px; }
        .sem-1 { background: #eff6ff; color: #2563eb; }
        .sem-2 { background: #fff7ed; color: #ea580c; }
        .sem-3 { background: #f0fdf4; color: #16a34a; }
        .sem-full { background: #f3e8ff; color: #7e22ce; }

        .subject-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .subject-table td { padding: 10px 0; border-bottom: 1px solid #f8fafc; }
        .sub-code { font-weight: 700; color: #64748b; width: 110px; font-size: 0.8rem; }
        .sub-name { color: #334155; font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .type-pill { font-size: 10px; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-left: 6px; }
        .units-pill { font-size: 10px; background: #e2e8f0; color: #1e293b; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 4px; }
        
        .action-group { display: flex; justify-content: flex-end; gap: 6px; }
        .action-btn { width: 28px; height: 28px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(30, 41, 59, 0.6); display: flex; align-items: center; justify-content: center; z-index: 2000; }
        .modal-overlay.hidden { display: none; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 460px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #64748b; margin-bottom: 4px; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; box-sizing: border-box; font-size: 0.85rem; }

        .mobile-toggle { transition: opacity 0.2s ease, visibility 0.2s ease; z-index: 999; }
        .mobile-toggle.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .sidebar-close { display: none; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0 5px; line-height: 1; }
        .sidebar { height: 100vh; overflow-y: auto; overflow-x: hidden; box-sizing: border-box; }

        @media (max-width: 1024px) { 
            body { overflow: auto; }
            main.content { margin-left: 0 !important; width: 100%; height: auto; overflow: visible; padding: 15px; }
            #curriculumContainer { overflow-y: visible; max-height: none; }
            .grade-grid { grid-template-columns: 1fr !important; } 
            .admin-actions-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
        }

        #sidebar:not(.active) ~ main.content { margin-left: 0; width: 100%; }
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
                <li><a href="manage_assignments.php">Teaching Assignments</a></li>
                <li><a href="admin_schedule.php">Manage Schedules</a></li>
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
                <p>Manage and view subjects across different DepEd curriculum structures.</p>
            </header>

            <!-- Curriculum Version Switcher -->
            <div class="version-switcher">
                <button class="version-btn <?= $curriculum_version === 'Strengthened' ? 'active' : '' ?>" onclick="switchCurriculum('Strengthened')">
                    Strengthened MATATAG (3-Term / Clusters)
                </button>
                <button class="version-btn <?= $curriculum_version === 'Legacy' ? 'active' : '' ?>" onclick="switchCurriculum('Legacy')">
                    Legacy Curriculum (2-Semester / 31 Subs)
                </button>
            </div>

            <div class="tab-container">
                <?php foreach($curriculum as $name =>$data): ?>
                    <button class="strand-tab <?php echo ($name ===$first_strand) ? 'active' : ''; ?>" 
                            onclick="switchTab(this, 'tab-<?php echo md5($name); ?>')">
                        <?php echo htmlspecialchars($name); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="admin-actions-bar">
                <div class="action-info">
                    <h3>Subject Management (<?= htmlspecialchars($curriculum_version) ?> Framework)</h3>
                    <p>Add new subjects or universal core entries to the active curriculum setup.</p>
                </div>
                <div class="btn-group">
                    <button class="btn-tool" onclick="prepareNewSubject()" style="background: #2563eb; color: white; padding: 10px 22px; border:none; border-radius:8px; font-weight:600; cursor:pointer;">➕ Add New Subject</button>
                </div>
            </div>

            <div id="curriculumContainer">
                <?php if (empty($curriculum)): ?>
                    <div style="background: white; padding: 40px; text-align: center; border-radius: 12px; border: 1px solid #e2e8f0; color: #64748b;">
                        No subject entries found for the <strong><?= htmlspecialchars($curriculum_version) ?> Curriculum</strong>. Use "Add New Subject" or run the seed script to add rows.
                    </div>
                <?php else: ?>
                    <?php foreach($curriculum as $name =>$grades): ?>
                        <div id="tab-<?php echo md5($name); ?>" class="strand-content <?php echo ($name ===$first_strand) ? 'active' : ''; ?>">
                            <div class="grade-grid">
                                <?php ksort($grades); foreach($grades as $lvl =>$terms): ?>
                                    <div class="grade-card">
                                        <div class="grade-header">Grade <?php echo $lvl; ?></div>
                                        <?php ksort($terms); foreach($terms as $t_num =>$subs): ?>
                                            <?php 
                                                $badge_class = 'sem-1'; 
                                                $term_label =$t_num;
                                                
                                                if ($t_num === '1st') { $badge_class = 'sem-1';$term_label = '1st Term'; }
                                                elseif ($t_num === '2nd') { $badge_class = 'sem-2';$term_label = '2nd Term'; }
                                                elseif ($t_num === '3rd') { $badge_class = 'sem-3';$term_label = '3rd Term'; }
                                                elseif ($t_num === '1st Sem') { $badge_class = 'sem-1';$term_label = '1st Semester'; }
                                                elseif ($t_num === '2nd Sem') { $badge_class = 'sem-2';$term_label = '2nd Semester'; }
                                                elseif ($t_num === 'Full Year') { $badge_class = 'sem-full';$term_label = 'Full Year (Core)'; }
                                            ?>
                                            <div class="semester-section">
                                                <span class="sem-badge <?php echo $badge_class; ?>">
                                                    <?php echo $term_label; ?>
                                                </span>
                                                <table class="subject-table">
                                                    <?php foreach($subs as$s): ?>
                                                        <tr>
                                                            <td class="sub-code"><?php echo htmlspecialchars($s['subject_code']); ?></td>
                                                            <td class="sub-name">
                                                                <?php echo htmlspecialchars($s['subject_name']); ?>
                                                                <span class="type-pill"><?php echo htmlspecialchars($s['type'] ?? 'Core'); ?></span>
                                                                <span class="units-pill"><?php echo htmlspecialchars($s['units'] ?? 3); ?> Units</span>
                                                            </td>
                                                            <td class="sub-actions">
                                                                <div class="action-group">
                                                                    <button class="action-btn edit-btn" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, "UTF-8"); ?>)'>✎</button>
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
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Dynamic Subject Modal (Create / Edit) -->
    <div id="subjectModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0; color: #1e293b;">Subject Details</h3>
            <hr style="border:0; border-top: 1px solid #f1f5f9; margin: 12px 0;">
            <form id="subjectForm" method="POST">
                <input type="hidden" name="subject_id" id="subject_id">
                
                <div class="form-group">
                    <label>Curriculum Version</label>
                    <select name="curriculum_version" id="form_curriculum_version" onchange="adaptTermOptions(this.value)" required>
                        <option value="Strengthened">Strengthened MATATAG Curriculum (3-Term)</option>
                        <option value="Legacy">Legacy Curriculum (2-Semester)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assigned Strand / Cluster</label>
                    <select name="strand_id" id="strand_id">
                        <option value="">Universal Core / Applicable to All</option>
                        <?php foreach ($strands_list as$st): ?>
                            <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['strand_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Subject Type</label>
                    <select name="type" id="subject_type" required>
                        <option value="Core">Core Subject</option>
                        <option value="Elective">Elective Subject (Strengthened)</option>
                        <option value="Applied">Applied Subject (Legacy)</option>
                        <option value="Specialized">Specialized Subject (Legacy)</option>
                    </select>
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Grade Level</label>
                        <select name="grade_level" id="grade_level">
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Term / Period</label>
                        <select name="term" id="term">
                            <!-- Options injected dynamically by adaptTermOptions JavaScript function -->
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:2;">
                        <label>Subject Code</label>
                        <input type="text" name="subject_code" id="subject_code" placeholder="e.g., CORE-GENMATH" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Units</label>
                        <input type="number" name="units" id="units" value="3" min="1" max="6" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="subject_name" id="subject_name" placeholder="e.g., General Mathematics" required>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" onclick="closeModal('subjectModal')" style="padding:10px 18px; border:none; background:#f1f5f9; color:#64748b; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
                    <button type="submit" style="background: #2563eb; color: white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Save Subject</button>
                </div>
            </form>
        </div>
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

        function switchCurriculum(version) {
            window.location.href = `curriculum_guide.php?version=${version}`;
        }

        function switchTab(btn, id) {
            document.querySelectorAll('.strand-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.strand-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(id).classList.add('active');
        }

        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

        function adaptTermOptions(version, selectedTerm = '1st') {
            const termSelect = document.getElementById('term');
            termSelect.innerHTML = '';

            if (version === 'Strengthened') {
                const options = [
                    { value: '1st', text: '1st Term' },
                    { value: '2nd', text: '2nd Term' },
                    { value: '3rd', text: '3rd Term' },
                    { value: 'Full Year', text: 'Full Year (Core)' }
                ];
                options.forEach(o => {
                    const opt = document.createElement('option');
                    opt.value = o.value;
                    opt.textContent = o.text;
                    if (o.value === selectedTerm) opt.selected = true;
                    termSelect.appendChild(opt);
                });
            } else {
                const options = [
                    { value: '1st Sem', text: '1st Semester' },
                    { value: '2nd Sem', text: '2nd Semester' }
                ];
                options.forEach(o => {
                    const opt = document.createElement('option');
                    opt.value = o.value;
                    opt.textContent = o.text;
                    if (o.value === selectedTerm) opt.selected = true;
                    termSelect.appendChild(opt);
                });
            }
        }

        function prepareNewSubject() {
            const form = document.getElementById('subjectForm');
            form.reset();
            form.action = "save_subject.php";
            document.getElementById('modalTitle').innerText = "Add New Subject";
            
            const currentVer = "<?= $curriculum_version ?>";
            document.getElementById('form_curriculum_version').value = currentVer;
            adaptTermOptions(currentVer);
            
            openModal('subjectModal');
        }

        function openEditModal(data) {
            const form = document.getElementById('subjectForm');
            form.action = "update_subject.php";
            document.getElementById('modalTitle').innerText = "Edit Subject";
            
            document.getElementById('subject_id').value = data.id;
            document.getElementById('form_curriculum_version').value = data.curriculum_version || 'Strengthened';
            document.getElementById('strand_id').value = data.strand_id || '';
            document.getElementById('subject_type').value = data.type || 'Core';
            document.getElementById('grade_level').value = data.grade_level;
            document.getElementById('units').value = data.units || 3;
            document.getElementById('subject_code').value = data.subject_code;
            document.getElementById('subject_name').value = data.subject_name;
            
            adaptTermOptions(data.curriculum_version || 'Strengthened', data.term || '1st');
            openModal('subjectModal');
        }

        function deleteSubject(id) { 
            if(confirm("Permanently delete this subject?")) {
                window.location.href = `delete_subject.php?id=${id}&version=<?= $curriculum_version ?>`; 
            }
        }

        // Initialize modal term options on boot
        adaptTermOptions("<?= $curriculum_version ?>");
    </script>
</body>
</html>