<?php
session_start();

// 1. Database Connection Setup (PDO)
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

// 2. Fetch Roster matching active students (PDO)
$roster = [];
$roster_sql = "
    SELECT u.id, u.full_name, COALESCE(NULLIF(u.lrn, ''), u.username) AS lrn 
    FROM users u
    WHERE u.role = 'student'
    ORDER BY u.full_name ASC
";
$roster_stmt = $conn->query($roster_sql);
if ($roster_stmt) {
    $roster = $roster_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Resolve Active Student ID
if (isset($_GET['student_id']) && intval($_GET['student_id']) > 0) {
    $student_id = intval($_GET['student_id']);
} elseif (!empty($roster)) {
    $student_id = intval($roster[0]['id']);
} else {
    $student_id = 0;
}

// 4. Fetch Available Academic Years dynamically
$available_years = [];
$sy_sql = "
    SELECT DISTINCT academic_year FROM (
        SELECT academic_year FROM subject_assignments WHERE academic_year IS NOT NULL AND academic_year != ''
        UNION
        SELECT academic_year FROM sections WHERE academic_year IS NOT NULL AND academic_year != ''
    ) AS combined_sy
    ORDER BY academic_year DESC
";
$sy_stmt = $conn->query($sy_sql);
if ($sy_stmt) {
    $available_years = $sy_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fallback school years if no entries exist yet
if (empty($available_years)) {
    $available_years[] = '2026-2027';
    $available_years[] = '2025-2026';
}

// Resolve Active Academic Year
$selected_sy = isset($_GET['academic_year']) && in_array($_GET['academic_year'], $available_years) 
    ? $_GET['academic_year'] 
    : $available_years[0];

$db_student = null;
$core_subjects = [];
$elective_subjects = [];
$adviser_comments = [
    'Term 1' => '',
    'Term 2' => '',
    'Term 3' => ''
];

if ($student_id > 0) {
    // A. Fetch Base Student Profile
    $stmt = $conn->prepare("
        SELECT 
            u.id AS user_id, u.full_name, COALESCE(NULLIF(u.lrn, ''), u.username) AS lrn, u.section_id AS current_section_id,
            sp.birthdate, sp.gender
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.id = :student_id
        LIMIT 1
    ");
    $stmt->execute(['student_id' => $student_id]);
    $db_student = $stmt->fetch(PDO::FETCH_ASSOC);

    // B. Fetch Section, Grade Level & Adviser Name
    $historical_section = null;
    
    // Check historical summaries first
    $sec_stmt = $conn->prepare("
        SELECT DISTINCT s.grade_level, s.section_name, st.strand_code, st.strand_name, adv.full_name AS adviser_name
        FROM subject_assignments sa
        INNER JOIN ecr_term_summaries ets ON ets.assignment_id = sa.id
        INNER JOIN sections s ON sa.section_id = s.id
        LEFT JOIN strands st ON s.strand_id = st.id
        LEFT JOIN users adv ON s.adviser_id = adv.id
        WHERE ets.student_id = :student_id AND sa.academic_year = :sy
        LIMIT 1
    ");
    $sec_stmt->execute(['student_id' => $student_id, 'sy' => $selected_sy]);
    $historical_section = $sec_stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback to student's current assigned section if no historical grades exist yet
    if (!$historical_section && !empty($db_student['current_section_id'])) {
        $curr_sec_stmt = $conn->prepare("
            SELECT s.grade_level, s.section_name, st.strand_code, st.strand_name, adv.full_name AS adviser_name
            FROM sections s
            LEFT JOIN strands st ON s.strand_id = st.id
            LEFT JOIN users adv ON s.adviser_id = adv.id
            WHERE s.id = :sec_id
            LIMIT 1
        ");
        $curr_sec_stmt->execute(['sec_id' => $db_student['current_section_id']]);
        $historical_section = $curr_sec_stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($historical_section) {
        $db_student['grade_level']  = $historical_section['grade_level'];
        $db_student['section_name'] = $historical_section['section_name'];
        $db_student['strand_code']  = $historical_section['strand_code'];
        $db_student['strand_name']  = $historical_section['strand_name'];
        $db_student['adviser_name']  = $historical_section['adviser_name'] ?? '';
    }

    // C. Fetch Dynamic Subjects and Transmuted Grades (Grouped to prevent duplicates)
    $grades_query = "
        SELECT 
            sub.id AS subject_id,
            sub.subject_name AS name,
            sub.type AS subject_type,
            COALESCE(sub.units, 3) AS units,
            MAX(ets.term1_transmuted) AS t1,
            MAX(ets.term2_transmuted) AS t2,
            MAX(ets.term3_transmuted) AS t3,
            MAX(ets.final_grade) AS final_grade,
            MAX(ets.remarks) AS remarks
        FROM users u
        INNER JOIN subject_assignments sa ON sa.section_id = u.section_id
        INNER JOIN subjects sub ON sa.subject_id = sub.id
        LEFT JOIN ecr_term_summaries ets ON (ets.assignment_id = sa.id AND ets.student_id = u.id)
        WHERE u.id = :student_id AND sa.academic_year = :sy
        GROUP BY sub.id, sub.subject_name, sub.type, sub.units
        ORDER BY sub.type ASC, sub.subject_name ASC
    ";

    $stmt_grades = $conn->prepare($grades_query);
    $stmt_grades->execute(['student_id' => $student_id, 'sy' => $selected_sy]);
    $res_grades = $stmt_grades->fetchAll(PDO::FETCH_ASSOC);

    foreach ($res_grades as $row) {
        $item = [
            'name'        => $row['name'],
            't1'          => $row['t1'] !== null ? round(floatval($row['t1'])) : null,
            't2'          => $row['t2'] !== null ? round(floatval($row['t2'])) : null,
            't3'          => $row['t3'] !== null ? round(floatval($row['t3'])) : null,
            'units'       => intval($row['units']),
            'final_grade' => $row['final_grade'] !== null ? round(floatval($row['final_grade'])) : null,
            'remarks'     => $row['remarks'] ?? '',
            'dis_t1'      => false,
            'dis_t2'      => false,
            'dis_t3'      => false
        ];

        if (strtolower($row['subject_type']) === 'core') {
            $core_subjects[] = $item;
        } else {
            $elective_subjects[] = $item;
        }
    }

    // D. Fetch Dynamic Teacher's Comments / Remarks from Database
    $stmt_remarks = $conn->prepare("
        SELECT term, comment 
        FROM sf9_adviser_remarks 
        WHERE student_id = :student_id AND academic_year = :sy
    ");
    $stmt_remarks->execute(['student_id' => $student_id, 'sy' => $selected_sy]);
    $remarks_rows = $stmt_remarks->fetchAll(PDO::FETCH_ASSOC);

    foreach ($remarks_rows as $row) {
        if (array_key_exists($row['term'], $adviser_comments)) {
            $adviser_comments[$row['term']] = $row['comment'];
        }
    }
}

// Dynamic Age Calculation from birthdate
$calculated_age = 'N/A';
if (!empty($db_student['birthdate']) && $db_student['birthdate'] !== '0000-00-00') {
    try {
        $dob = new DateTime($db_student['birthdate']);
        $now = new DateTime();
        $calculated_age = $now->diff($dob)->y;
    } catch (Exception $e) {
        $calculated_age = 'N/A';
    }
}

// Dynamic Track / Strand display
$track_display = 'Academic Track';
if (!empty($db_student['strand_code']) || !empty($db_student['strand_name'])) {
    $track_display = 'Academic / ' . ($db_student['strand_code'] ?: $db_student['strand_name']);
}

$student = [
    'full_name' => !empty($db_student['full_name']) ? strtoupper($db_student['full_name']) : 'NO STUDENT SELECTED',
    'lrn'       => !empty($db_student['lrn']) ? $db_student['lrn'] : 'N/A',
    'age'       => $calculated_age,
    'gender'    => $db_student['gender'] ?? 'N/A',
    'grade'     => $db_student['grade_level'] ?? 'N/A',
    'section'   => $db_student['section_name'] ?? 'N/A',
    'track'     => $track_display,
    'region'    => 'REGION III',
    'division'  => 'SCHOOLS DIVISION OF TARLAC',
    'district'  => 'District I',
    'school'    => 'SAN JUAN DE VALDEZ INTEGRATED SCHOOL'
];

$adviser_display = !empty($db_student['adviser_name']) ? strtoupper($db_student['adviser_name']) : 'JUAN DELA CRUZ';

// Helper Function for Final Grade & Remark
function calcFinalGrade($subj) {
    if (isset($subj['final_grade']) && $subj['final_grade'] !== null) {
        return ['final' => $subj['final_grade'], 'remark' => $subj['remarks'] ?: ($subj['final_grade'] >= 75 ? 'Passed' : 'Failed')];
    }
    $scores = array_filter([$subj['t1'] ?? null, $subj['t2'] ?? null, $subj['t3'] ?? null], fn($v) => $v !== null && $v !== '');
    if (empty($scores)) return ['final' => '', 'remark' => ''];
    $final = round(array_sum($scores) / count($scores));
    return ['final' => $final, 'remark' => $final >= 75 ? 'Passed' : 'Failed'];
}

// Calculate General Averages
$all_subjs = array_merge($core_subjects, $elective_subjects);
$t1_all = array_filter(array_column($all_subjs, 't1'), fn($v) => $v !== null && $v !== '');
$t2_all = array_filter(array_column($all_subjs, 't2'), fn($v) => $v !== null && $v !== '');
$t3_all = array_filter(array_column($all_subjs, 't3'), fn($v) => $v !== null && $v !== '');

$gen_t1 = !empty($t1_all) ? round(array_sum($t1_all) / count($t1_all)) : '';
$gen_t2 = !empty($t2_all) ? round(array_sum($t2_all) / count($t2_all)) : '';
$gen_t3 = !empty($t3_all) ? round(array_sum($t3_all) / count($t3_all)) : '';

$valid_gen = array_filter([$gen_t1, $gen_t2, $gen_t3], fn($v) => $v !== '');
$final_gen_avg = !empty($valid_gen) ? round(array_sum($valid_gen) / count($valid_gen)) : '';
$total_units = array_sum(array_column($all_subjs, 'units'));

// Attendance Structure
$school_days  = ['Jun' => 20, 'Jul' => 20, 'Aug' => 20, 'Sep' => 20, 'Oct' => 20, 'Nov' => 20, 'Dec' => 20, 'Jan' => 18, 'Feb' => 18, 'Mar' => 20, 'Apr' => 5];
$days_present = ['Jun' => 20, 'Jul' => 19, 'Aug' => 20, 'Sep' => 18, 'Oct' => 20, 'Nov' => 20, 'Dec' => 19, 'Jan' => 18, 'Feb' => 18, 'Mar' => 20, 'Apr' => 5];
$days_absent  = [];
foreach ($school_days as $m => $d) {
    $days_absent[$m] = $d - $days_present[$m];
}

$tot_days = array_sum($school_days);
$tot_pres = array_sum($days_present);
$tot_abs  = array_sum($days_absent);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SF9 Learner's Performance Report Card | San Juan De Valdez IS</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        *, *::before, *::after {
            box-sizing: border-box !important;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #0f172a;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }

        main.content {
            flex-grow: 1;
            margin-left: 260px;
            padding: 16px 20px;
            width: calc(100% - 260px);
            min-height: 100vh;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        #sidebar:not(.active) ~ main.content {
            margin-left: 0;
            width: 100%;
        }

        .sf9-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .mobile-burger-btn {
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 1.1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .btn-print {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.82rem;
            transition: background 0.2s ease;
        }

        .btn-print:hover {
            background-color: #1d4ed8;
        }

        .btn-remarks {
            background-color: #0d9488;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.82rem;
            margin-right: 8px;
            transition: background 0.2s ease;
        }

        .btn-remarks:hover {
            background-color: #0f766e;
        }

        .report-document-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
            container-type: inline-size;
            padding-bottom: 20px;
        }

        .report-document {
            display: flex;
            flex-direction: row;
            gap: 1%;
            background: #ffffff;
            padding: 1.5%;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            width: 100%;
            box-sizing: border-box;
            font-family: 'Times New Roman', Times, serif;
            color: #000000;
            font-size: clamp(8px, 0.95cqw, 14px);
        }

        .doc-page {
            flex: 0 0 49.5%;
            width: 49.5%;
            border: 1px solid #000000;
            padding: 1.5%;
            box-sizing: border-box;
            background: #ffffff;
            position: relative;
        }

        .header-section {
            position: relative;
            text-align: center;
            margin-bottom: 4px;
        }

        .vector-deped-seal {
            position: absolute;
            left: 0;
            top: 0;
            width: 2.8em;
            height: 2.8em;
        }

        .school-logo-box {
            position: absolute;
            right: 0;
            top: 0;
            width: 2.8em;
            height: 2.8em;
            border: 1px dashed #000000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            text-align: center;
            font-family: sans-serif;
        }

        .header-section h5, .header-section h4, .header-section h3 {
            margin: 1px 0;
            font-weight: normal;
            font-size: 0.9em;
        }

        .header-section h3 {
            font-weight: bold;
            font-size: 1.1em;
        }

        .line-field {
            border-bottom: 1px solid #000000;
            display: inline-block;
            padding: 0 2px;
        }

        .student-info-block {
            margin-bottom: 6px;
            font-size: 0.9em;
            line-height: 1.3;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .message-block {
            font-size: 0.85em;
            margin-bottom: 6px;
            text-align: justify;
            line-height: 1.25;
        }

        .message-block p {
            margin: 2px 0;
            text-indent: 12px;
        }

        .header-signatures {
            display: flex;
            justify-content: space-between;
            margin: 8px 0 6px 0;
            font-size: 0.9em;
            text-align: center;
        }

        .header-signatures .sig-col {
            width: 42%;
            padding-top: 2px;
        }

        .section-heading {
            text-align: center;
            font-weight: bold;
            font-size: 0.95em;
            margin: 6px 0 4px 0;
            text-transform: uppercase;
        }

        table.doc-table {
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
            font-size: 0.8em;
            margin-bottom: 4px;
            box-sizing: border-box;
        }

        table.doc-table th, table.doc-table td {
            border: 1px solid #000000;
            padding: 0.25em 1px;
            text-align: center;
            word-wrap: break-word;
            overflow-wrap: break-word;
            box-sizing: border-box;
        }

        table.doc-table td.text-left {
            text-align: left;
            padding-left: 3px;
        }

        table.attendance-table th, 
        table.attendance-table td {
            padding: 0.2em 0px !important;
            font-size: 0.72em !important;
            text-align: center !important;
            white-space: nowrap !important;
        }

        table.attendance-table td.text-left {
            text-align: left !important;
            padding-left: 3px !important;
            white-space: normal !important;
        }

        .cell-disabled {
            background-color: #cbd5e1;
        }

        .descriptors-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            font-size: 0.8em;
            margin-top: 2px;
        }

        .descriptors-table th, .descriptors-table td {
            border: none;
            padding: 1px;
        }

        .comment-area {
            border: 1px solid #000000;
            min-height: 2.2em;
            margin-bottom: 4px;
            padding: 2px 4px;
            font-size: 0.82em;
        }

        .signature-row {
            margin-bottom: 4px;
            font-size: 0.85em;
        }

        .transfer-cert-body {
            font-size: 0.85em;
            line-height: 1.3;
            margin-top: 6px;
        }

        .footer-signatures {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 8px;
        }

        .footer-signatures .field {
            text-align: center;
            width: 45%;
            font-size: 0.85em;
        }

        .footer-signatures .field .line {
            border-top: 1px solid #000000;
            margin-top: 1.5em;
            padding-top: 2px;
        }

        /* Optimized Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(6px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            transition: opacity 0.2s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 16px;
            width: 90%;
            max-width: 540px;
            padding: 24px 28px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            animation: modalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(12px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 1px solid #f1f5f9;
        }

        .modal-header h4 {
            margin: 0;
            font-size: 1.05rem;
            color: #0f172a;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .modal-header .student-name-highlight {
            color: #2563eb;
        }

        .modal-close {
            background: #f1f5f9;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group .term-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #3b82f6;
            background: #eff6ff;
            padding: 3px 8px;
            border-radius: 6px;
            margin-bottom: 6px;
        }

        .form-group textarea {
            width: 100%;
            min-height: 64px;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 0.85rem;
            color: #1e293b;
            resize: vertical;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 22px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 9px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .btn-save {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 9px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
            transition: all 0.2s ease;
        }

        .btn-save:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
        }

        @media screen and (max-width: 600px) {
            .report-document {
                flex-direction: column !important;
            }
            .doc-page {
                width: 100% !important;
                flex: 1 1 100% !important;
            }
        }

        @media print {
            @page {
                size: A4 landscape !important;
                margin: 4mm !important;
            }

            html, body {
                width: 100% !important;
                height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                overflow: hidden !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .sidebar, .mobile-toggle, .sf9-toolbar, .no-print, .modal-overlay {
                display: none !important;
            }

            main.content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
            }

            .report-document-wrapper {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                display: block !important;
            }

            .report-document {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                width: 100% !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                gap: 4mm !important;
                box-sizing: border-box !important;
                font-size: 0.60rem !important;
                zoom: 0.94;
            }

            .doc-page {
                width: calc(50% - 2mm) !important;
                max-width: calc(50% - 2mm) !important;
                flex: 0 0 calc(50% - 2mm) !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                padding: 5px 7px !important;
                overflow: hidden !important;
                border: 1px solid #000000 !important;
            }

            table.doc-table {
                font-size: 0.52rem !important;
            }

            table.attendance-table th, 
            table.attendance-table td {
                font-size: 0.48rem !important;
            }

            .student-info-block, 
            .message-block, 
            .transfer-cert-body, 
            .signature-row {
                font-size: 0.56rem !important;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3>STRAND-SYNC</h3>
                <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
            </div>
            <ul class="menu">
                <li><a href="faculty_dashboard.php">Dashboard</a></li>
                <li><a href="faculty_schedule.php">My Schedule</a></li>
                <li><a href="ecr-inputdata.php">ECR Setup &amp; Roster</a></li>
                <li><a href="ecr-view.php">Encode Term Grades</a></li>
                <li><a href="ecr-summary.php">ECR Summary</a></li>
                <li class="menu-divider" style="padding: 12px 16px 4px 16px; font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;">
                    Advisory Forms
                </li>
                <li><a href="sf9-reportcard.php" class="active">SF9 (Report Card)</a></li>
                <li><a href="sf10-record.php">SF10 (Permanent Record)</a></li>
                <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>

        <main class="content">
            <!-- Screen Header Control Bar -->
            <div class="sf9-toolbar no-print">
                <div class="toolbar-title" style="display: flex; align-items: center; gap: 12px;">
                    <button class="mobile-burger-btn" onclick="toggleSidebar()" title="Toggle Sidebar">☰</button>
                    
                    <form method="GET" action="sf9-reportcard.php" id="studentSelectForm" style="margin: 0; display: flex; align-items: center; gap: 12px;">
                        <!-- Dynamic Student Selector Dropdown -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label for="student_id" style="font-size: 0.85rem; font-weight: 600; color: #334155;">Learner:</label>
                            <select name="student_id" id="student_id" onchange="document.getElementById('studentSelectForm').submit();" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; background: #ffffff; cursor: pointer; font-weight: 500;">
                                <?php if (!empty($roster)): ?>
                                    <?php foreach ($roster as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= intval($s['id']) === $student_id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(strtoupper($s['full_name'])) ?> (LRN: <?= htmlspecialchars($s['lrn'] ?: 'N/A') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">NO STUDENTS FOUND</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Dynamic School Year Selector Dropdown -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label for="academic_year" style="font-size: 0.85rem; font-weight: 600; color: #334155;">School Year:</label>
                            <select name="academic_year" id="academic_year" onchange="document.getElementById('studentSelectForm').submit();" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; background: #ffffff; cursor: pointer; font-weight: 500;">
                                <?php if (!empty($available_years)): ?>
                                    <?php foreach ($available_years as $sy): ?>
                                        <option value="<?= $sy ?>" <?= $sy === $selected_sy ? 'selected' : '' ?>>
                                            SY <?= htmlspecialchars($sy) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">N/A</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </form>
                </div>
                
                <div>
                    <button class="btn-remarks" onclick="openRemarksModal()">Edit Adviser Remarks</button>
                    <button class="btn-print" onclick="window.print()">Print Official SF9 Document</button>
                </div>
            </div>

            <!-- DOCUMENT DISPLAY CONTAINER -->
            <div class="report-document-wrapper">
                <div class="report-document">
                    
                    <!-- PAGE 1: Academic Performance -->
                    <div class="doc-page">
                        <div class="header-section">
                            <svg class="vector-deped-seal" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="46" fill="none" stroke="#003366" stroke-width="4"/>
                                <circle cx="50" cy="50" r="38" fill="none" stroke="#C5A059" stroke-width="2"/>
                                <text x="50" y="30" font-size="9" text-anchor="middle" font-weight="bold" fill="#003366">KAGAWARAN NG EDUKASYON</text>
                                <polygon points="50,38 54,48 64,48 56,54 59,64 50,58 41,64 44,54 36,48 46,48" fill="#C5A059"/>
                                <text x="50" y="78" font-size="8" text-anchor="middle" font-weight="bold" fill="#003366">REPUBLIKA NG PILIPINAS</text>
                            </svg>

                            <div class="school-logo-box">School<br>Logo</div>
                            <h5>Republic of the Philippines</h5>
                            <h5>Department of Education</h5>
                            <h5><?= htmlspecialchars($student['region']) ?></h5>
                            <h3><?= htmlspecialchars($student['division']) ?></h3>
                            <h5><?= htmlspecialchars($student['district']) ?></h5>
                            <h4 style="margin-top: 2px; font-weight: bold;"><?= htmlspecialchars($student['school']) ?></h4>
                        </div>

                        <div class="section-heading" style="margin-top: 2px;">LEARNER'S PERFORMANCE REPORT</div>
                        <div style="text-align: center; font-size: 0.85em; margin-bottom: 4px;">School Year <?= htmlspecialchars($selected_sy ?: 'N/A') ?></div>

                        <div class="student-info-block">
                            <div class="info-row">
                                <span>Name: <span class="line-field" style="min-width: 90px;"><?= htmlspecialchars($student['full_name']) ?></span></span>
                                <span>Age: <span class="line-field" style="min-width: 15px;"><?= htmlspecialchars($student['age']) ?></span></span>
                                <span>Sex: <span class="line-field" style="min-width: 20px;"><?= htmlspecialchars($student['gender']) ?></span></span>
                            </div>
                            <div class="info-row">
                                <span>LRN: <span class="line-field" style="min-width: 80px;"><?= htmlspecialchars($student['lrn']) ?></span></span>
                                <span>Grade: <span class="line-field" style="min-width: 15px;"><?= htmlspecialchars($student['grade']) ?></span></span>
                                <span>Section: <span class="line-field" style="min-width: 30px;"><?= htmlspecialchars($student['section']) ?></span></span>
                            </div>
                            <div>
                                Track (SHS only) <span class="line-field" style="min-width: 100px;"><?= htmlspecialchars($student['track']) ?></span>
                            </div>
                        </div>

                        <div class="message-block">
                            Dear Parents,
                            <p>This Performance Report presents your child's progress and achievement in the different learning areas.</p>
                            <p>The school welcomes you to reach out should you wish to know more about your child's learning and performance.</p>
                        </div>

                        <div class="header-signatures">
                            <div class="sig-col">
                                <div style="border-bottom: 1px solid #000; font-weight: bold; margin-bottom: 2px;">SCHOOL HEAD NAME</div>
                                <div>School Head</div>
                            </div>
                            <div class="sig-col">
                                <div style="border-bottom: 1px solid #000; font-weight: bold; margin-bottom: 2px;"><?= htmlspecialchars($adviser_display) ?></div>
                                <div>Class Adviser</div>
                            </div>
                        </div>

                        <div class="section-heading">LEARNING PROGRESS AND ACHIEVEMENT</div>

                        <table class="doc-table">
                            <colgroup>
                                <col style="width: 36%;">
                                <col style="width: 5%;">
                                <col style="width: 5%;">
                                <col style="width: 5%;">
                                <col style="width: 8%;">
                                <col style="width: 18%;">
                                <col style="width: 23%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th rowspan="2" class="text-left">LEARNING AREAS</th>
                                    <th colspan="3">TERM</th>
                                    <th rowspan="2">UNITS</th>
                                    <th rowspan="2">FINAL GRADE</th>
                                    <th rowspan="2">REMARKS</th>
                                </tr>
                                <tr>
                                    <th>T1</th>
                                    <th>T2</th>
                                    <th>T3</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-left" style="font-weight: bold; font-style: italic; background: #f8fafc;">Core Subjects</td>
                                </tr>
                                <?php if (!empty($core_subjects)): ?>
                                    <?php foreach ($core_subjects as $subj): ?>
                                        <?php $res = calcFinalGrade($subj); ?>
                                        <tr>
                                            <td class="text-left"><?= htmlspecialchars($subj['name']) ?></td>
                                            <td><?= $subj['t1'] ?? '' ?></td>
                                            <td><?= $subj['t2'] ?? '' ?></td>
                                            <td><?= $subj['t3'] ?? '' ?></td>
                                            <td><strong><?= $subj['units'] ?></strong></td>
                                            <td><strong><?= $res['final'] ?></strong></td>
                                            <td><?= $res['remark'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="font-style: italic; color: #64748b;">No core subjects found for this academic year.</td>
                                    </tr>
                                <?php endif; ?>

                                <tr>
                                    <td colspan="7" class="text-left" style="font-weight: bold; font-style: italic; background: #f8fafc;">Elective Subjects</td>
                                </tr>
                                <?php if (!empty($elective_subjects)): ?>
                                    <?php foreach ($elective_subjects as $subj): ?>
                                        <?php $res = calcFinalGrade($subj); ?>
                                        <tr>
                                            <td class="text-left"><?= htmlspecialchars($subj['name']) ?></td>
                                            <td class="<?= !empty($subj['dis_t1']) ? 'cell-disabled' : '' ?>"><?= $subj['t1'] ?? '' ?></td>
                                            <td class="<?= !empty($subj['dis_t2']) ? 'cell-disabled' : '' ?>"><?= $subj['t2'] ?? '' ?></td>
                                            <td class="<?= !empty($subj['dis_t3']) ? 'cell-disabled' : '' ?>"><?= $subj['t3'] ?? '' ?></td>
                                            <td><strong><?= $subj['units'] ?></strong></td>
                                            <td><strong><?= $res['final'] ?></strong></td>
                                            <td><?= $res['remark'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="font-style: italic; color: #64748b;">No elective subjects found for this academic year.</td>
                                    </tr>
                                <?php endif; ?>

                                <tr style="font-weight: bold; background: #f1f5f9;">
                                    <td class="text-left" style="font-style: italic;">General Average</td>
                                    <td><?= $gen_t1 ?></td>
                                    <td><?= $gen_t2 ?></td>
                                    <td><?= $gen_t3 ?></td>
                                    <td><?= $total_units ?></td>
                                    <td><?= $final_gen_avg ?></td>
                                    <td><?= !empty($final_gen_avg) ? ($final_gen_avg >= 75 ? 'Passed' : 'Failed') : '' ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="section-heading" style="font-size: 0.85em; margin-top: 4px;">PERFORMANCE DESCRIPTOR</div>
                        <table class="descriptors-table">
                            <thead>
                                <tr style="font-weight: bold;">
                                    <td style="width: 33%;">Grading Scale</td>
                                    <td style="width: 33%;">Descriptors</td>
                                    <td style="width: 34%;">Remarks</td>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>90-100</td><td>Advancing</td><td>Passed</td></tr>
                                <tr><td>80-89</td><td>Benchmarking</td><td>Passed</td></tr>
                                <tr><td>75-79</td><td>Connecting</td><td>Passed</td></tr>
                                <tr><td>65-74</td><td>Developing</td><td>Failed</td></tr>
                                <tr><td>0-64</td><td>Emerging</td><td>Failed</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGE 2: Attendance, Comments, Transfer Certificate -->
                    <div class="doc-page">
                        <div class="section-heading">ATTENDANCE RECORD</div>
                        <table class="doc-table attendance-table">
                            <colgroup>
                                <col style="width: 20%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 5.7%;">
                                <col style="width: 11.3%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>MONTH</th>
                                    <th>JUN</th><th>JUL</th><th>AUG</th><th>SEP</th><th>OCT</th><th>NOV</th><th>DEC</th><th>JAN</th><th>FEB</th><th>MAR</th><th>APR</th><th>TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-left" style="font-weight: bold;">No. of Class Days</td>
                                    <?php foreach ($school_days as $m => $days): ?>
                                        <td><?= $days ?></td>
                                    <?php endforeach; ?>
                                    <td><strong><?= $tot_days ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-left" style="font-weight: bold;">No. of Days Present</td>
                                    <?php foreach ($days_present as $m => $pres): ?>
                                        <td><?= $pres ?></td>
                                    <?php endforeach; ?>
                                    <td><strong><?= $tot_pres ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-left" style="font-weight: bold;">No. of Days Absent</td>
                                    <?php foreach ($days_absent as $m => $abs): ?>
                                        <td><?= $abs ?></td>
                                    <?php endforeach; ?>
                                    <td><strong><?= $tot_abs ?></strong></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="section-heading">TEACHER'S COMMENTS / REMARKS</div>
                        <div style="font-size: 0.82em; font-weight: bold; margin-bottom: 1px;">Term 1</div>
                        <div class="comment-area"><?= htmlspecialchars($adviser_comments['Term 1'] ?? '') ?></div>

                        <div style="font-size: 0.82em; font-weight: bold; margin-bottom: 1px;">Term 2</div>
                        <div class="comment-area"><?= htmlspecialchars($adviser_comments['Term 2'] ?? '') ?></div>

                        <div style="font-size: 0.82em; font-weight: bold; margin-bottom: 1px;">Term 3</div>
                        <div class="comment-area"><?= htmlspecialchars($adviser_comments['Term 3'] ?? '') ?></div>

                        <div class="section-heading" style="margin-top: 6px;">PARENT/S GUARDIAN'S SIGNATURE</div>
                        <div class="signature-row">Term 1 <span class="line-field" style="width: 80%;"></span></div>
                        <div class="signature-row">Term 2 <span class="line-field" style="width: 80%;"></span></div>
                        <div class="signature-row">Term 3 <span class="line-field" style="width: 80%;"></span></div>

                        <div class="section-heading" style="margin-top: 6px;">CERTIFICATE OF TRANSFER</div>
                        <div class="transfer-cert-body">
                            This is to certify that the above-named learner has satisfactorily completed the requirements for the grade level indicated.<br><br>
                            Admitted to Grade: <span class="line-field" style="min-width: 80px;">Grade 12</span><br>
                            Eligible for Admission to Grade: <span class="line-field" style="min-width: 80px;">Grade 12</span>
                        </div>

                        <div class="footer-signatures">
                            <div class="field">
                                <div class="line" style="font-weight: bold;">SCHOOL HEAD NAME</div>
                                <div>School Head</div>
                            </div>
                            <div class="field">
                                <div class="line" style="font-weight: bold;"><?= htmlspecialchars($adviser_display) ?></div>
                                <div>Class Adviser</div>
                            </div>
                        </div>

                        <div class="section-heading" style="margin-top: 6px; font-size: 0.85em;">CANCELLATION OF ELIGIBILITY TO TRANSFER</div>
                        <div style="font-size: 0.85em; margin-top: 4px;">
                            Admitted in: <span class="line-field" style="min-width: 80px;"></span> 
                            <span style="margin-left: 8px;">Date: <span class="line-field" style="min-width: 50px;"></span></span>
                        </div>
                        <div class="footer-signatures" style="margin-top: 4px;">
                            <div class="field">
                                <div class="line" style="font-weight: bold;">SCHOOL HEAD NAME</div>
                                <div>School Head</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- MODAL EDIT ADVISER REMARKS -->
    <div class="modal-overlay" id="remarksModal">
        <div class="modal-box">
            <div class="modal-header">
                <h4>Adviser Remarks: <span class="student-name-highlight"><?= htmlspecialchars($student['full_name']) ?></span></h4>
                <button class="modal-close" onclick="closeRemarksModal()" title="Close">✕</button>
            </div>
            <form id="saveRemarksForm" onsubmit="submitRemarksForm(event)">
                <input type="hidden" name="student_id" value="<?= $student_id ?>">
                <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_sy) ?>">
                
                <div class="form-group">
                    <span class="term-badge">Term 1 Remark / Comment</span>
                    <textarea name="term1_comment" id="term1_comment" placeholder="Enter Term 1 remark..."><?= htmlspecialchars($adviser_comments['Term 1'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <span class="term-badge">Term 2 Remark / Comment</span>
                    <textarea name="term2_comment" id="term2_comment" placeholder="Enter Term 2 remark..."><?= htmlspecialchars($adviser_comments['Term 2'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <span class="term-badge">Term 3 Remark / Comment</span>
                    <textarea name="term3_comment" id="term3_comment" placeholder="Enter Term 3 remark..."><?= htmlspecialchars($adviser_comments['Term 3'] ?? '') ?></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeRemarksModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Remarks</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/app.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        function openRemarksModal() {
            document.getElementById('remarksModal').classList.add('active');
        }

        function closeRemarksModal() {
            document.getElementById('remarksModal').classList.remove('active');
        }

        function submitRemarksForm(e) {
            e.preventDefault();
            const form = document.getElementById('saveRemarksForm');
            const formData = new FormData(form);

            fetch('save-sf9-remarks.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeRemarksModal();
                    window.location.reload();
                } else {
                    alert(data.message || 'Error saving remarks.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred while attempting to save remarks.');
            });
        }
    </script>
</body>

</html>