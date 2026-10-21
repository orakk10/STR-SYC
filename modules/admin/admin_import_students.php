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
$normalizeValue = static function ($value) {
    return trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string) $value))));
};
$normalizeCurriculum = static function ($value) use ($normalizeValue) {
    $normalized = $normalizeValue($value);
    if (strpos($normalized, 'strengthened') !== false || strpos($normalized, 'matatag') !== false) {
        return 'strengthened';
    }
    if (strpos($normalized, 'legacy') !== false) {
        return 'legacy';
    }
    return $normalized;
};

$headerAliases = [
    'lrn' => ['lrn', 'username', 'student id'],
    'full_name' => ['full name', 'student name', 'name'],
    'section' => ['section', 'section name', 'section id'],
    'academic_year' => ['academic year', 'school year', 'sy'],
    'curriculum_version' => ['curriculum', 'curriculum version', 'curriculum framework', 'framework'],
    'grade_level' => ['grade', 'grade level'],
    'strand' => ['strand', 'strand name'],
    'strand_code' => ['strand code', 'strand abbreviation'],
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
    || !isset($columnIndexes['academic_year'], $columnIndexes['curriculum_version'])
    || !isset($columnIndexes['grade_level'], $columnIndexes['strand'])
) {
    fclose($handle);
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'The CSV must include LRN, student name, academic year, curriculum version, grade level, strand, and section columns. Profile detail columns are optional.'
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

    $sectionsStmt = $conn->query("
        SELECT sec.id, sec.section_name, sec.grade_level, sec.academic_year,
               sec.curriculum_version, st.strand_name, st.strand_code
        FROM sections sec
        LEFT JOIN strands st ON st.id = sec.strand_id
    ");
    $availableSections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);

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
        $academicYearValue = $getValue('academic_year');
        $curriculumVersionValue = $getValue('curriculum_version');
        $isUnassignedSection = $sectionValue === ''
            || strcasecmp($sectionValue, 'unassigned') === 0;
        if ($lrn === '' || $name === '') {
            $skippedMissingRequired++;
            continue;
        }
        if (
            !$isUnassignedSection
            && (
                $gradeValue === ''
                || $strandValue === ''
                || $academicYearValue === ''
                || $curriculumVersionValue === ''
            )
        ) {
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

        $sectionId = null;

        if (!$isUnassignedSection) {
            $sectionValueWithoutYear = preg_replace(
                '/^(sy\s*)?\d{4}\s*[-\/]\s*\d{4}\s*[-:|]\s*/i',
                '',
                trim($sectionValue)
            );
            $normalizedSectionValue = $normalizeValue($sectionValueWithoutYear);
            $matchingSections = array_filter($availableSections, static function ($section) use (
                $gradeValue,
                $strandValue,
                $academicYearValue,
                $curriculumVersionValue,
                $normalizedSectionValue,
                $normalizeValue,
                $normalizeCurriculum
            ) {
                $sectionGrade = preg_replace('/^grade\s*/i', '', trim((string) $section['grade_level']));
                $sectionGrade = preg_replace('/^g\s*/i', '', $sectionGrade);
                $expectedGrade = preg_replace('/^(grade|g)\s*/i', '', trim($gradeValue));
                $gradeMatches = $expectedGrade === '' || $normalizeValue($sectionGrade) === $normalizeValue($expectedGrade);
                $sectionStrand = trim((string) ($section['strand_name'] ?? ''));
                if ($sectionStrand === '') {
                    $sectionStrand = 'General / Multi-Cluster';
                }
                $strandMatches = $normalizeValue($strandValue) === $normalizeValue($sectionStrand)
                    || ($section['strand_code'] !== null && $normalizeValue($strandValue) === $normalizeValue($section['strand_code']))
                    || ($normalizeValue($strandValue) === 'general' && $normalizeValue($sectionStrand) === 'general multi cluster');
                $yearMatches = $normalizeValue($section['academic_year']) === $normalizeValue(preg_replace('/^(sy|school year)\s*/i', '', $academicYearValue));
                $curriculumMatches = $normalizeCurriculum($section['curriculum_version'])
                    === $normalizeCurriculum($curriculumVersionValue);

                $sectionName = $normalizeValue($section['section_name']);
                $sectionLabels = [
                    $sectionName,
                    $normalizeValue($sectionGrade . ' ' . $section['section_name']),
                    $normalizeValue('Grade ' . $sectionGrade . ' ' . $section['section_name']),
                    $normalizeValue('G' . $sectionGrade . ' ' . $section['section_name']),
                    $normalizeValue($sectionStrand . ' ' . $section['section_name']),
                ];
                if (!empty($section['strand_code'])) {
                    $sectionLabels[] = $normalizeValue($section['strand_code'] . ' ' . $section['section_name']);
                }
                $sectionNameMatches = ctype_digit($normalizedSectionValue)
                    ? (int) $section['id'] === (int) $normalizedSectionValue
                    : in_array($normalizedSectionValue, $sectionLabels, true);

                return $sectionNameMatches && $gradeMatches && $strandMatches && $yearMatches && $curriculumMatches;
            });

            if (count($matchingSections) === 1) {
                $matchingSection = reset($matchingSections);
                $sectionId = (int) $matchingSection['id'];
            } else {
                $skippedInvalidSections++;
                continue;
            }
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
        $message .= " Skipped {$skippedInvalidSections} rows with invalid or mismatched section, academic year, curriculum, grade level, or strand.";
    }
    if ($skippedMissingRequired > 0) {
        $message .= " Skipped {$skippedMissingRequired} rows missing LRN or student name, or missing section details for an assigned section.";
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