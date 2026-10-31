<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'alumni') {
    header("Location: ../../manifest/login.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT u.full_name, u.username, COALESCE(NULLIF(u.lrn, ''), a.lrn) AS lrn,
           a.batch_year, st.strand_name, s.grade_level, s.section_name
    FROM users u
    LEFT JOIN archived_students a ON a.original_id = u.id
    LEFT JOIN strands st ON st.id = a.strand_id
    LEFT JOIN sections s ON s.id = a.section_id
    WHERE u.id = :user_id AND u.role = 'alumni'
    LIMIT 1
");
$stmt->execute([':user_id' => (int) $_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    header("Location: ../../manifest/login.php");
    exit();
}

$gradesStmt = $conn->prepare("
    SELECT
        sub.subject_name,
        sub.subject_code,
        sub.grade_level,
        sub.term AS subject_term,
        sa.academic_year,
        ets.term1_transmuted,
        ets.term2_transmuted,
        ets.term3_transmuted,
        ets.final_grade,
        ets.remarks
    FROM ecr_term_summaries ets
    INNER JOIN subject_assignments sa ON ets.assignment_id = sa.id
    INNER JOIN subjects sub ON sa.subject_id = sub.id
    WHERE ets.student_id = :user_id
    ORDER BY
        sub.grade_level ASC,
        sa.academic_year ASC,
        CASE sub.term
            WHEN '1st' THEN 1
            WHEN '1st Sem' THEN 1
            WHEN '2nd' THEN 2
            WHEN '2nd Sem' THEN 2
            WHEN '3rd' THEN 3
            ELSE 4
        END ASC,
        sub.subject_name ASC
");
$gradesStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
$academic_records = [];
foreach ($gradesStmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
    $academic_records[$record['grade_level']][$record['academic_year']][$record['subject_term']][] = $record;
}

$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? 'N/A'), ENT_QUOTES, 'UTF-8');
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unofficial Academic Record | STRAND-SYNC</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .alumni-container {
            max-width: 1100px;
            margin: 80px auto;
            padding: 0 20px;
        }

        .portal-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .portal-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .portal-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .portal-header p {
            opacity: 0.8;
            margin-top: 8px;
            font-size: 0.95rem;
        }

        .portal-body {
            padding: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #f1f5f9;
        }

        .info-label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .info-value {
            display: block;
            font-size: 1.1rem;
            color: #1e293b;
            font-weight: 500;
        }

        .archive-notice {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            padding: 15px;
            border-radius: 8px;
            color: #92400e;
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .btn-logout {
            display: block;
            width: 100%;
            text-align: center;
            background: #e2e8f0;
            color: #475569;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: #cbd5e1;
            color: #1e293b;
        }

        .brand-footer {
            text-align: center;
            margin-top: 20px;
            color: #94a3b8;
            font-size: 0.8rem;
        }

        .record-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 36px 0 16px;
        }

        .record-heading h2 {
            margin: 0;
            color: #1e293b;
        }

        .record-year {
            margin: 0 0 20px;
            color: #64748b;
            font-weight: 600;
        }

        .grade-level-section {
            margin-bottom: 24px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }

        .grade-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 0.9rem;
        }

        .grade-table th,
        .grade-table td {
            padding: 11px 12px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .grade-table th {
            background: #1e293b;
            color: white;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .grade-table th:first-child,
        .grade-table td:first-child {
            text-align: left;
        }

        .subject-code {
            display: block;
            color: #64748b;
            font-size: 0.78rem;
        }

        .record-term {
            color: #334155;
            font-size: 1rem;
            margin: 20px 0 10px;
        }

        .record-status {
            margin: 0 0 25px;
            padding: 15px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fef2f2;
            color: #991b1b;
            text-align: center;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .record-disclaimer {
            margin: 0 0 24px;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .portal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-print,
        .btn-logout {
            flex: 1;
            border: 0;
            cursor: pointer;
            font: inherit;
        }

        .btn-print {
            background: #2563eb;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-print:hover {
            background: #1d4ed8;
        }

        @media (max-width: 640px) {
            .alumni-container {
                margin: 24px auto;
                padding: 0 12px;
            }

            .portal-header,
            .portal-body {
                padding: 24px 18px;
            }

            .record-heading,
            .portal-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .table-wrap {
                overflow-x: auto;
            }

            .grade-table {
                min-width: 650px;
            }
        }

        @media print {
            body {
                background: white;
            }

            .alumni-container {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .portal-card {
                border: 0;
                box-shadow: none;
            }

            .portal-header {
                background: white;
                color: #1e293b;
                padding: 16px 0;
            }

            .portal-header p {
                color: #475569;
            }

            .portal-body {
                padding: 16px 0;
            }

            .portal-actions,
            .brand-footer {
                display: none;
            }

            .info-item,
            .grade-level-section,
            .grade-table tr {
                break-inside: avoid;
            }

            .grade-table th {
                background: #e2e8f0 !important;
                color: #0f172a !important;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="alumni-container">
        <div class="portal-card">
            <header class="portal-header">
                <h1>Welcome, <?php echo $escape($user_data['full_name']); ?>!</h1>
                <p>Alumni Account Access</p>
            </header>

            <div class="portal-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Graduation Year</span>
                        <span class="info-value"><?php echo $escape($user_data['batch_year']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo $escape($user_data['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Learner Reference Number</span>
                        <span class="info-value"><?php echo $escape($user_data['lrn']); ?></span>
                    </div>
                    <?php if (!empty($user_data['strand_name'])): ?>
                        <div class="info-item">
                            <span class="info-label">Strand</span>
                            <span class="info-value"><?php echo $escape($user_data['strand_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($user_data['section_name'])): ?>
                        <div class="info-item">
                            <span class="info-label">Last Section</span>
                            <span class="info-value"><?php echo $escape($user_data['section_name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="archive-notice">
                    <strong>Note:</strong> Your account is currently in <strong>Archive Mode</strong>. Profile modifications are restricted. For official transcript requests or record corrections, please contact the School Registrar.
                </div>

                <div class="record-status">UNOFFICIAL COPY — FOR PERSONAL REFERENCE ONLY</div>
                <p class="record-disclaimer">
                    The academic history below is provided for your personal reference. Viewing or printing it does not make it an official transcript or school-certified record. Contact the School Registrar for official records.
                </p>

                <div class="record-heading">
                    <h2>Past Academic Records</h2>
                </div>
                <?php if (!empty($academic_records)): ?>
                    <?php foreach ($academic_records as $grade_level => $school_years): ?>
                        <?php foreach ($school_years as $school_year => $terms): ?>
                            <section class="grade-level-section">
                                <h3 class="record-year">
                                    Grade <?php echo $escape($grade_level); ?>
                                    <?php if (!empty($school_year)): ?>
                                        &middot; School Year <?php echo $escape($school_year); ?>
                                    <?php endif; ?>
                                </h3>
                                <?php foreach ($terms as $term => $subjects): ?>
                                    <h4 class="record-term"><?php echo $escape($term); ?></h4>
                                    <div class="table-wrap">
                                        <table class="grade-table">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Term 1</th>
                                                    <th>Term 2</th>
                                                    <th>Term 3</th>
                                                    <th>Final</th>
                                                    <th>Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subjects as $record): ?>
                                                    <?php
                                                    $final = (float) ($record['final_grade'] ?? 0);
                                                    $remarks = $record['remarks'];
                                                    if (empty($remarks)) {
                                                        $remarks = $final >= 75
                                                            ? 'Passed'
                                                            : ($final > 0 ? 'Failed' : 'Pending');
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo $escape($record['subject_name']); ?>
                                                            <span class="subject-code"><?php echo $escape($record['subject_code']); ?></span>
                                                        </td>
                                                        <td><?php echo $record['term1_transmuted'] !== null ? number_format((float) $record['term1_transmuted'], 1) : '-'; ?></td>
                                                        <td><?php echo $record['term2_transmuted'] !== null ? number_format((float) $record['term2_transmuted'], 1) : '-'; ?></td>
                                                        <td><?php echo $record['term3_transmuted'] !== null ? number_format((float) $record['term3_transmuted'], 1) : '-'; ?></td>
                                                        <td><?php echo $record['final_grade'] !== null && $final > 0 ? number_format($final, 1) : '-'; ?></td>
                                                        <td><?php echo $escape($remarks); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="archive-notice">
                        No past academic records were found for this account.
                    </div>
                <?php endif; ?>

                <div class="portal-actions">
                    <button type="button" class="btn-print" onclick="window.print()">Print Personal Copy</button>
                    <a href="../../manifest/logout.php" class="btn-logout">Secure Logout</a>
                </div>
            </div>
        </div>
        
        <div class="brand-footer">
            STRAND-SYNC | Student Record Management System
        </div>
    </div>

</body>
</html>