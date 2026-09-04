<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../manifest/login.php");
    exit();
}

$min_age_date = date('Y-m-d', strtotime('-16 years'));

$where_clause = "WHERE 1=1"; 
if (isset($_GET['filter']) && $_GET['filter'] === 'reset_requests') {
    $where_clause = "WHERE u.reset_requested = 1";
}

$query = "SELECT u.*, s.section_name, s.grade_level, st.strand_name 
          FROM users u 
          LEFT JOIN sections s ON u.section_id = s.id 
          LEFT JOIN strands st ON s.strand_id = st.id
          $where_clause
          ORDER BY u.reset_requested DESC, u.role DESC, u.full_name ASC";
$users_list = $conn->query($query)->fetchAll();

$sections_dropdown = $conn->query("SELECT id, section_name, grade_level FROM sections ORDER BY grade_level ASC, section_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | STRAND-SYNC</title>
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

        .row-needs-reset { background-color: #fffbeb !important; border-left: 5px solid #f59e0b !important; }
        .badge-reset { background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-left: 5px; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .hidden { display: none !important; }
        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .profile-grid .full-width { grid-column: span 2; }
        .profile-grid label { display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 5px; font-weight: bold; }
        .profile-grid input, .profile-grid select, .profile-grid textarea { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; }
        .btn-add-user { background: #2563eb; color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid transparent; }
        .alert-success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border-color: #fecaca; }

        .mobile-toggle {
            display: none;
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

        @media (max-width: 768px) {
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
                <li><a href="manage_users.php" class="active">Manage Users</a></li>
                <li><a href="admin_master_list.php">Master List</a></li>
                <li><a href="admin_logs.php">Activity Logs</a></li>
                <li><a href="admin_archive.php">Graduate Archive</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>User Management</h1>
                <button class="btn-add-user" onclick="openModal('addUserModal')">+ Add New User</button>
            </header>

            <?php if(isset($_GET['msg'])): ?>
                <div class="alert alert-<?php echo (strpos($_GET['msg'], 'success') !== false) ? 'success' : 'error'; ?>">
                    <?php 
                        if($_GET['msg'] == 'success') echo "Action completed successfully!";
                        else if($_GET['msg'] == 'error_age') echo "Error: User must be at least 16 years old.";
                        else if($_GET['msg'] == 'error_self_delete') echo "Error: You cannot delete your own account.";
                        else echo "An error occurred. Please try again.";
                    ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div class="header-tools" style="display: flex; gap: 10px;">
                        <input type="text" id="userSearch" placeholder="Search name or LRN..." onkeyup="filterTable()">
                        <select id="roleFilter" onchange="filterTable()">
                            <option value="">All Roles</option>
                            <option value="student">Student</option>
                            <option value="adviser">Adviser</option>
                            <option value="faculty">Faculty</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Username / LRN</th>
                            <th>Role</th>
                            <th>Placement (Grade/Strand/Section)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php foreach ($users_list as $row): ?>
                        <tr class="<?php echo ($row['reset_requested'] == 1) ? 'row-needs-reset' : ''; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                <?php if($row['reset_requested'] == 1): ?><span class="badge-reset">REQ</span><?php endif; ?>
                                <br>
                                <button type="button" class="btn-view-profile" onclick="viewProfile(<?php echo $row['id']; ?>)" style="font-size: 0.75rem; padding: 2px 5px; cursor: pointer; margin-top: 4px;">Edit Info</button>
                            </td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><span class="role-badge <?php echo strtolower($row['role']); ?>"><?php echo ucfirst($row['role']); ?></span></td>
                            <td>
                                <?php if($row['section_name']): ?>
                                    G<?php echo $row['grade_level']; ?> - <?php echo htmlspecialchars($row['strand_name']); ?> (<?php echo htmlspecialchars($row['section_name']); ?>)
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-edit" onclick="resetPassword(<?php echo $row['id']; ?>)">Reset</button>
                                <button class="btn-delete" onclick="deleteUser(<?php echo $row['id']; ?>)">Remove</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="addUserModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2>Create New Account</h2>
            <form action="save_user.php" method="POST">
                <div class="profile-grid">
                    <div class="full-width"><label>Full Name</label><input type="text" name="full_name" required></div>
                    <div><label>Username / LRN</label><input type="text" name="username" required></div>
                    <div>
                        <label>Role</label>
                        <select name="role" id="addRole" onchange="toggleSectionField('addRole', 'addSectionGroup')" required>
                            <option value="student">Student</option>
                            <option value="adviser">Adviser</option>
                            <option value="faculty">Faculty</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="full-width" id="addSectionGroup">
                        <label>Initial Section Assignment</label>
                        <select name="section_id">
                            <option value="">-- No Section --</option>
                            <?php foreach ($sections_dropdown as $sec): ?>
                                <option value="<?php echo $sec['id']; ?>">G<?php echo $sec['grade_level']; ?> - <?php echo htmlspecialchars($sec['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="full-width"><label>Temporary Password</label><input type="password" name="password" required value="strand1234"></div>
                </div>
                <div class="modal-footer" style="margin-top: 20px; text-align: right;">
                    <button type="button" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="btn-add-user">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <div id="profileModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2 id="prof_name_title">Edit User</h2>
            <form action="update_profile.php" method="POST">
                <input type="hidden" name="user_id" id="prof_user_id">
                <div class="profile-grid">
                    <div class="full-width" id="editSectionGroup" style="background: #f1f5f9; padding: 10px; border-radius: 8px;">
                        <label>Academic Placement (Transfer Section)</label>
                        <select name="section_id" id="prof_section">
                            <option value="">Unassigned</option>
                            <?php foreach ($sections_dropdown as $sec): ?>
                                <option value="<?php echo $sec['id']; ?>">G<?php echo $sec['grade_level']; ?> - <?php echo htmlspecialchars($sec['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Gender</label><select name="gender" id="prof_gender"><option value="Male">Male</option><option value="Female">Female</option></select></div>
                    <div><label>Birthdate</label>
                        <input type="date" name="birthdate" id="prof_bday" max="<?php echo $min_age_date; ?>" required>
                    </div>
                    <div><label>Contact No.</label><input type="text" name="contact_no" id="prof_contact"></div>
                    <div class="full-width"><label>Address</label><textarea name="address" id="prof_address" rows="2"></textarea></div>
                </div>
                <div class="profile-grid">
                    <div><label>Guardian Name</label><input type="text" name="guardian_name" id="prof_gname"></div>
                    <div><label>Guardian Contact</label><input type="text" name="guardian_contact" id="prof_gcontact"></div>
                </div>
                <div class="full-width" id="specGroup" style="display: none; background: #fefce8; padding: 10px; border-radius: 8px; border: 1px solid #fef08a; margin-top: 15px;">
                    <label>Professional Specialization</label>
                    <input type="text" name="specialization" id="prof_spec" placeholder="e.g., Mathematics, Social Sciences, STEM Coordinator">
                </div>
                <div class="modal-footer" style="margin-top: 20px; text-align: right;">
                    <button type="button" onclick="closeModal('profileModal')">Close</button>
                    <button type="submit" class="btn-add-user">Save Changes</button>
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

        function toggleSectionField(roleId, targetId) {
            const role = document.getElementById(roleId).value;
            document.getElementById(targetId).style.display = (role === 'admin' || role === 'faculty') ? 'none' : 'block';
            
            const addSpec = document.getElementById('addSpecGroup');
            if(addSpec) {
                addSpec.style.display = (role === 'faculty' || role === 'adviser') ? 'block' : 'none';
            }
        }

        function filterTable() {
            const search = document.getElementById('userSearch').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value.toLowerCase();
            const rows = document.querySelectorAll("#userTableBody tr");
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const role = row.cells[2].innerText.toLowerCase();
                row.style.display = (text.includes(search) && (roleFilter === "" || role.includes(roleFilter))) ? "" : "none";
            });
        }

        function deleteUser(id) { if(confirm("Permanently delete this user?")) window.location.href = `delete_user.php?id=${id}`; }
        function resetPassword(id) { if(confirm("Reset password to 'strand1234'?")) window.location.href = `admin_perform_reset.php?id=${id}`; }

        function viewProfile(userId) {
            fetch(`get_profile.php?id=${userId}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('prof_user_id').value = userId;
                    document.getElementById('prof_name_title').innerText = "Edit: " + data.full_name;

                    document.getElementById('prof_section').value = data.section_id || "";
                    document.getElementById('editSectionGroup').style.display = (data.role === 'admin' || data.role === 'faculty') ? 'none' : 'block';

                    const specGroup = document.getElementById('specGroup');
                    if (specGroup) {
                        if (data.role === 'faculty' || data.role === 'adviser') {
                            specGroup.style.display = 'block';
                            document.getElementById('prof_spec').value = data.specialization || '';
                        } else {
                            specGroup.style.display = 'none';
                        }
                    }

                    document.getElementById('prof_gender').value = data.gender || 'Male';
                    document.getElementById('prof_bday').value = data.birthdate || '';
                    document.getElementById('prof_contact').value = data.contact_no || '';
                    document.getElementById('prof_address').value = data.address || '';
                    document.getElementById('prof_gname').value = data.guardian_name || '';
                    document.getElementById('prof_gcontact').value = data.guardian_contact || '';
                    
                    openModal('profileModal');
                });
        }
    </script>
</body>
</html>