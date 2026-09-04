<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (isset($_FILES['import_file'])) {
    $file = $_FILES['import_file']['tmp_name'];
    $handle = fopen($file, "r");
    fgetcsv($handle); // Skip header

    // $conn->begin_transaction();
    try {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $lrn = trim($data[0]);
            $name = trim($data[1]);
            $section = intval($data[2]);
            $role = 'student';
            // Default password for new students
            $password = password_hash($lrn, PASSWORD_DEFAULT); 

            $sql = "INSERT INTO users (username, full_name, section_id, role, password) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    full_name = VALUES(full_name), 
                    section_id = VALUES(section_id)";
            
            $stmt = $conn->prepare($sql);
            // $stmt->bind_param("ssiss", $lrn, $name, $section, $role, $password);
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Student database updated!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}