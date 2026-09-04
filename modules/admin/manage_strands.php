<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

// 2. Fetch All Strands
$strands_list = $conn->query("SELECT * FROM strands ORDER BY strand_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Strands | STRAND-SYNC</title>
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

        /* --- BURGER TOGGLE UTILITIES --- */
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

        /* Responsive Breakpoints */
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

        #mobileBurger {
            position: fixed;
            top: 25px;
            left: 25px;
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #mobileBurger:hover {
            background: #4338ca;
        }

        #sidebar:not(.active) ~ main.content .content-header {
            padding-left: 65px;
        }

        @media (max-width: 1024px) {
            #mobileBurger {
                top: 15px;
                left: 15px;
                padding: 8px 12px;
                font-size: 1.1rem;
            }
            main.content .content-header {
                padding-left: 55px;
                margin-top: 2px;
            }
            #sidebar:not(.active) ~ main.content .content-header {
                padding-left: 55px;
            }
        }

        .table-container table {
            width: 100%;
            min-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .table-container th, 
        .table-container td {
            padding: 14px 16px;
            font-size: 0.95rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            box-sizing: border-box;
        }

        .table-container th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
        }

        .col-id { width: 12%; }
        .col-name { width: 23%; }
        .col-desc { width: 45%; }
        
        .table-container th.col-actions { 
            text-align: right !important;
            padding-right: 16px;
        }

        .col-actions { 
            width: 20%; 
        }

        .action-cell-wrapper {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            width: max-content;
            margin-left: auto;
            box-sizing: border-box;
        }

        .btn-edit {
            padding: 6px 12px;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-edit:hover {
            background: #dbeafe;
        }

        .btn-delete {
            padding: 6px 12px;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        .desc-text {
            word-break: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #475569;
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
                <li><a href="manage_strands.php" class="active">Manage Strands</a></li>
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
                <h1>Academic Strands</h1>
                <p>Define and manage Senior High School tracks</p>
            </header>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Available Strands</h2>
                    <button class="btn-primary" onclick="prepareNewStrand()">+ Add Strand</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th class="col-name">Strand Name</th>
                            <th class="col-desc">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($strands_list)): ?>
                            <?php foreach ($strands_list as $row): ?>
                            <tr>
                                <td class="col-id">#<?php echo $row['id']; ?></td>
                                <td class="col-name"><strong><?php echo htmlspecialchars($row['strand_name']); ?></strong></td>
                                <td class="col-desc">
                                    <div class="desc-text">
                                        <?php echo !empty($row['description']) ? htmlspecialchars($row['description']) : '<em style="color:#94a3b8;">No description provided</em>'; ?>
                                    </div>
                                </td>
                                <td class="col-actions">
                                    <div class="action-cell-wrapper">
                                        <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                                        <button class="btn-delete" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['strand_name']); ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 40px; color: #64748b;">No strands found. Click "+ Add Strand" to create one.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="strandModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="modalTitle">New Strand Registration</h3>
            <form id="strandForm" action="save_strand.php" method="POST">
                <input type="hidden" name="strand_id" id="strand_id">
                
                <div class="input-group">
                    <label>Strand Name</label>
                    <input type="text" name="strand_name" id="strand_name" placeholder="e.g. STEM, HUMSS, ABM" required>
                </div>
                <div class="input-group">
                    <label>Description</label>
                    <textarea name="description" id="description" placeholder="Brief overview of the track" style="width:100%; border-radius:8px; border:1px solid #cbd5e1; padding:10px; min-height: 100px; font-family: inherit;"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('strandModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Strand</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal & Sidebar Controls
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
        
        function toggleSidebar() { 
            const sidebar = document.getElementById('sidebar');
            const burger = document.getElementById('mobileBurger');
            
            sidebar.classList.toggle('active'); 
            
            // Toggle hamburger button visibility dynamically
            if (sidebar.classList.contains('active')) {
                burger.classList.add('hidden');
            } else {
                burger.classList.remove('hidden');
            }
        }

        // Logic to switch Modal to "ADD" mode
        function prepareNewStrand() {
            document.getElementById('strandForm').reset();
            document.getElementById('strandForm').action = "save_strand.php";
            document.getElementById('modalTitle').innerText = "New Strand Registration";
            document.getElementById('strand_id').value = "";
            openModal('strandModal');
        }

        // Logic to switch Modal to "EDIT" mode and fill data
        function openEditModal(data) {
            document.getElementById('strandForm').action = "update_strand.php";
            document.getElementById('modalTitle').innerText = "Edit Strand: " + data.strand_name;
            
            document.getElementById('strand_id').value = data.id;
            document.getElementById('strand_name').value = data.strand_name;
            document.getElementById('description').value = data.description;
            
            openModal('strandModal');
        }

        // Delete Confirmation Utility
        function confirmDelete(id, name) {
            if (confirm("Are you sure you want to delete the '" + name + "' strand? This may affect sections assigned to it.")) {
                window.location.href = "delete_strand.php?id=" + id;
            }
        }
    </script>
</body>
</html>