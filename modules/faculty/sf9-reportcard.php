<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['adviser', 'faculty', 'admin'], true)) {
    header("Location: ../../manifest/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role']);
$is_admin = $user_role === 'admin';
$is_adviser_or_admin = in_array($user_role, ['adviser', 'admin'], true);

require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

/* ==========================================
   0. FETCH PENDING AVATARS COUNT
   ========================================== */
$pending_avatar_count = 0;
if ($is_adviser_or_admin) {
    if ($is_admin) {
        $avatar_count_query = $conn->query("SELECT COUNT(*) as total FROM users WHERE avatar_status = 'pending'");
        $pending_avatar_count = (int) ($avatar_count_query->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } else {
        $avatar_count_query = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM users u
            JOIN sections s ON u.section_id = s.id
            WHERE s.adviser_id = ? AND u.avatar_status = 'pending'
        ");
        $avatar_count_query->execute([$user_id]);
        $pending_avatar_count = (int) ($avatar_count_query->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
}

/* ==========================================
   1. FETCH STUDENT ROSTER
   ========================================== */

$roster_sql = "
    SELECT u.id, u.full_name,
           COALESCE(NULLIF(u.lrn, ''), u.username) AS lrn
    FROM users u
";

if (!$is_admin) {
    $roster_sql .= "
        INNER JOIN sections s ON s.id = u.section_id
        WHERE u.role = 'student'
        AND s.adviser_id = :adviser_id
    ";
} else {
    $roster_sql .= " WHERE u.role = 'student'";
}

$roster_sql .= " ORDER BY u.full_name ASC";

$roster_stmt = $conn->prepare($roster_sql);

if (!$is_admin) {
    $roster_stmt->execute(['adviser_id' => $user_id]);
} else {
    $roster_stmt->execute();
}

$roster = $roster_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   2. RESOLVE ACTIVE STUDENT
   ========================================== */

$roster_ids = array_map('intval', array_column($roster, 'id'));

$requested_student_id = isset($_GET['student_id'])
    ? intval($_GET['student_id'])
    : 0;

if ($requested_student_id > 0 && in_array($requested_student_id, $roster_ids, true)) {
    $student_id = $requested_student_id;
} elseif ($is_admin) {
    $student_id = 0;
} elseif (!empty($roster)) {
    $student_id = intval($roster[0]['id']);
} else {
    $student_id = 0;
}

$sf9_page = $is_admin
    ? (basename($_SERVER['PHP_SELF']) === 'print_report_card.php'
        ? 'print_report_card.php'
        : '../admin/print_report_card.php')
    : 'sf9-reportcard.php';

$remarks_save_url = $is_admin
    ? '../faculty/save-sf9-remarks.php'
    : 'save-sf9-remarks.php';

/* ==========================================
   3. FETCH ACADEMIC YEARS
   ========================================== */

$available_years = [];

$sy_sql = "
    SELECT DISTINCT academic_year FROM (
        SELECT academic_year
        FROM subject_assignments
        WHERE academic_year IS NOT NULL
        AND academic_year != ''

        UNION

        SELECT academic_year
        FROM sections
        WHERE academic_year IS NOT NULL
        AND academic_year != ''
    ) AS combined_sy
    ORDER BY academic_year DESC
";

$sy_stmt = $conn->query($sy_sql);

if ($sy_stmt) {
    $available_years = $sy_stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($available_years)) {
    $available_years = ['2026-2027', '2025-2026'];
}

$selected_sy = (
    isset($_GET['academic_year']) &&
    in_array($_GET['academic_year'], $available_years)
)
    ? $_GET['academic_year']
    : $available_years[0];

/* ==========================================
   4. INITIALIZE STUDENT DATA
   ========================================== */

$db_student = null;
$core_subjects = [];
$elective_subjects = [];

$adviser_comments = [
    'Term 1' => '',
    'Term 2' => '',
    'Term 3' => ''
];

if ($student_id > 0) {

    /* Student information */

    $student_scope_sql = $is_admin
        ? ''
        : " AND EXISTS (
            SELECT 1
            FROM sections owned_section
            WHERE owned_section.id = u.section_id
            AND owned_section.adviser_id = :adviser_id
        )";

    $stmt = $conn->prepare("
        SELECT
            u.id AS user_id,
            u.full_name,
            COALESCE(NULLIF(u.lrn, ''), u.username) AS lrn,
            u.section_id AS current_section_id,
            sp.birthdate,
            sp.gender
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.id = :student_id
        $student_scope_sql
        LIMIT 1
    ");

    $student_params = ['student_id' => $student_id];

    if (!$is_admin) {
        $student_params['adviser_id'] = $user_id;
    }

    $stmt->execute($student_params);
    $db_student = $stmt->fetch(PDO::FETCH_ASSOC);

    /* Historical section information */

    $historical_section = null;

    $section_scope_sql = $is_admin
        ? ''
        : ' AND s.adviser_id = :adviser_id';

    $sec_stmt = $conn->prepare("
        SELECT DISTINCT
            s.grade_level,
            s.section_name,
            st.strand_code,
            st.strand_name,
            adv.full_name AS adviser_name
        FROM subject_assignments sa
        INNER JOIN ecr_term_summaries ets
            ON ets.assignment_id = sa.id
        INNER JOIN sections s
            ON sa.section_id = s.id
        LEFT JOIN strands st
            ON s.strand_id = st.id
        LEFT JOIN users adv
            ON s.adviser_id = adv.id
        WHERE ets.student_id = :student_id
        AND sa.academic_year = :sy
        $section_scope_sql
        LIMIT 1
    ");

    $section_params = [
        'student_id' => $student_id,
        'sy' => $selected_sy
    ];

    if (!$is_admin) {
        $section_params['adviser_id'] = $user_id;
    }

    $sec_stmt->execute($section_params);
    $historical_section = $sec_stmt->fetch(PDO::FETCH_ASSOC);

    /* Fallback to current section */

    if (!$historical_section && !empty($db_student['current_section_id'])) {

        $current_section_scope_sql = $is_admin
            ? ''
            : ' AND s.adviser_id = :adviser_id';

        $curr_sec_stmt = $conn->prepare("
            SELECT
                s.grade_level,
                s.section_name,
                st.strand_code,
                st.strand_name,
                adv.full_name AS adviser_name
            FROM sections s
            LEFT JOIN strands st
                ON s.strand_id = st.id
            LEFT JOIN users adv
                ON s.adviser_id = adv.id
            WHERE s.id = :sec_id
            $current_section_scope_sql
            LIMIT 1
        ");

        $current_section_params = [
            'sec_id' => $db_student['current_section_id']
        ];

        if (!$is_admin) {
            $current_section_params['adviser_id'] = $user_id;
        }

        $curr_sec_stmt->execute($current_section_params);
        $historical_section = $curr_sec_stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($historical_section) {
        $db_student['grade_level'] = $historical_section['grade_level'];
        $db_student['section_name'] = $historical_section['section_name'];
        $db_student['strand_code'] = $historical_section['strand_code'];
        $db_student['strand_name'] = $historical_section['strand_name'];
        $db_student['adviser_name'] = $historical_section['adviser_name'] ?? '';
    }

    /* ==========================================
       5. FETCH SUBJECT GRADES
       ========================================== */

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

        INNER JOIN subject_assignments sa
            ON sa.section_id = u.section_id

        INNER JOIN subjects sub
            ON sa.subject_id = sub.id

        LEFT JOIN ecr_term_summaries ets
            ON ets.assignment_id = sa.id
            AND ets.student_id = u.id

        WHERE u.id = :student_id
        AND sa.academic_year = :sy

        GROUP BY
            sub.id,
            sub.subject_name,
            sub.type,
            sub.units

        ORDER BY sub.type ASC, sub.subject_name ASC
    ";

    $stmt_grades = $conn->prepare($grades_query);

    $stmt_grades->execute([
        'student_id' => $student_id,
        'sy' => $selected_sy
    ]);

    $res_grades = $stmt_grades->fetchAll(PDO::FETCH_ASSOC);

    foreach ($res_grades as $row) {

        $item = [
            'name' => $row['name'],

            't1' => $row['t1'] !== null
                ? round(floatval($row['t1']))
                : null,

            't2' => $row['t2'] !== null
                ? round(floatval($row['t2']))
                : null,

            't3' => $row['t3'] !== null
                ? round(floatval($row['t3']))
                : null,

            'units' => intval($row['units']),

            'final_grade' => $row['final_grade'] !== null
                ? round(floatval($row['final_grade']))
                : null,

            'remarks' => $row['remarks'] ?? '',

            'dis_t1' => false,
            'dis_t2' => false,
            'dis_t3' => false
        ];

        if (strtolower($row['subject_type']) === 'core') {
            $core_subjects[] = $item;
        } else {
            $elective_subjects[] = $item;
        }
    }

    /* ==========================================
       6. FETCH ADVISER REMARKS
       ========================================== */

    $stmt_remarks = $conn->prepare("
        SELECT term, comment
        FROM sf9_adviser_remarks
        WHERE student_id = :student_id
        AND academic_year = :sy
    ");

    $stmt_remarks->execute([
        'student_id' => $student_id,
        'sy' => $selected_sy
    ]);

    $remarks_rows = $stmt_remarks->fetchAll(PDO::FETCH_ASSOC);

    foreach ($remarks_rows as $row) {
        if (array_key_exists($row['term'], $adviser_comments)) {
            $adviser_comments[$row['term']] = $row['comment'];
        }
    }
}

/* ==========================================
   7. STUDENT AGE AND INFORMATION
   ========================================== */

$calculated_age = 'N/A';

if (!empty($db_student['birthdate']) &&
    $db_student['birthdate'] !== '0000-00-00') {

    try {
        $dob = new DateTime($db_student['birthdate']);
        $now = new DateTime();

        $calculated_age = $now->diff($dob)->y;

    } catch (Exception $e) {
        $calculated_age = 'N/A';
    }
}

$track_display = 'Academic Track';

if (!empty($db_student['strand_code']) ||
    !empty($db_student['strand_name'])) {

    $track_display = 'Academic / ' .
        ($db_student['strand_code'] ?: $db_student['strand_name']);
}

$student = [
    'full_name' => !empty($db_student['full_name'])
        ? strtoupper($db_student['full_name'])
        : 'NO STUDENT SELECTED',

    'lrn' => !empty($db_student['lrn'])
        ? $db_student['lrn']
        : 'N/A',

    'age' => $calculated_age,
    'gender' => $db_student['gender'] ?? 'N/A',
    'grade' => $db_student['grade_level'] ?? 'N/A',
    'section' => $db_student['section_name'] ?? 'N/A',
    'track' => $track_display,

    'region' => 'Region I',
    'division' => 'SCHOOLS DIVISION OFFICE OF',
    'district' => 'District I',

    'school' => 'SAN JUAN DE VALDEZ INTEGRATED SCHOOL'
];

$adviser_display = !empty($db_student['adviser_name'])
    ? strtoupper($db_student['adviser_name'])
    : 'RICHARD R. RAQUEÑO';

/* ==========================================
   8. COMPUTE FINAL GRADES
   ========================================== */

function calcFinalGrade(array $subj) {

    if (isset($subj['final_grade']) &&
        $subj['final_grade'] !== null) {

        return [
            'final' => $subj['final_grade'],
            'remark' => $subj['remarks'] ?:
                ($subj['final_grade'] >= 75 ? 'Passed' : 'Failed')
        ];
    }

    $scores = array_filter(
        [$subj['t1'] ?? null, $subj['t2'] ?? null, $subj['t3'] ?? null],
        fn($v) => $v !== null && $v !== ''
    );

    if (empty($scores)) {
        return ['final' => '', 'remark' => ''];
    }

    $final = round(array_sum($scores) / count($scores));

    return [
        'final' => $final,
        'remark' => $final >= 75 ? 'Passed' : 'Failed'
    ];
}

$all_subjs = array_merge($core_subjects, $elective_subjects);

$t1_all = array_filter(
    array_column($all_subjs, 't1'),
    fn($v) => $v !== null && $v !== ''
);

$t2_all = array_filter(
    array_column($all_subjs, 't2'),
    fn($v) => $v !== null && $v !== ''
);

$t3_all = array_filter(
    array_column($all_subjs, 't3'),
    fn($v) => $v !== null && $v !== ''
);

$gen_t1 = !empty($t1_all)
    ? round(array_sum($t1_all) / count($t1_all))
    : '';

$gen_t2 = !empty($t2_all)
    ? round(array_sum($t2_all) / count($t2_all))
    : '';

$gen_t3 = !empty($t3_all)
    ? round(array_sum($t3_all) / count($t3_all))
    : '';

$valid_gen = array_filter(
    [$gen_t1, $gen_t2, $gen_t3],
    fn($v) => $v !== ''
);

$final_gen_avg = !empty($valid_gen)
    ? round(array_sum($valid_gen) / count($valid_gen))
    : '';

$total_units = array_sum(array_column($all_subjs, 'units'));

/* ==========================================
   9. ATTENDANCE DATA
   ========================================== */

$school_days = [
    'Jun' => 20, 'Jul' => 20, 'Aug' => 20,
    'Sep' => 20, 'Oct' => 20, 'Nov' => 20,
    'Dec' => 20, 'Jan' => 18, 'Feb' => 18,
    'Mar' => 20, 'Apr' => 5
];

$days_present = [
    'Jun' => 20, 'Jul' => 19, 'Aug' => 20,
    'Sep' => 18, 'Oct' => 20, 'Nov' => 20,
    'Dec' => 19, 'Jan' => 18, 'Feb' => 18,
    'Mar' => 20, 'Apr' => 5
];

$days_absent = [];

foreach ($school_days as $m => $d) {
    $days_absent[$m] = $d - $days_present[$m];
}

$tot_days = array_sum($school_days);
$tot_pres = array_sum($days_present);
$tot_abs = array_sum($days_absent);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SF9 Learner's Performance Report Card | San Juan De Valdez IS</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/security-lock.css">

    <script src="../../assets/js/security-lock.js" defer></script>

    <style>
/* =========================================
   GENERAL SETTINGS
========================================= */
*, *::before, *::after {
    box-sizing: border-box !important;
}

html, body {
    min-height: 100%;
    margin: 0;
    padding: 0;
    background-color: #f1f5f9;
    font-family: 'Times New Roman', Times, serif;
    color: #000;
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
    min-width: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
    transition: margin-left 0.3s ease, width 0.3s ease;
}

#sidebar:not(.active) ~ main.content {
    margin-left: 0;
    width: 100%;
}

/* =========================================
   NAV BADGE
========================================= */
.nav-badge {
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    float: right;
    margin-top: 2px;
}

/* =========================================
   TOOLBAR
========================================= */
.sf9-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    background: #fff;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
    width: 100%;
}

.toolbar-title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    min-width: 0;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.mobile-burger-btn {
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 1.1rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-print,
.btn-remarks {
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: .82rem;
}

.btn-print {
    background: #2563eb;
}

.btn-print:hover {
    background: #1d4ed8;
}

.btn-remarks {
    background: #0d9488;
}

.btn-remarks:hover {
    background: #0f766e;
}

/* =========================================
   REPORT DOCUMENT
========================================= */
.report-document-wrapper {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    width: 100%;
    padding-bottom: 20px;
    overflow: visible;
}

.report-document {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    gap: 14px;

    background: #fff;
    padding: 18px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 4px 12px rgba(0,0,0,.06);

    width: 100%;
    max-width: 1200px;
    min-width: 0;

    font-size: 12px;
}

.doc-page {
    flex: 1 1 0;
    width: 50%;
    min-width: 0;
    border: 1px solid #000;
    padding: 12px;
    background: #fff;
    position: relative;
    overflow: hidden;
}

/* =========================================
   DOCUMENT HEADER
========================================= */
.header-section {
    position: relative;
    text-align: center;
    margin-bottom: 6px;
    font-size: 11px;
}

.vector-deped-seal {
    position: absolute;
    left: 0;
    top: 0;
    width: 42px;
    height: 42px;
}

.school-logo-box {
    position: absolute;
    right: 0;
    top: 0;
    width: 42px;
    height: 42px;
    border: 1px dashed #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    text-align: center;
    font-family: sans-serif;
}

.header-section .deped-line {
    margin: 1px 0;
}

.line-field {
    border-bottom: 1px solid #000;
    display: inline-block;
    padding: 0 2px;
    text-align: center;
}

/* =========================================
   STUDENT INFORMATION
========================================= */
.student-info-block {
    margin-bottom: 6px;
    font-size: 12px;
    line-height: 1.35;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 5px;
    margin-bottom: 3px;
}

.message-block {
    font-size: 11.5px;
    margin-bottom: 6px;
    text-align: justify;
    line-height: 1.25;
}

.message-block p {
    margin: 2px 0;
    text-indent: 12px;
}

.section-heading {
    text-align: center;
    font-weight: bold;
    font-size: 12px;
    margin: 8px 0 4px;
    text-transform: uppercase;
}

/* =========================================
   LEARNING AREAS TABLE
========================================= */
table.doc-table {
    width: 100% !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
    font-size: 11px;
    margin-bottom: 6px;
}

table.doc-table th,
table.doc-table td {
    border: 1px solid #000;
    padding: 3px 2px;
    text-align: center;
    overflow-wrap: anywhere;
    word-wrap: break-word;
    box-sizing: border-box;
}

table.doc-table td.text-left {
    text-align: left;
    padding-left: 4px;
}

/* =========================================
   ATTENDANCE TABLE
========================================= */
table.attendance-table {
    width: 100% !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
}

table.attendance-table th,
table.attendance-table td {
    padding: 1px 0px !important;
    font-size: 8px !important;
    text-align: center !important;
    white-space: nowrap !important;
    overflow: hidden !important;
}

table.attendance-table td.text-left {
    text-align: left !important;
    padding-left: 2px !important;
    white-space: normal !important;
    font-size: 8px !important;
}

.cell-disabled {
    background-color: #cbd5e1;
}

/* =========================================
   PERFORMANCE DESCRIPTORS
========================================= */
.descriptors-table {
    width: 100%;
    border-collapse: collapse;
    text-align: center;
    font-size: 11px;
    margin-top: 4px;
}

.descriptors-table th,
.descriptors-table td {
    border: none;
    padding: 1px;
}

/* =========================================
   COMMENTS AND SIGNATURES
========================================= */
.comment-area {
    border: 1px solid #000;
    min-height: 28px;
    margin-bottom: 4px;
    padding: 3px 5px;
    font-size: 11px;
    overflow-wrap: anywhere;
}

.signature-row {
    margin-bottom: 4px;
    font-size: 11px;
}

.transfer-cert-body {
    font-size: 11px;
    line-height: 1.3;
    margin-top: 4px;
}

.footer-signatures {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 10px;
}

.footer-signatures .field {
    text-align: center;
    width: 45%;
    font-size: 11px;
}

.footer-signatures .field .line {
    border-top: 1px solid #000;
    margin-top: 16px;
    padding-top: 2px;
}

/* =========================================
   REMARKS MODAL
========================================= */
.modal-overlay {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    background: rgba(15,23,42,.55);
    backdrop-filter: blur(6px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    padding: 15px;
}

.modal-overlay.active {
    display: flex;
}

.modal-box {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 540px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px 28px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,.1);
    border: 1px solid #e2e8f0;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding-bottom: 14px;
    margin-bottom: 18px;
    border-bottom: 1px solid #f1f5f9;
}

.modal-header h4 {
    margin: 0;
    font-size: 1.05rem;
    color: #0f172a;
    font-weight: 700;
}

.modal-close {
    background: #f1f5f9;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    color: #64748b;
    flex-shrink: 0;
}

.form-group {
    margin-bottom: 16px;
}

.form-group .term-badge {
    display: inline-block;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
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
    font-size: .85rem;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
}

.btn-cancel,
.btn-save {
    padding: 9px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

.btn-cancel {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.btn-save {
    background: #2563eb;
    color: #fff;
    border: none;
}

/* =========================================
   TABLET RESPONSIVENESS
========================================= */
@media screen and (max-width: 900px) {
    main.content {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 12px !important;
    }

    .report-document-wrapper {
        width: 100%;
        overflow: visible;
    }

    .report-document {
        width: 100%;
        max-width: 100%;
        padding: 12px;
        gap: 10px;
    }

    .doc-page {
        padding: 8px;
    }

    .sf9-toolbar {
        padding: 12px;
    }
}

/* =========================================
   MOBILE RESPONSIVENESS
========================================= */
@media screen and (max-width: 600px) {

    html, body {
        width: 100%;
        overflow-x: hidden;
    }

    main.content {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 8px !important;
        min-width: 0;
    }

    .sf9-toolbar {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        padding: 12px;
        margin-bottom: 12px;
    }

    .toolbar-title {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .toolbar-title form {
        width: 100%;
        display: flex !important;
        flex-direction: column;
        align-items: stretch !important;
        gap: 12px !important;
    }

    .toolbar-title form > div {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: stretch !important;
        gap: 5px !important;
    }

    .toolbar-title form label {
        font-size: 13px !important;
    }

    .toolbar-title form select {
        width: 100%;
        max-width: 100%;
        min-height: 40px;
        font-size: 13px !important;
    }

    .toolbar-actions {
        display: flex;
        flex-direction: column;
        width: 100%;
        gap: 8px;
    }

    .toolbar-actions button {
        width: 100%;
        min-height: 42px;
        margin: 0;
    }

    .report-document-wrapper {
        display: block;
        width: 100%;
        overflow: visible;
        padding: 0 0 20px;
    }

    .report-document {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding: 8px;
        gap: 12px;
        border-radius: 8px;
        box-shadow: none;
        zoom: 1;
    }

    .doc-page {
        display: block;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        flex: none;
        padding: 9px;
        overflow: hidden;
        border: 1px solid #000;
    }

    .header-section {
        font-size: 9px;
        padding-top: 2px;
    }

    .header-section .deped-line {
        margin: 2px 0;
    }

    .vector-deped-seal,
    .school-logo-box {
        width: 32px;
        height: 32px;
    }

    .school-logo-box {
        font-size: 7px;
    }

    .section-heading {
        font-size: 10px;
        margin: 7px 0 4px;
    }

    .student-info-block {
        font-size: 10px;
        line-height: 1.5;
    }

    .info-row {
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 5px 10px;
    }

    .message-block {
        font-size: 10px;
        line-height: 1.35;
    }

    table.doc-table {
        width: 100% !important;
        font-size: 8px;
        table-layout: fixed !important;
    }

    table.doc-table th,
    table.doc-table td {
        padding: 3px 2px;
        font-size: 8px;
        overflow-wrap: anywhere;
    }

    table.attendance-table th,
    table.attendance-table td {
        font-size: 6.5px !important;
        padding: 2px 0 !important;
    }

    .descriptors-table {
        font-size: 9px;
    }

    .descriptors-table th,
    .descriptors-table td {
        padding: 2px;
    }

    .comment-area {
        min-height: 35px;
        font-size: 10px;
    }

    .signature-row,
    .transfer-cert-body {
        font-size: 9px;
    }

    .footer-signatures .field {
        font-size: 9px;
    }

    .modal-box {
        padding: 18px;
        max-height: 85vh;
    }

    .modal-header h4 {
        font-size: .9rem;
    }
}

/* =========================================
   OFFICIAL SF9 PRINT LAYOUT
   A4 LANDSCAPE - TWO PAGES SIDE BY SIDE
========================================= */
@media print {

    @page {
        size: A4 landscape;
        margin: 4mm;
    }

    html, body {
        width: 289mm !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        overflow: visible !important;
    }

    .sidebar,
    .mobile-toggle,
    .sf9-toolbar,
    .no-print,
    .modal-overlay {
        display: none !important;
    }

    .dashboard-wrapper {
        display: block !important;
        width: 100% !important;
        min-height: 0 !important;
    }

    main.content {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    .report-document-wrapper {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
    }

    .report-document {
        display: flex !important;
        flex-direction: row !important;
        align-items: stretch !important;
        justify-content: space-between !important;

        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;

        padding: 0 !important;
        margin: 0 !important;
        gap: 3mm !important;

        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;

        zoom: 1 !important;
        transform: none !important;
        font-size: 9px !important;
    }

    .doc-page {
        display: block !important;

        flex: 0 0 calc(50% - 1.5mm) !important;
        width: calc(50% - 1.5mm) !important;
        max-width: calc(50% - 1.5mm) !important;
        min-width: 0 !important;

        padding: 4mm !important;
        border: 1px solid #000 !important;
        overflow: hidden !important;

        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .header-section {
        font-size: 9px !important;
    }

    .student-info-block {
        font-size: 9px !important;
    }

    .message-block {
        font-size: 9px !important;
    }

    .section-heading {
        font-size: 9px !important;
    }

    table.doc-table {
        width: 100% !important;
        font-size: 8px !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
    }

    table.doc-table th,
    table.doc-table td {
        font-size: 8px !important;
        padding: 2px 1px !important;
        border: 1px solid #000 !important;
    }

    table.attendance-table th,
    table.attendance-table td {
        font-size: 6.5px !important;
        padding: 2px 0 !important;
        white-space: normal !important;
    }

    .descriptors-table {
        font-size: 8px !important;
    }

    .comment-area {
        font-size: 9px !important;
        min-height: 24px !important;
    }

    .signature-row,
    .transfer-cert-body {
        font-size: 9px !important;
    }

    .footer-signatures .field {
        font-size: 9px !important;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
    </style>
</head>

<body>

<div class="dashboard-wrapper">

    <!-- ==========================================
         SIDEBAR NAVIGATION
         ========================================== -->

    <nav class="sidebar" id="sidebar">

        <div class="sidebar-header"
             style="display:flex;justify-content:space-between;align-items:center;width:100%;">

            <h3>STRAND-SYNC</h3>

            <button class="sidebar-close" onclick="toggleSidebar()">✕</button>

        </div>

        <ul class="menu">

            <li><a href="faculty_dashboard.php">Dashboard</a></li>

            <li><a href="faculty_schedule.php">My Schedule</a></li>

            <li><a href="ecr-inputdata.php">ECR Setup &amp; Roster</a></li>

            <li><a href="ecr-view.php">Encode Term Grades</a></li>

            <li><a href="ecr-summary.php">ECR Summary</a></li>

            <?php if ($is_adviser_or_admin): ?>
                <li class="menu-divider"
                    style="padding:12px 16px 4px;font-size:0.7rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">

                    Advisory Forms

                </li>

                <li>
                    <a href="review_avatars.php" class="<?= basename($_SERVER['PHP_SELF']) == 'review_avatars.php' ? 'active' : '' ?>">
                        Review Avatars 
                        <?php if ($pending_avatar_count > 0): ?>
                            <span class="nav-badge"><?= $pending_avatar_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="<?= htmlspecialchars($sf9_page) ?>" class="active">
                        SF9 (Report Card)
                    </a>
                </li>

                <li><a href="sf10-record.php">SF10 (Permanent Record)</a></li>
            <?php endif; ?>

            <li><a href="../../manifest/logout.php" class="logout">Logout</a></li>

        </ul>

    </nav>

    <main class="content">

        <!-- ==========================================
             SCREEN TOOLBAR
             ========================================== -->

        <div class="sf9-toolbar no-print">

            <div class="toolbar-title">

                <button class="mobile-burger-btn"
                        onclick="toggleSidebar()"
                        title="Toggle Sidebar">
                    ☰
                </button>

                <form method="GET"
                      action="<?= htmlspecialchars($sf9_page) ?>"
                      id="studentSelectForm"
                      style="margin:0; display:flex; align-items:center; gap:12px;">

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

                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="academic_year" style="font-size: 0.85rem; font-weight: 600; color: #334155;">School Year:</label>
                        <select name="academic_year" id="academic_year" onchange="document.getElementById('studentSelectForm').submit();" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; background: #ffffff; cursor: pointer; font-weight: 500;" <?= $student_id > 0 ? '' : 'disabled' ?>>
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

            <?php if ($db_student): ?>
            <div class="toolbar-actions">

                <button class="btn-remarks"
                        onclick="openRemarksModal()">
                    Edit Adviser Remarks
                </button>

                <button class="btn-print"
                        onclick="window.print()">
                    Print Official SF9 Document
                </button>

            </div>
            <?php endif; ?>

        </div>

        <!-- ==========================================
             SF9 DOCUMENT
             ========================================== -->

        <div class="report-document-wrapper">

            <?php if ($db_student): ?>

            <div class="report-document">

                <!-- ==========================================
                     PAGE 1 - ACADEMIC PERFORMANCE
                     ========================================== -->

                <div class="doc-page">

                    <!-- SCHOOL HEADER -->

                    <div class="header-section">

                        <svg class="vector-deped-seal" viewBox="0 0 100 100">

                            <circle cx="50" cy="50" r="46"
                                    fill="none" stroke="#003366" stroke-width="4"/>

                            <circle cx="50" cy="50" r="38"
                                    fill="none" stroke="#C5A059" stroke-width="2"/>

                            <text x="50" y="30" font-size="9"
                                  text-anchor="middle" font-weight="bold"
                                  fill="#003366">

                                KAGAWARAN NG EDUKASYON

                            </text>

                            <polygon points="50,38 54,48 64,48 56,54 59,64 50,58 41,64 44,54 36,48 46,48"
                                     fill="#C5A059"/>

                            <text x="50" y="78" font-size="8"
                                  text-anchor="middle" font-weight="bold"
                                  fill="#003366">

                                REPUBLIKA NG PILIPINAS

                            </text>

                        </svg>

                        <div class="school-logo-box">
                            School<br>Logo
                        </div>

                        <div class="deped-line">Republic of the Philippines</div>
                        <div class="deped-line">Department of Education</div>

                        <div class="deped-line">
                            <?= htmlspecialchars($student['region']) ?>
                        </div>

                        <div class="deped-line" style="font-weight:bold;font-size:11.5px;">
                            <?= htmlspecialchars($student['division']) ?>
                        </div>

                        <div class="deped-line">
                            District
                            <span class="line-field" style="width:80px;">
                                <?= htmlspecialchars($student['district']) ?>
                            </span>
                        </div>

                        <div class="deped-line">Municipality/City, Province</div>

                        <div class="deped-line" style="margin-top:3px;">
                            School:
                            <span class="line-field" style="width:220px;">
                                <?= htmlspecialchars($student['school']) ?>
                            </span>
                        </div>

                    </div>

                    <div class="section-heading" style="margin-top:4px;">
                        LEARNER'S PERFORMANCE REPORT
                    </div>

                    <div style="text-align:center;font-size:11px;margin-bottom:6px;">
                        School Year <?= htmlspecialchars($selected_sy ?: 'N/A') ?>
                    </div>

                    <!-- STUDENT INFORMATION -->

                    <div class="student-info-block">

                        <div class="info-row">

                            <span>
                                Name:
                                <span class="line-field"
                                      style="min-width:180px;text-align:left;padding-left:4px;">

                                    <?= htmlspecialchars($student['full_name']) ?>

                                </span>
                            </span>

                            <span>
                                Age:
                                <span class="line-field" style="min-width:25px;">
                                    <?= htmlspecialchars($student['age']) ?>
                                </span>
                            </span>

                            <span>
                                Sex:
                                <span class="line-field" style="min-width:35px;">
                                    <?= htmlspecialchars($student['gender']) ?>
                                </span>
                            </span>

                        </div>

                        <div class="info-row" style="margin-top:4px;">

                            <span>
                                LRN:
                                <span class="line-field"
                                      style="min-width:140px;text-align:left;padding-left:4px;">

                                    <?= htmlspecialchars($student['lrn']) ?>

                                </span>
                            </span>

                            <span>
                                Grade:
                                <span class="line-field" style="min-width:35px;">
                                    <?= htmlspecialchars($student['grade']) ?>
                                </span>
                            </span>

                            <span>
                                Section:
                                <span class="line-field" style="min-width:70px;">
                                    <?= htmlspecialchars($student['section']) ?>
                                </span>
                            </span>

                        </div>

                        <div style="margin-top:4px;">

                            Track (SHS only):

                            <span class="line-field"
                                  style="min-width:180px;text-align:left;padding-left:4px;">

                                <?= htmlspecialchars($student['track']) ?>

                            </span>

                        </div>

                    </div>

                    <!-- PARENT MESSAGE -->

                    <div class="message-block">

                        Dear Parents,

                        <p>
                            This Performance Report shows the ability and progress
                            your child has made in the different learning areas
                            as well as his/her core values.
                        </p>

                        <p>
                            The school welcomes you should you desire to know more
                            about your child's progress.
                        </p>

                    </div>

                    <!-- GRADES TABLE -->

                    <div class="section-heading">
                        LEARNING PROGRESS AND ACHIEVEMENT
                    </div>

                    <table class="doc-table">

                        <colgroup>
                            <col style="width:36%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:18%;">
                            <col style="width:31%;">
                        </colgroup>

                        <thead>

                            <tr>

                                <th rowspan="2" class="text-left">
                                    Learning Areas
                                </th>

                                <th colspan="3">TERM</th>

                                <th rowspan="2">Final Grade</th>

                                <th rowspan="2">Remarks</th>

                            </tr>

                            <tr>
                                <th>1</th>
                                <th>2</th>
                                <th>3</th>
                            </tr>

                        </thead>

                        <tbody>

                            <!-- CORE SUBJECTS -->

                            <tr>
                                <td colspan="6"
                                    class="text-left"
                                    style="font-weight:bold;font-style:italic;background:#f8fafc;">

                                    Core Subjects

                                </td>
                            </tr>

                            <?php if (!empty($core_subjects)): ?>

                                <?php foreach ($core_subjects as $subj): ?>

                                    <?php $res = calcFinalGrade($subj); ?>

                                    <tr>

                                        <td class="text-left">
                                            <?= htmlspecialchars($subj['name']) ?>
                                        </td>

                                        <td><?= $subj['t1'] ?? '' ?></td>

                                        <td><?= $subj['t2'] ?? '' ?></td>

                                        <td><?= $subj['t3'] ?? '' ?></td>

                                        <td>
                                            <strong><?= $res['final'] ?></strong>
                                        </td>

                                        <td><?= htmlspecialchars($res['remark']) ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="6" style="font-style:italic;color:#64748b;">
                                        No core subjects found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                            <!-- ELECTIVE SUBJECTS -->

                            <tr>

                                <td colspan="6"
                                    class="text-left"
                                    style="font-weight:bold;font-style:italic;background:#f8fafc;">

                                    Elective Subjects

                                </td>

                            </tr>

                            <?php if (!empty($elective_subjects)): ?>

                                <?php foreach ($elective_subjects as $subj): ?>

                                    <?php $res = calcFinalGrade($subj); ?>

                                    <tr>

                                        <td class="text-left">
                                            <?= htmlspecialchars($subj['name']) ?>
                                        </td>

                                        <td class="<?= !empty($subj['dis_t1']) ? 'cell-disabled' : '' ?>">
                                            <?= $subj['t1'] ?? '' ?>
                                        </td>

                                        <td class="<?= !empty($subj['dis_t2']) ? 'cell-disabled' : '' ?>">
                                            <?= $subj['t2'] ?? '' ?>
                                        </td>

                                        <td class="<?= !empty($subj['dis_t3']) ? 'cell-disabled' : '' ?>">
                                            <?= $subj['t3'] ?? '' ?>
                                        </td>

                                        <td>
                                            <strong><?= $res['final'] ?></strong>
                                        </td>

                                        <td><?= htmlspecialchars($res['remark']) ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="6" style="font-style:italic;color:#64748b;">
                                        No elective subjects found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                            <!-- GENERAL AVERAGE -->

                            <tr style="font-weight:bold;background:#f1f5f9;">

                                <td class="text-left" style="font-style:italic;">
                                    General Average
                                </td>

                                <td><?= $gen_t1 ?></td>
                                <td><?= $gen_t2 ?></td>
                                <td><?= $gen_t3 ?></td>
                                <td><?= $final_gen_avg ?></td>

                                <td>
                                    <?= !empty($final_gen_avg)
                                        ? ($final_gen_avg >= 75 ? 'Passed' : 'Failed')
                                        : '' ?>
                                </td>

                            </tr>

                        </tbody>

                    </table>

                    <!-- PERFORMANCE DESCRIPTORS -->

                    <div class="section-heading"
                         style="font-size:11px;margin-top:4px;">

                        PERFORMANCE DESCRIPTORS

                    </div>

                    <table class="descriptors-table">

                        <thead>

                            <tr style="font-weight:bold;">

                                <td style="width:33%;">Grading Scale</td>
                                <td style="width:33%;">Description</td>
                                <td style="width:34%;">Remarks</td>

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

                <!-- ==========================================
                     PAGE 2 - ATTENDANCE AND CERTIFICATES
                     ========================================== -->

                <div class="doc-page">

                    <!-- ATTENDANCE RECORD -->

                    <div style="font-weight:bold;text-align:center;font-size:12px;margin-bottom:4px;">

                        ATTENDANCE RECORD

                    </div>

                    <table class="doc-table attendance-table">

                        <colgroup>

                            <col style="width:26%;">

                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">
                            <col style="width:5%;">

                            <col style="width:14%;">

                        </colgroup>

                        <thead>

                            <tr>

                                <th>Month</th>

                                <th>Jun</th>
                                <th>Jul</th>
                                <th>Aug</th>
                                <th>Sep</th>
                                <th>Oct</th>
                                <th>Nov</th>
                                <th>Dec</th>
                                <th>Jan</th>
                                <th>Feb</th>
                                <th>Mar</th>
                                <th>Apr</th>

                                <th>Total</th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td class="text-left" style="font-weight:bold;">
                                    No. of Class Days
                                </td>

                                <?php foreach ($school_days as $m => $days): ?>

                                    <td><?= $days ?></td>

                                <?php endforeach; ?>

                                <td><strong><?= $tot_days ?></strong></td>

                            </tr>

                            <tr>

                                <td class="text-left" style="font-weight:bold;">
                                    No. of Days Present
                                </td>

                                <?php foreach ($days_present as $m => $pres): ?>

                                    <td><?= $pres ?></td>

                                <?php endforeach; ?>

                                <td><strong><?= $tot_pres ?></strong></td>

                            </tr>

                            <tr>

                                <td class="text-left" style="font-weight:bold;">
                                    No. of Days Absent
                                </td>

                                <?php foreach ($days_absent as $m => $abs): ?>

                                    <td><?= $abs ?></td>

                                <?php endforeach; ?>

                                <td><strong><?= $tot_abs ?></strong></td>

                            </tr>

                        </tbody>

                    </table>

                    <!-- TEACHER COMMENTS -->

                    <div style="font-weight:bold;text-align:center;font-size:12px;margin:8px 0 4px;">

                        TEACHER'S COMMENTS/REMARKS

                    </div>

                    <div style="font-size:11px;font-weight:bold;margin-bottom:1px;">
                        Term 1
                    </div>

                    <div class="comment-area">
                        <?= htmlspecialchars($adviser_comments['Term 1'] ?? '') ?>
                    </div>

                    <div style="font-size:11px;font-weight:bold;margin-bottom:1px;">
                        Term 2
                    </div>

                    <div class="comment-area">
                        <?= htmlspecialchars($adviser_comments['Term 2'] ?? '') ?>
                    </div>

                    <div style="font-size:11px;font-weight:bold;margin-bottom:1px;">
                        Term 3
                    </div>

                    <div class="comment-area">
                        <?= htmlspecialchars($adviser_comments['Term 3'] ?? '') ?>
                    </div>

                    <!-- PARENT SIGNATURE -->

                    <div style="font-weight:bold;text-align:center;font-size:12px;margin:8px 0 4px;">

                        PARENTS/GUARDIAN'S SIGNATURE

                    </div>

                    <div class="signature-row">
                        Term 1
                        <span class="line-field" style="width:82%;"></span>
                    </div>

                    <div class="signature-row">
                        Term 2
                        <span class="line-field" style="width:82%;"></span>
                    </div>

                    <div class="signature-row">
                        Term 3
                        <span class="line-field" style="width:82%;"></span>
                    </div>

                    <!-- CERTIFICATE OF TRANSFER -->

                    <div style="font-weight:bold;text-align:center;font-size:12px;margin:8px 0 4px;">

                        CERTIFICATE OF TRANSFER

                    </div>

                    <div class="transfer-cert-body">

                        This is to certify that the above-named learner has
                        satisfactorily completed the requirements for the
                        grade level indicated.

                        <br><br>

                        Admitted to Grade:
                        <span class="line-field" style="min-width:90px;"></span>

                        <br>

                        Eligible for Admission to Grade:
                        <span class="line-field" style="min-width:90px;"></span>

                    </div>

                    <div style="margin-top:6px;font-weight:bold;font-size:11px;">
                        Approved:
                    </div>

                    <!-- SIGNATURES -->

                    <div class="footer-signatures" style="margin-top:4px;">

                        <div class="field">

                            <div class="line" style="font-weight:bold;">
                                SCHOOL HEAD NAME
                            </div>

                            <div>School Head</div>

                        </div>

                        <div class="field">

                            <div class="line" style="font-weight:bold;">
                                <?= htmlspecialchars($adviser_display) ?>
                            </div>

                            <div>Adviser</div>

                        </div>

                    </div>

                    <!-- CANCELLATION OF TRANSFER -->

                    <div style="font-weight:bold;text-align:center;font-size:12px;margin:10px 0 4px;">

                        CANCELLATION OF ELIGIBILITY TO TRANSFER

                    </div>

                    <div style="font-size:11px;margin-top:4px;">

                        Admitted in:
                        <span class="line-field" style="min-width:140px;"></span>

                        <span style="float:right;">

                            Date:
                            <span class="line-field" style="min-width:90px;"></span>

                        </span>

                    </div>

                    <div class="footer-signatures" style="margin-top:10px;">

                        <div class="field" style="width:50%;margin:0 auto;">

                            <div class="line" style="font-weight:bold;">
                                SCHOOL HEAD NAME
                            </div>

                            <div>School Head</div>

                        </div>

                    </div>

                </div>

            </div>

            <?php else: ?>

                <p class="no-print">
                    Select a student from the Master List to view and print their SF9 record.
                </p>

            <?php endif; ?>

        </div>

    </main>

</div>

<!-- ==========================================
     ADVISER REMARKS MODAL
     ========================================== -->

<div class="modal-overlay" id="remarksModal">

    <div class="modal-box">

        <div class="modal-header">

            <h4>
                Adviser Remarks:
                <span class="student-name-highlight">
                    <?= htmlspecialchars($student['full_name']) ?>
                </span>
            </h4>

            <button class="modal-close"
                    onclick="closeRemarksModal()"
                    title="Close">

                ✕

            </button>

        </div>

        <form id="saveRemarksForm" onsubmit="submitRemarksForm(event)">

            <input type="hidden" name="student_id" value="<?= $student_id ?>">

            <input type="hidden"
                   name="academic_year"
                   value="<?= htmlspecialchars($selected_sy) ?>">

            <div class="form-group">

                <span class="term-badge">
                    Term 1 Remark / Comment
                </span>

                <textarea name="term1_comment"
                          id="term1_comment"
                          placeholder="Enter Term 1 remark..."><?= htmlspecialchars($adviser_comments['Term 1'] ?? '') ?></textarea>

            </div>

            <div class="form-group">

                <span class="term-badge">
                    Term 2 Remark / Comment
                </span>

                <textarea name="term2_comment"
                          id="term2_comment"
                          placeholder="Enter Term 2 remark..."><?= htmlspecialchars($adviser_comments['Term 2'] ?? '') ?></textarea>

            </div>

            <div class="form-group">

                <span class="term-badge">
                    Term 3 Remark / Comment
                </span>

                <textarea name="term3_comment"
                          id="term3_comment"
                          placeholder="Enter Term 3 remark..."><?= htmlspecialchars($adviser_comments['Term 3'] ?? '') ?></textarea>

            </div>

            <div class="modal-actions">

                <button type="button"
                        class="btn-cancel"
                        onclick="closeRemarksModal()">

                    Cancel

                </button>

                <button type="submit" class="btn-save">

                    Save Remarks

                </button>

            </div>

        </form>

    </div>

</div>

<!-- ==========================================
     JAVASCRIPT
     ========================================== -->

<script src="../../assets/js/app.js"></script>

<script>

    /* Sidebar */

    function toggleSidebar() {

        const sidebar = document.getElementById('sidebar');

        sidebar.classList.toggle('active');

    }

    /* Open remarks modal */

    function openRemarksModal() {

        document.getElementById('remarksModal')
            .classList.add('active');

    }

    /* Close remarks modal */

    function closeRemarksModal() {

        document.getElementById('remarksModal')
            .classList.remove('active');

    }

    /* Save adviser remarks */

    function submitRemarksForm(e) {

        e.preventDefault();

        const form = document.getElementById('saveRemarksForm');

        const formData = new FormData(form);

        fetch('<?= htmlspecialchars($remarks_save_url) ?>', {

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

    /* Automatically print when requested */

    <?php if (isset($_GET['print']) && $_GET['print'] === '1' && $db_student): ?>

        window.addEventListener('load', function() {

            window.print();

        });

    <?php endif; ?>

</script>

</body>
</html>