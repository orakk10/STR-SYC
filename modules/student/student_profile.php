<?php
session_start();
require_once 'db_config.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// --- POST REQUEST PROCESSING HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Action 1: Update Personal Info & Extended Profile Card Fields
    if (isset($_POST['update_info'])) {
        $full_name = trim($_POST['full_name']);
        
        // Grab new extended profile values safely
        $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : null;
        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $address = trim($_POST['address']);
        $contact_no = trim($_POST['contact_no']);
        $guardian_name = trim($_POST['guardian_name']);
        $guardian_contact = trim($_POST['guardian_contact']);
        $specialization = trim($_POST['specialization']);
        
        if (!empty($full_name)) {
            // Start a quick transaction block to ensure both database tables sync seamlessly
            $conn->begin_transaction();
            
            try {
                // Update Main Users Table Info
                $update_user = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                $update_user->bind_param("si", $full_name, $user_id);
                $update_user->execute();
                
                // Save or update entries inside student_profiles table structure
                $profile_sync = $conn->prepare("INSERT INTO student_profiles (user_id, birthdate, gender, address, contact_no, guardian_name, guardian_contact, specialization) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                                                ON DUPLICATE KEY UPDATE 
                                                    birthdate = VALUES(birthdate), 
                                                    gender = VALUES(gender), 
                                                    address = VALUES(address), 
                                                    contact_no = VALUES(contact_no), 
                                                    guardian_name = VALUES(guardian_name), 
                                                    guardian_contact = VALUES(guardian_contact), 
                                                    specialization = VALUES(specialization)");
                
                $profile_sync->bind_param("isssssss", $user_id, $birthdate, $gender, $address, $contact_no, $guardian_name, $guardian_contact, $specialization);
                $profile_sync->execute();
                
                $conn->commit();
                $message = "Personal profile fields and information updated successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error writing changes to database records: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Full name field cannot be empty.";
            $message_type = "error";
        }
    }
    
    // Action 2: Change Password Securely
    elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password === $confirm_password) {
                // Verify original password
                $pass_check = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $pass_check->bind_param("i", $user_id);
                $pass_check->execute();
                $res = $pass_check->get_result()->fetch_assoc();
                
                if (password_verify($current_password, $res['password'])) {
                    $new_hashed = password_hash($new_password, PASSWORD_BCRYPT);
                    $pass_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $pass_update->bind_param("si", $new_hashed, $user_id);
                    $pass_update->execute();
                    $message = "Password modified securely and updated successfully.";
                    $message_type = "success";
                } else {
                    $message = "Your current password value does not match our records.";
                    $message_type = "error";
                }
            } else {
                $message = "New password confirmation entries do not match.";
                $message_type = "error";
            }
        } else {
            $message = "All password fields are mandatory parameters.";
            $message_type = "error";
        }
    }
    
    // Action 3: Profile Photo Validation & Target Upload Engine
    elseif (isset($_POST['upload_avatar'])) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_name = $_FILES['avatar']['name'];
            $file_size = $_FILES['avatar']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size = 2 * 1024 * 1024; // 2MB Bounds Limit

            if (in_array($file_ext, $allowed_exts)) {
                if ($file_size <= $max_size) {
                    // Build local system target path directory safely
                    $upload_dir = 'uploads/profile_pics/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $new_filename = "student_" . $user_id . "_" . time() . "." . $file_ext;
                    $target_destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $target_destination)) {
                        // Delete previous asset references if present
                        $old_pic_stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
                        $old_pic_stmt->bind_param("i", $user_id);
                        $old_pic_stmt->execute();
                        $old_res = $old_pic_stmt->get_result()->fetch_assoc();
                        if (!empty($old_res['profile_image']) && file_exists($old_res['profile_image'])) {
                            @unlink($old_res['profile_image']);
                        }
                        
                        $img_stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                        $img_stmt->bind_param("si", $target_destination, $user_id);
                        $img_stmt->execute();
                        
                        $message = "Profile picture updated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error writing binary file resource to disk storage directory.";
                        $message_type = "error";
                    }
                } else {
                    $message = "File exceeds the maximum allowable asset size of 2MB.";
                    $message_type = "error";
                }
            } else {
                $message = "Invalid file type format extension. Only JPG, PNG, and WEBP accepted.";
                $message_type = "error";
            }
        } else {
            $message = "Please select a valid image file to upload resource data.";
            $message_type = "error";
        }
    }
}

// Fetch Latest Student Record Info + Extended Metadata from Profile Relationship Table
$query = "SELECT u.full_name, u.username as lrn, u.profile_image, s.section_name, s.grade_level, str.strand_name,
                 sp.birthdate, sp.gender, sp.address, sp.contact_no, sp.guardian_name, sp.guardian_contact, sp.specialization
          FROM users u 
          LEFT JOIN sections s ON u.section_id = s.id 
          LEFT JOIN strands str ON s.strand_id = str.id
          LEFT JOIN student_profiles sp ON u.id = sp.user_id
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student_data = $stmt->get_result()->fetch_assoc();

// Fallback avatar if property is null
$avatar_src = !empty($student_data['profile_image']) ? $student_data['profile_image'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | STRAND-SYNC</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        /* Base Viewport Layout Setup */
        body { margin: 0; padding: 0; overflow: hidden; }
        .dashboard-wrapper { display: flex; height: 100vh; width: 100%; overflow: hidden; position: relative; }

        /* Fluid Content Area Configuration */
        main.content { 
            flex-grow: 1; margin-left: 260px; padding: 30px; width: 100%; max-width: 100%; height: 100vh; 
            box-sizing: border-box; display: flex; flex-direction: column; 
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow-y: auto; 
        }

        /* Profile Layout Content Framework */
        .profile-container { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-top: 20px; }
        .profile-card, .form-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); height: max-content; }
        
        .avatar-wrapper { text-align: center; margin-bottom: 20px; }
        .avatar-preview { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #3b82f6; margin-bottom: 15px; background: #f8fafc; }
        
        .form-section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9; }
        .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .form-section h3 { margin: 0 0 15px 0; color: #1e293b; font-size: 1.1rem; border-left: 4px solid #2563eb; padding-left: 10px; }
        
        /* Grid layout for cleaner rendering of multiple fields */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }

        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; gap: 5px; }
        .form-group.full-width { grid-column: span 2; }
        @media (max-width: 640px) { .form-group.full-width { grid-column: span 1; } }

        .form-group label { font-size: 0.85rem; font-weight: 600; color: #64748b; }
        .form-control { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; color: #334155; width: 100%; box-sizing: border-box; }
        .form-control:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        
        .btn-action { background: #2563eb; color: white; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-action:hover { background: #1d4ed8; }

        /* Status Alert Panels */
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; font-weight: 500; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* --- BURGER TOGGLE UTILITIES --- */
        .mobile-toggle { display: none; transition: opacity 0.2s ease, visibility 0.2s ease; z-index: 999; background: #2563eb; color: white; border: none; padding: 8px 12px; border-radius: 6px; font-size: 1.1rem; cursor: pointer; line-height: 1; margin-bottom: 15px; align-self: flex-start; }
        .mobile-toggle.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .sidebar-close { display: none; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0 5px; line-height: 1; }

        @media (max-width: 1024px) {
            body { overflow: auto; }
            .sidebar { position: fixed; left: -260px; transition: 0.3s; z-index: 1050; }
            .sidebar.active { left: 0; }
            .mobile-toggle { display: block; }
            main.content { margin-left: 0 !important; width: 100%; height: auto; overflow-y: visible; padding: 15px; padding-top: 20px; }
            .profile-container { grid-template-columns: 1fr; gap: 20px; }
            #sidebar.active .sidebar-close { display: block; }
        }
        #sidebar:not(.active) ~ main.content { margin-left: 0; width: 100%; }
    </style>
</head>
<body>

    <div class="dashboard-wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="student_dashboard.php">My Dashboard</a></li>
                <li><a href="student_announcements.php">Announcements</a></li>
                <li><a href="student_grades.php">My Grades</a></li>
                <li><a href="logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <button class="mobile-toggle" id="mobileBurger" onclick="toggleSidebar()">☰</button>

            <header class="content-header" style="margin-bottom: 20px;">
                <h1 style="margin: 0 0 5px 0;">Account Settings</h1>
                <p style="margin: 0; color: #64748b;">Manage your user profile fields, personal documentation coordinates, and system credentials.</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <div class="profile-card">
                    <div class="avatar-wrapper">
                        <img src="<?php echo $avatar_src; ?>" alt="Avatar" class="avatar-preview">
                        <h2 style="margin: 10px 0 5px 0; font-size: 1.2rem;"><?php echo htmlspecialchars($student_data['full_name']); ?></h2>
                        <p style="margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 600;">LRN: <?php echo htmlspecialchars($student_data['lrn']); ?></p>
                    </div>

                    <form action="student_profile.php" method="POST" enctype="multipart/form-data" style="margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                        <div class="form-group">
                            <label for="avatar">Upload New Avatar Photo</label>
                            <input type="file" name="avatar" id="avatar" accept="image/*" class="form-control" style="padding: 5px;">
                            <small style="color: #94a3b8; font-size: 0.75rem; margin-top: 3px;">Max size 2MB. Format limits: JPG, PNG, WEBP.</small>
                        </div>
                        <button type="submit" name="upload_avatar" class="btn-action" style="width: 100%; justify-content: center;">🖼️ Save Photo</button>
                    </form>
                </div>

                <div class="form-card">
                    <form action="student_profile.php" method="POST" class="form-section">
                        <h3>Update Personal Information</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($student_data['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Assigned Section Context</label>
                                <input type="text" class="form-control" disabled value="Grade <?php echo ($student_data['grade_level'] ?? 'N/A') . ' - ' . ($student_data['section_name'] ?? 'Unassigned') . ' (' . ($student_data['strand_name'] ?? 'General') . ')'; ?>">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="birthdate">Birthdate</label>
                                <input type="date" name="birthdate" id="birthdate" class="form-control" value="<?php echo htmlspecialchars($student_data['birthdate'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender Orientation</label>
                                <select name="gender" id="gender" class="form-control">
                                    <option value="">Select Gender...</option>
                                    <option value="Male" <?php echo (($student_data['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (($student_data['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo (($student_data['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="contact_no">Contact Number</label>
                                <input type="text" name="contact_no" id="contact_no" class="form-control" placeholder="e.g. 09123456789" value="<?php echo htmlspecialchars($student_data['contact_no'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="specialization">Specialization / Focus Track</label>
                                <input type="text" name="specialization" id="specialization" class="form-control" placeholder="e.g. Mobile App Development" value="<?php echo htmlspecialchars($student_data['specialization'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="address">Residential Address</label>
                            <input type="text" name="address" id="address" class="form-control" placeholder="Complete Street, Barangay, City, Province" value="<?php echo htmlspecialchars($student_data['address'] ?? ''); ?>">
                        </div>

                        <div class="form-grid" style="margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                            <div class="form-group">
                                <label for="guardian_name">Parent / Guardian Name</label>
                                <input type="text" name="guardian_name" id="guardian_name" class="form-control" value="<?php echo htmlspecialchars($student_data['guardian_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="guardian_contact">Guardian Contact Number</label>
                                <input type="text" name="guardian_contact" id="guardian_contact" class="form-control" value="<?php echo htmlspecialchars($student_data['guardian_contact'] ?? ''); ?>">
                            </div>
                        </div>

                        <button type="submit" name="update_info" class="btn-action" style="margin-top: 10px;">💾 Save Changes</button>
                    </form>

                    <form action="student_profile.php" method="POST" class="form-section">
                        <h3>Change Password Verification</h3>
                        <div class="form-group">
                            <label for="current_password">Current Secure Password</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Selected Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password Entry</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn-action" style="background-color: #0f172a;">🔒 Modify Password</button>
                    </form>
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
                if (burger) burger.classList.add('hidden');
            } else {
                if (burger) burger.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>