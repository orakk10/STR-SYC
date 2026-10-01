<?php
// modules/admin/admin_import_users.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Check Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Admin privileges required.']);
    exit();
}

// 2. Validate File Upload
if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please provide a valid CSV file for import.']);
    exit();
}

$filePath = $_FILES['import_file']['tmp_name'];

if (!file_exists($filePath) || !is_readable($filePath)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unable to read uploaded file.']);
    exit();
}

$handle = fopen($filePath, "r");
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to open file stream.']);
    exit();
}

// Skip header row
fgetcsv($handle);

try {
    $conn = getDBConnection();
    
    // Begin PDO Transaction
    $conn->beginTransaction();

    // Prepare User Upsert Query
    $stmtUser = $conn->prepare("
        INSERT INTO users (username, full_name, role, section_id, password, require_reset)
        VALUES (:username, :full_name, :role, :section_id, :password, 1)
        ON DUPLICATE KEY UPDATE
            full_name  = VALUES(full_name),
            role       = VALUES(role),
            section_id = VALUES(section_id)
    ");

    // Prepare Secondary Student Profile Query
    $stmtProfile = $conn->prepare("
        INSERT INTO student_profiles (user_id, gender)
        VALUES (:user_id, 'Male')
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
    ");

    $importedCount = 0;

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        // Skip empty or malformed rows
        if (empty($data[0]) || empty($data[1])) {
            continue;
        }

        $username  = trim($data[0]);
        $fullName  = trim($data[1]);
        $role      = !empty($data[2]) ? strtolower(trim($data[2])) : 'student';
        $sectionId = !empty($data[3]) ? (int)$data[3] : null;

        // Default password matches username (LRN) hashed via BCRYPT
        $hashedPass = password_hash($username, PASSWORD_BCRYPT);

        $stmtUser->execute([
            ':username'   => $username,
            ':full_name'  => $fullName,
            ':role'       => $role,
            ':section_id' => $sectionId,
            ':password'   => $hashedPass
        ]);

        $insertedUserId = $conn->lastInsertId();

        // Attach student profile if new student created
        if ($role === 'student' && $insertedUserId) {
            $stmtProfile->execute([':user_id' => $insertedUserId]);
        }

        $importedCount++;
    }

    // Log Activity
    $stmtLog = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, affected_table, ip_address)
        VALUES (:uid, :action, 'users', :ip)
    ");
    $stmtLog->execute([
        ':uid'    => $_SESSION['user_id'],
        ':action' => "Imported {$importedCount} users via CSV batch file.",
        ':ip'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    // Commit Transaction
    $conn->commit();
    fclose($handle);

    echo json_encode([
        'status'  => 'success',
        'message' => "Successfully imported/updated {$importedCount} user records!"
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($handle) {
        fclose($handle);
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}