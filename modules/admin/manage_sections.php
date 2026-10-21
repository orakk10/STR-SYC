<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

// 2. Active Filter Options
$active_ay = $_GET['academic_year'] ?? '2026-2027';
$active_version = $_GET['curriculum_version'] ?? 'Strengthened';

// 3. Fetch Selection Options for Modals and Filters
$strands = $conn->query("SELECT id, strand_name FROM strands ORDER BY strand_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$advisers = $conn->query("SELECT id, full_name FROM users WHERE role IN ('adviser', 'faculty') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Filtered Active Sections List
$query = "
    SELECT 
        s.id, s.section_name, s.grade_level, s.strand_id, s.adviser_id, 
        s.academic_year, s.curriculum_version,
        COALESCE(st.strand_name, 'General / Multi-Cluster') AS strand_name, 
        u.full_name AS adviser_name 
    FROM sections s
    LEFT JOIN strands st ON s.strand_id = st.id
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE s.academic_year = :ay AND s.curriculum_version = :version
    ORDER BY s.grade_level ASC, s.section_name ASC
";

$stmt = $conn->prepare($query);
$stmt->execute(['ay' => $active_ay, 'version' => $active_version]);
$sections_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sections | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body { 
            margin: 0; padding: 0; overflow: hidden; 
            font-family: 'Inter', system-ui, sans-serif; background-color: #f8fafc;
        }

        .dashboard-wrapper { display: flex; height: 100vh; width: 100%; overflow: hidden; position: relative; }

        main.content { 
            flex-grow: 1; margin-left: 260px; padding: 25px 30px; width: 100%; 
            height: 100vh; box-sizing: border-box; display: flex; flex-direction: column; 
            transition: margin-left 0.3s ease; overflow: hidden; 
        }

        .table-container { 
            flex: 1; min-height: 0; overflow-y: auto; overflow-x: auto; 
            background: white; padding: 25px; border-radius: 12px; 
            border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
        }

        .version-badge {
            display: inline-block; font-size: 0.72rem; font-weight: 700;
            padding: 3px 8px; border-radius: 6px; text-transform: uppercase;
        }
        .ver-matatag { background: #eff6ff; color: #2563eb; }
        .ver-legacy { background: #fff7ed; color: #ea580c; }

        .btn-action { padding: 6px 12px; border-radius: 6px; border: none; font-weight: 600; font-size: 0.8rem; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-edit { background: #f1f5f9; color: #334155; }
        .btn-subjects { background: #6366f1; color: white; }
        .btn-delete { background: #fef2f2; color: #ef4444; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(30, 41, 59, 0.6); display: flex; align-items: center; justify-content: center; z-index: 2000; }
        .modal-overlay.hidden { display: none; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 440px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .input-group { margin-bottom: 14px; }
        .input-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #64748b; margin-bottom: 4px; }
        .input-group input, .input-group select { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; box-sizing: border-box; font-size: 0.85rem; }

        .modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
        .modal-actions button { padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; }

        .mobile-toggle { display: none; z-index: 999; }
        .sidebar-close { display: none; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .sidebar { height: 100vh; overflow-y: auto; box-sizing: border-box; }

        @media (max-width: 1024px) {
            body { overflow: auto; }
            main.content { margin-left: 0 !important; width: 100%; height: auto; overflow: visible; padding: 15px; }
            .table-container { overflow-y: visible; max-height: none; }
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
                <li><a href="curriculum_guide.php">Curriculum Guide</a></li>
                <li><a href="manage_sections.php" class="active">Manage Sections</a></li>
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
                <h1 style="margin: 0; font-size: 1.4rem;">Manage Sections</h1>
                <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.85rem;">Organize class sections, strands, and advisers per Academic Year.</p>
            </header>

            <?php
                $success_messages = [
                    'assigned' => 'Section assignments updated successfully.',
                    'saved' => 'Section created successfully.',
                    'updated' => 'Section updated successfully.',
                    'deleted' => 'Section deleted successfully.',
                ];
                $error_messages = [
                    'invalid_request' => 'Invalid request. Please submit the section form again.',
                    'missing_required_fields' => 'Please complete all required section fields.',
                    'missing_data' => 'The section details are invalid. Please review and try again.',
                    'invalid_id' => 'The section could not be found.',
                ];
                $success_message = $success_messages[$_GET['msg'] ?? ''] ?? null;
                $error_message = $error_messages[$_GET['error'] ?? ''] ?? null;
            ?>
            <?php if ($success_message !== null): ?>
                <div style="background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; padding: 10px; border-radius: 8px; margin: 15px 0 0 0; font-size: 0.85rem; font-weight: 600;">
                    ✔ <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message !== null): ?>
                <div role="alert" style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin: 15px 0 0 0; font-size: 0.85rem; font-weight: 600;">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <div class="table-container" style="margin-top: 15px;">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                        <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Filter:</label>
                        <select name="academic_year" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem;">
                            <option value="2026-2027" <?= $active_ay === '2026-2027' ? 'selected' : '' ?>>SY 2026-2027</option>
                            <option value="2025-2026" <?= $active_ay === '2025-2026' ? 'selected' : '' ?>>SY 2025-2026</option>
                        </select>

                        <select name="curriculum_version" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem;">
                            <option value="Strengthened" <?= $active_version === 'Strengthened' ? 'selected' : '' ?>>Strengthened MATATAG</option>
                            <option value="Legacy" <?= $active_version === 'Legacy' ? 'selected' : '' ?>>Legacy Curriculum</option>
                        </select>
                    </form>

                    <button class="btn-primary" onclick="prepareNewSection()" style="background: #2563eb; color: white; padding: 8px 16px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer;">
                        + New Section
                    </button>
                </div>

                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; background: #f8fafc;">
                            <th style="padding: 10px;">Grade</th>
                            <th style="padding: 10px;">Section Name</th>
                            <th style="padding: 10px;">Strand / Cluster</th>
                            <th style="padding: 10px;">Class Adviser</th>
                            <th style="padding: 10px;">Framework</th>
                            <th style="width: 240px; text-align: center; padding: 10px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sections_list)): ?>
                            <?php foreach ($sections_list as $row): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px;">Grade <?php echo $row['grade_level']; ?></td>
                                <td style="padding: 10px;"><strong><?php echo htmlspecialchars($row['section_name']); ?></strong></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($row['strand_name']); ?></td>
                                <td style="padding: 10px;"><?php echo $row['adviser_name'] ? htmlspecialchars($row['adviser_name']) : '<em style="color:#94a3b8;">Not Assigned</em>'; ?></td>
                                <td style="padding: 10px;">
                                    <span class="version-badge <?= $row['curriculum_version'] === 'Strengthened' ? 'ver-matatag' : 'ver-legacy' ?>">
                                        <?= htmlspecialchars($row['curriculum_version']) ?>
                                    </span>
                                </td>
                                <td style="text-align: center; padding: 10px;">
                                    <button class="btn-action btn-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                                    
                                    <a href="manage_assignments.php?filter_section=<?php echo $row['id']; ?>&academic_year=<?= urlencode($row['academic_year']) ?>" class="btn-action btn-subjects">
                                        Subjects
                                    </a>

                                    <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['section_name']); ?>')">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">
                                    No sections found for SY <?= htmlspecialchars($active_ay) ?> (<?= htmlspecialchars($active_version) ?> Framework).
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Section Create/Edit Modal -->
    <div id="sectionModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0; color:#1e293b;">New Section Details</h3>
            <form id="sectionForm" action="save_section.php" method="POST">
                <input type="hidden" name="section_id" id="section_id">
                
                <div class="input-group">
                    <label>Academic Year</label>
                    <select name="academic_year" id="academic_year" required>
                        <option value="2026-2027">SY 2026-2027</option>
                        <option value="2025-2026">SY 2025-2026</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Curriculum Framework</label>
                    <select name="curriculum_version" id="curriculum_version" required>
                        <option value="Strengthened">Strengthened MATATAG Curriculum</option>
                        <option value="Legacy">Legacy Curriculum</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Section Name</label>
                    <input type="text" name="section_name" id="section_name" placeholder="e.g. 11-Newton" required>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="input-group" style="flex:1;">
                        <label>Grade Level</label>
                        <select name="grade_level" id="grade_level">
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label>Strand / Cluster</label>
                        <select name="strand_id" id="strand_id">
                            <option value="">General / Multi-Cluster</option>
                            <?php foreach ($strands as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['strand_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label>Class Adviser</label>
                    <select name="adviser_id" id="adviser_id">
                        <option value="">-- No Adviser Assigned --</option>
                        <?php foreach ($advisers as $a): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closeModal('sectionModal')" style="background:#f1f5f9; color:#64748b;">Cancel</button>
                    <button type="submit" style="background:#2563eb; color:white;">Save Section</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
        
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

        function prepareNewSection() {
            document.getElementById('sectionForm').reset();
            document.getElementById('sectionForm').action = "save_section.php";
            document.getElementById('modalTitle').innerText = "New Section Details";
            document.getElementById('section_id').value = "";
            document.getElementById('academic_year').value = "<?= $active_ay ?>";
            document.getElementById('curriculum_version').value = "<?= $active_version ?>";
            openModal('sectionModal');
        }

        function openEditModal(data) {
            document.getElementById('sectionForm').action = "update_section.php";
            document.getElementById('modalTitle').innerText = "Edit Section: " + data.section_name;
            
            document.getElementById('section_id').value = data.id;
            document.getElementById('academic_year').value = data.academic_year || "2026-2027";
            document.getElementById('curriculum_version').value = data.curriculum_version || "Strengthened";
            document.getElementById('section_name').value = data.section_name;
            document.getElementById('grade_level').value = data.grade_level;
            document.getElementById('strand_id').value = data.strand_id || "";
            document.getElementById('adviser_id').value = data.adviser_id || "";
            
            openModal('sectionModal');
        }

        function confirmDelete(id, name) {
            if (confirm("Are you sure you want to delete section '" + name + "'? Associated student enrollments will be affected.")) {
                window.location.href = "delete_section.php?id=" + id + "&academic_year=<?= urlencode($active_ay) ?>&curriculum_version=<?= urlencode($active_version) ?>";
            }
        }
    </script>
</body>
</html>