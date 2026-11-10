<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. Fetch All Strands
$strands_list = $conn->query("
    SELECT s.*
    FROM strands s
    WHERE NOT EXISTS (
        SELECT 1 FROM resource_archives ra
        WHERE ra.resource_type = 'strand' AND ra.resource_id = s.id
    )
    ORDER BY s.strand_name ASC
")->fetchAll();
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
                display: flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 36px;
                width: 36px;
                height: 36px;
                position: relative;
                z-index: 1002;
            }

            #sidebar.active .sidebar-header h3 { margin-bottom: 0; }
        }

        #sidebar:not(.active) ~ main.content { 
            margin-left: 0; 
            width: 100%; 
        }

        #mobileBurger {
            position: fixed;
            top: 15px;
            left: 15px;
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
        @media (max-width: 1024px) {
            #mobileBurger {
                top: 15px;
                left: 15px;
                padding: 10px 15px;
                font-size: 1.1rem;
            }
        }

        .table-container .strands-table {
            width: 100%;
            min-width: 760px;
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

        .table-container th.col-actions { text-align: center !important; }
        .table-container td.col-actions { display: table-cell !important; text-align: center !important; }

        .action-cell-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
        }

        .action-cell-wrapper form { display: inline-flex; margin: 0; }
        .btn-edit,
        .btn-delete {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 68px;
            flex: 0 0 68px;
            padding: 6px 10px;
            border-radius: 6px;
            font: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.2;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
        }

        .btn-edit { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .btn-edit:hover { background: #dbeafe; }

        .btn-delete { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .btn-delete:hover { background: #fee2e2; }

        .table-header .btn-primary,
        .modal-actions .btn-primary {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 34px;
            padding: 7px 12px;
            border: 1px solid #1d4ed8;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font: inherit;
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.2;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
        }

        .table-header .btn-primary:hover,
        .modal-actions .btn-primary:hover { background: #1d4ed8; }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
        }

        .modal-actions button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-width: 68px;
            min-height: 34px;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.2;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
        }

        .modal-actions button:hover { background: #e2e8f0; }
        .modal-actions .btn-primary {
            min-width: 92px;
            border-color: #1d4ed8;
            background: #2563eb;
            color: #fff;
            font-size: 0.8rem;
        }

        .modal-actions .btn-primary:hover { background: #1d4ed8; }

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
                <h1>Academic Strands</h1>
                <p>Define and manage Senior High School tracks</p>
            </header>

            <?php if(($_GET['msg'] ?? '') === 'archived'): ?>
                <div class="alert alert-success">Strand archived and sent to the super admin for review.</div>
            <?php elseif(isset($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>

            <?php if(($_GET['error'] ?? '') === 'archive_failed'): ?>
                <div class="alert alert-error">The strand could not be archived. Please try again.</div>
            <?php elseif(isset($_GET['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Available Strands</h2>
                    <button type="button" class="btn-primary" onclick="prepareNewStrand()">+ Add Strand</button>
                </div>
                <table class="strands-table">
                    <colgroup>
                        <col style="width: 12%;">
                        <col style="width: 23%;">
                        <col style="width: 45%;">
                        <col style="width: 20%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th class="col-name">Strand Name</th>
                            <th class="col-desc">Description</th>
                            <th class="col-actions">Actions</th>
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
                                        <form method="POST" action="archive_resource.php" data-confirm-title="Confirm Archive" data-confirm-label="Archive" data-confirm-variant="danger" data-confirm-message="Archive this strand? The super admin can restore or permanently delete it.">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="resource_type" value="strand">
                                            <input type="hidden" name="resource_id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn-delete">Archive</button>
                                        </form>
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
                    <button type="button" class="modal-cancel" onclick="closeModal('strandModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Strand</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/admin-form-confirmation.js"></script>
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

    </script>
</body>
</html>