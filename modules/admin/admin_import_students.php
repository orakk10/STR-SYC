<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Admin privileges required.']);
    exit();
}

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

$handle = fopen($filePath, 'r');
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to open file stream.']);
    exit();
}

if (fgetcsv($handle) === false) {
    fclose($handle);
    echo json_encode(['status' => 'error', 'message' => 'The uploaded CSV file is empty.']);
    exit();
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO users (username, lrn, full_name, role, section_id, password, require_reset)
        VALUES (:username, :lrn, :full_name, :role, :section_id, :password, 1)
        ON DUPLICATE KEY UPDATE
            lrn = VALUES(lrn),
            full_name = VALUES(full_name),
            role = VALUES(role),
            section_id = VALUES(section_id)
    ");

    $sectionLookupStmt = $conn->prepare("
        SELECT id FROM sections
        WHERE id = :id
        LIMIT 1
    ");

    $sectionNameLookupStmt = $conn->prepare("
        SELECT id FROM sections
        WHERE LOWER(TRIM(section_name)) = LOWER(TRIM(:section_name))
        OR LOWER(TRIM(CONCAT(CAST(grade_level AS CHAR), ' - ', section_name))) = LOWER(TRIM(:section_name))
        LIMIT 1
    ");

    $importedCount = 0;
    $skippedInvalidSections = 0;

    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        if (empty($data[0]) || empty($data[1])) {
            continue;
        }

        $lrn = trim((string) $data[0]);
        $name = trim((string) $data[1]);
        $sectionValue = trim((string) ($data[2] ?? ''));
        $sectionId = null;

        if ($sectionValue !== '') {
            if (ctype_digit($sectionValue)) {
                $sectionId = (int) $sectionValue;
                $sectionLookupStmt->execute([':id' => $sectionId]);
                if ($sectionLookupStmt->fetchColumn() === false) {
                    $sectionId = null;
                }
            } else {
                $sectionNameLookupStmt->execute([':section_name' => $sectionValue]);
                $resolved = $sectionNameLookupStmt->fetchColumn();
                $sectionId = $resolved !== false ? (int) $resolved : null;
            }
        }

        if ($sectionValue !== '' && $sectionId === null) {
            $skippedInvalidSections++;
        }

        $stmt->execute([
            ':username' => $lrn,
            ':lrn' => $lrn,
            ':full_name' => $name,
            ':role' => 'student',
            ':section_id' => $sectionId,
            ':password' => password_hash($lrn, PASSWORD_BCRYPT)
        ]);

        $importedCount++;
    }

    $conn->commit();
    fclose($handle);

    $message = "Successfully imported/updated {$importedCount} student records!";
    if ($skippedInvalidSections > 0) {
        $message .= " Skipped {$skippedInvalidSections} rows with invalid section references.";
    }

    echo json_encode([
        'status' => 'success',
        'message' => $message
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    if (is_resource($handle) || $handle !== false) {
        fclose($handle);
    }

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}