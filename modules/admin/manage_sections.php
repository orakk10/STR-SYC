<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

$strands = $conn->query("SELECT id, strand_name FROM strands")->fetchAll();
$advisers = $conn->query("SELECT id, full_name FROM users WHERE role = 'adviser'")->fetchAll();

$sections_list = $conn->query("
    SELECT 
        s.id, s.section_name, s.grade_level, s.strand_id, s.adviser_id,
        st.strand_name, u.full_name as adviser_name 
    FROM sections s
    JOIN strands st ON s.strand_id = st.id
    LEFT JOIN users u ON s.adviser_id = u.id
    ORDER BY s.grade_level ASC, s.section_name ASC
")->fetchAll();
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
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
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
                <li><a href="manage_sections.php" class="active">Manage Sections</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <h1>Manage Sections</h1>
                <p>Assign Strands and Advisers to Classes</p>
            </header>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success">
                    <?php 
                        if($_GET['msg'] == 'assigned') echo "Subject teachers updated successfully.";
                        if($_GET['msg'] == 'deleted') echo "Section deleted successfully.";
                    ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Active Sections</h2>
                    <button class="btn-primary" onclick="prepareNewSection()">+ Section</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Grade</th>
                            <th>Section Name</th>
                            <th>Strand</th>
                            <th>Class Adviser</th>
                            <th style="width: 250px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections_list as $row): ?>
                        <tr>
                            <td>Grade <?php echo $row['grade_level']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['section_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['strand_name']); ?></td>
                            <td><?php echo $row['adviser_name'] ? htmlspecialchars($row['adviser_name']) : '<em>Not Assigned</em>'; ?></td>
                            <td style="text-align: center;">
                                <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                                
                                <a href="manage_assignments.php?section_id=<?php echo $row['id']; ?>" class="btn-primary" 
                                   style="text-decoration:none; padding: 6px 12px; font-size: 0.8rem; background-color: #6366f1;">
                                   Subjects
                                </a>

                                <button class="btn-delete" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['section_name']); ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="sectionModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="modalTitle">New Section Details</h3>
            <form id="sectionForm" action="save_section.php" method="POST">
                <input type="hidden" name="section_id" id="section_id">
                
                <div class="input-group">
                    <label>Section Name</label>
                    <input type="text" name="section_name" id="section_name" placeholder="e.g. 11-Newton" required>
                </div>
                <div class="input-group">
                    <label>Grade Level</label>
                    <select name="grade_level" id="grade_level">
                        <option value="11">Grade 11</option>
                        <option value="12">Grade 12</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Strand</label>
                    <select name="strand_id" id="strand_id" required>
                        <?php foreach ($strands as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['strand_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Adviser</label>
                    <select name="adviser_id" id="adviser_id">
                        <option value="">-- No Adviser Yet --</option>
                        <?php foreach ($advisers as $a): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('sectionModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Section</button>
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
            openModal('sectionModal');
        }

        function openEditModal(data) {
            document.getElementById('sectionForm').action = "update_section.php";
            document.getElementById('modalTitle').innerText = "Edit Section: " + data.section_name;
            
            document.getElementById('section_id').value = data.id;
            document.getElementById('section_name').value = data.section_name;
            document.getElementById('grade_level').value = data.grade_level;
            document.getElementById('strand_id').value = data.strand_id;
            document.getElementById('adviser_id').value = data.adviser_id || "";
            
            openModal('sectionModal');
        }

        function confirmDelete(id, name) {
            if (confirm("Are you sure you want to delete section '" + name + "'? Students in this section will be unassigned.")) {
                window.location.href = "delete_section.php?id=" + id;
            }
        }
    </script>
</body>
</html>