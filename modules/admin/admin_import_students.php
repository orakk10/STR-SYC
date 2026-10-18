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

$headers = fgetcsv($handle);
if ($headers === false || $headers === [null]) {
    fclose($handle);
    echo json_encode(['status' => 'error', 'message' => 'The uploaded CSV file is empty.']);
    exit();
}

$normalizeHeader = static function ($value) {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $value)));
};

$headerAliases = [
    'lrn' => ['lrn', 'username', 'student id'],
    'full_name' => ['full name', 'student name', 'name'],
    'section' => ['section', 'section name', 'section id'],
    'grade_level' => ['grade', 'grade level'],
    'strand' => ['strand', 'strand name'],
    'birthdate' => ['birthdate', 'birth date', 'date of birth', 'dob'],
    'gender' => ['gender', 'sex'],
    'address' => ['address'],
    'contact_no' => ['contact no', 'contact number', 'contact', 'phone', 'phone number'],
    'guardian_name' => ['guardian name', 'parent guardian name'],
    'guardian_contact' => ['guardian contact', 'guardian contact no', 'guardian contact number'],
    'specialization' => ['specialization', 'specialisation']
];
$columnIndexes = [];
foreach ($headers as $index => $header) {
    $normalizedHeader = $normalizeHeader($header);
    foreach ($headerAliases as $column => $aliases) {
        if (in_array($normalizedHeader, $aliases, true)) {
            $columnIndexes[$column] = $index;
        }
    }
}

if (
    !isset($columnIndexes['lrn'], $columnIndexes['full_name'], $columnIndexes['section'])
    || !isset($columnIndexes['grade_level'], $columnIndexes['strand'])
) {
    fclose($handle);
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'The CSV must include LRN, student name, grade level, strand, and section columns. Profile detail columns are optional.'
    ]);
    exit();
}

$conn = getDBConnection();

try {
    $genderTypeStmt = $conn->query("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'student_profiles'
          AND COLUMN_NAME = 'gender'
        LIMIT 1
    ");
    $genderColumnType = $genderTypeStmt->fetchColumn();
    if (!is_string($genderColumnType) || !preg_match("/'N\\/A'/i", $genderColumnType)) {
        throw new RuntimeException('The database must be updated to allow N/A gender values. Apply database/migrations/20261018_add_na_gender.sql and retry the import.');
    }

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

    $userIdStmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE lrn = :lrn OR username = :username
        ORDER BY (lrn = :matched_lrn) DESC
        LIMIT 1
    ");
    $profileStmt = $conn->prepare("
        INSERT INTO student_profiles
            (user_id, birthdate, gender, address, contact_no, guardian_name, guardian_contact, specialization)
        VALUES
            (:user_id, :birthdate, :gender, :address, :contact_no, :guardian_name, :guardian_contact, :specialization)
        ON DUPLICATE KEY UPDATE
            birthdate = VALUES(birthdate),
            gender = VALUES(gender),
            address = VALUES(address),
            contact_no = VALUES(contact_no),
            guardian_name = VALUES(guardian_name),
            guardian_contact = VALUES(guardian_contact),
            specialization = VALUES(specialization)
    ");

    $sectionLookupStmt = $conn->prepare("
        SELECT sec.id, sec.grade_level, st.strand_name
        FROM sections sec
        LEFT JOIN strands st ON st.id = sec.strand_id
        WHERE sec.id = :id
        LIMIT 1
    ");

    $sectionNameLookupStmt = $conn->prepare("
        SELECT sec.id, sec.grade_level, st.strand_name
        FROM sections sec
        LEFT JOIN strands st ON st.id = sec.strand_id
        WHERE LOWER(TRIM(sec.section_name)) = LOWER(TRIM(:section_name))
        OR LOWER(TRIM(CONCAT(CAST(sec.grade_level AS CHAR), ' - ', sec.section_name))) = LOWER(TRIM(:grade_section_name))
        OR LOWER(TRIM(CONCAT(st.strand_name, ' - ', sec.section_name))) = LOWER(TRIM(:strand_section_name))
    ");

    $importedCount = 0;
    $skippedInvalidSections = 0;
    $skippedMissingRequired = 0;
    $defaultedOptionalDetails = 0;

    while (($data = fgetcsv($handle)) !== false) {
        if ($data === [null]) {
            continue;
        }

        $getValue = static function ($column) use ($columnIndexes, $data) {
            return isset($columnIndexes[$column])
                ? trim((string) ($data[$columnIndexes[$column]] ?? ''))
                : '';
        };

        $lrn = $getValue('lrn');
        $name = $getValue('full_name');
        $sectionValue = $getValue('section');
        $gradeValue = $getValue('grade_level');
        $strandValue = $getValue('strand');
        if ($lrn === '' || $name === '' || $gradeValue === '' || $strandValue === '') {
            $skippedMissingRequired++;
            continue;
        }

        $birthdate = $getValue('birthdate');
        $defaultOptionalDetails = false;
        if ($birthdate === '' || strcasecmp($birthdate, 'N/A') === 0) {
            $birthdate = null;
        } else {
            $parsedBirthdate = false;
            foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y'] as $dateFormat) {
                $candidateDate = DateTime::createFromFormat($dateFormat, $birthdate);
                $dateErrors = DateTime::getLastErrors();
                if (
                    $candidateDate !== false
                    && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
                ) {
                    $parsedBirthdate = $candidateDate;
                    break;
                }
            }

            if ($parsedBirthdate === false) {
                $birthdate = null;
                $defaultOptionalDetails = true;
            } else {
                $birthdate = $parsedBirthdate->format('Y-m-d');
            }
        }

        $gender = $getValue('gender');
        if ($gender === '' || strcasecmp($gender, 'N/A') === 0 || strcasecmp($gender, 'NA') === 0) {
            $gender = 'N/A';
            $defaultOptionalDetails = true;
        } elseif (strcasecmp($gender, 'male') === 0) {
            $gender = 'Male';
        } elseif (strcasecmp($gender, 'female') === 0) {
            $gender = 'Female';
        } else {
            $gender = 'N/A';
            $defaultOptionalDetails = true;
        }

        if ($defaultOptionalDetails) {
            $defaultedOptionalDetails++;
        }

        $profileValue = static function ($column) use ($getValue) {
            $value = $getValue($column);
            return $value === '' ? 'N/A' : $value;
        };

        if (strcasecmp($sectionValue, 'unassigned') === 0) {
            $sectionValue = '';
        }
        $sectionId = null;

        if ($sectionValue !== '') {
            if (ctype_digit($sectionValue)) {
                $sectionLookupStmt->execute([':id' => (int) $sectionValue]);
                $sections = $sectionLookupStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sectionNameLookupStmt->execute([
                    ':section_name' => $sectionValue,
                    ':grade_section_name' => $sectionValue,
                    ':strand_section_name' => $sectionValue
                ]);
                $sections = $sectionNameLookupStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $matchingSections = array_filter($sections, static function ($section) use ($gradeValue, $strandValue) {
                $sectionGrade = preg_replace('/^grade\s*/i', '', trim((string) $section['grade_level']));
                $expectedGrade = preg_replace('/^grade\s*/i', '', $gradeValue);
                $gradeMatches = $gradeValue === '' || strcasecmp($sectionGrade, $expectedGrade) === 0;
                $strandMatches = $strandValue === ''
                    || strcasecmp(trim((string) $section['strand_name']), $strandValue) === 0;

                return $gradeMatches && $strandMatches;
            });

            if (count($matchingSections) === 1) {
                $matchingSection = reset($matchingSections);
                $sectionId = (int) $matchingSection['id'];
            } else {
                $skippedInvalidSections++;
                continue;
            }
        } elseif ($gradeValue !== '' || $strandValue !== '') {
            // Grade and strand cannot be validated without an assigned section.
            $skippedInvalidSections++;
            continue;
        }

        $stmt->execute([
            ':username' => $lrn,
            ':lrn' => $lrn,
            ':full_name' => $name,
            ':role' => 'student',
            ':section_id' => $sectionId,
            ':password' => password_hash($lrn, PASSWORD_BCRYPT)
        ]);

        $userIdStmt->execute([
            ':lrn' => $lrn,
            ':username' => $lrn,
            ':matched_lrn' => $lrn
        ]);
        $userId = $userIdStmt->fetchColumn();
        if (!$userId) {
            throw new RuntimeException("Unable to find imported student account for LRN {$lrn}.");
        }

        $profileStmt->execute([
            ':user_id' => (int) $userId,
            ':birthdate' => $birthdate,
            ':gender' => $gender,
            ':address' => $profileValue('address'),
            ':contact_no' => $profileValue('contact_no'),
            ':guardian_name' => $profileValue('guardian_name'),
            ':guardian_contact' => $profileValue('guardian_contact'),
            ':specialization' => $profileValue('specialization')
        ]);

        $importedCount++;
    }

    $conn->commit();
    fclose($handle);

    $message = "Successfully imported/updated {$importedCount} student records!";
    if ($skippedInvalidSections > 0) {
        $message .= " Skipped {$skippedInvalidSections} rows with invalid or mismatched section, grade level, or strand.";
    }
    if ($skippedMissingRequired > 0) {
        $message .= " Skipped {$skippedMissingRequired} rows missing LRN, student name, grade level, or strand.";
    }
    if ($defaultedOptionalDetails > 0) {
        $message .= " Imported {$defaultedOptionalDetails} rows with missing or invalid optional details using N/A defaults.";
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