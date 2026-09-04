<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (isset($_FILES['import_file'])) {
    $file = $_FILES['import_file']['tmp_name'];
    $handle = fopen($file, "r");
    fgetcsv($handle); // Skip the header row

    // $conn->begin_transaction();
    try {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $username = trim($data[0]);
            $full_name = trim($data[1]);
            $role = strtolower(trim($data[2]));
            $section_id = !empty($data[3]) ? intval($data[3]) : NULL;
            
            // Password defaults to the LRN for new accounts
            $hashed_pass = password_hash($username, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (username, full_name, role, section_id, password) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    full_name = VALUES(full_name), 
                    role = VALUES(role), 
                    section_id = VALUES(section_id)";
            
            $stmt = $conn->prepare($sql);
            // $stmt->bind_param("sssis", $username, $full_name, $role, $section_id, $hashed_pass);
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'System records updated successfully!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
    }
    fclose($handle);
}