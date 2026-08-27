<?php
// modules/faculty/ecr-summary.php
// require_once '../../config/database.php';
// require_once '../../includes/header.php';
require_once '../../core/DepEdTransmutation.php';

// Dynamic database query placeholder
$males = [];   // DB array of male students with calculated term grades
$females = []; // DB array of female students with calculated term grades
?>

<link rel="stylesheet" href="../../assets/css/style.css">

<div class="ecr-container">
    <!-- Header Banner -->
    <header class="ecr-header">
        <img src="../../assets/icons/kagawaran-logo.png" alt="Kagawaran Logo" class="logo-sm">
        <div class="header-text">
            <h2>Final Grades Summary</h2>
            <p>Strengthened Senior High School Class Record</p>
        </div>
        <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" class="logo-md">
    </header>

    <!-- Navigation Tabs -->
    <nav class="ecr-nav">
        <a href="ecr-inputdata.php" class="nav-tab">INPUT DATA</a>
        <a href="ecr-view.php?term=1" class="nav-tab">TERM 1</a>
        <a href="ecr-view.php?term=2" class="nav-tab">TERM 2</a>
        <a href="ecr-view.php?term=3" class="nav-tab">TERM 3</a>
        <a href="ecr-summary.php" class="nav-tab tab-summary active">SUMMARY</a>
    </nav>

    <!-- Class Metadata Banner -->
    <div class="meta-banner">
        <div class="meta-item"><span>GRADE & SECTION:</span> 11 - ICT</div>
        <div class="meta-item"><span>TEACHER:</span> Juan Dela Cruz</div>
        <div class="meta-item"><span>SUBJECT:</span> Capstone Project</div>
        <div class="meta-item"><span>SUBJECT TYPE:</span> Core Subject (All Tracks)</div>
    </div>

    <!-- Tablet/Laptop Optimized Summary Matrix -->
    <div class="table-scroll-container">
        <table class="ecr-grid summary-grid">
            <thead>
                <tr>
                    <th class="col-sticky student-col">LEARNERS' NAMES</th>
                    <th class="head-term">TERM 1</th>
                    <th class="head-term">TERM 2</th>
                    <th class="head-term">TERM 3</th>
                    <th class="head-avg">AVERAGE</th>
                    <th class="head-letter">LETTER GRADE</th>
                    <th class="head-remarks">REMARKS</th>
                </tr>
            </thead>
            <tbody>
                <!-- Male Students Group -->
                <tr class="group-header"><td colspan="7">MALE</td></tr>
                <?php if (empty($males)): ?>
                    <tr class="empty-row"><td colspan="7">No male students configured. Add roster in Input Data tab.</td></tr>
                <?php else: ?>
                    <?php foreach ($males as $index => $student): ?>
                        <?php 
                            // Fetch grades dynamically from database/calculations
                            $t1 = $student['term1'] ?? 0;
                            $t2 = $student['term2'] ?? 0;
                            $t3 = $student['term3'] ?? 0;
                            
                            $average = ($t1 && $t2 && $t3) ? round(($t1 + $t2 + $t3) / 3, 2) : 0;
                            $letter = $average ? DepEdTransmutation::getLetterGrade($average) : '-';
                            $remarks = $average ? ($average >= 75 ? 'PASSED' : 'FAILED') : '-';
                        ?>
                        <tr class="summary-row" data-id="<?= $student['id'] ?>">
                            <td class="col-sticky student-col"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                            <td class="term-val"><?= $t1 ?: '-' ?></td>
                            <td class="term-val"><?= $t2 ?: '-' ?></td>
                            <td class="term-val"><?= $t3 ?: '-' ?></td>
                            <td class="calc-val highlight-main"><?= $average ?: '-' ?></td>
                            <td class="letter-val"><?= $letter ?></td>
                            <td class="remarks-val <?= strtolower($remarks) ?>"><?= $remarks ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Female Students Group -->
                <tr class="group-header"><td colspan="7">FEMALE</td></tr>
                <?php if (empty($females)): ?>
                    <tr class="empty-row"><td colspan="7">No female students configured. Add roster in Input Data tab.</td></tr>
                <?php else: ?>
                    <?php foreach ($females as $index => $student): ?>
                        <?php 
                            $t1 = $student['term1'] ?? 0;
                            $t2 = $student['term2'] ?? 0;
                            $t3 = $student['term3'] ?? 0;
                            
                            $average = ($t1 && $t2 && $t3) ? round(($t1 + $t2 + $t3) / 3, 2) : 0;
                            $letter = $average ? DepEdTransmutation::getLetterGrade($average) : '-';
                            $remarks = $average ? ($average >= 75 ? 'PASSED' : 'FAILED') : '-';
                        ?>
                        <tr class="summary-row" data-id="<?= $student['id'] ?>">
                            <td class="col-sticky student-col"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                            <td class="term-val"><?= $t1 ?: '-' ?></td>
                            <td class="term-val"><?= $t2 ?: '-' ?></td>
                            <td class="term-val"><?= $t3 ?: '-' ?></td>
                            <td class="calc-val highlight-main"><?= $average ?: '-' ?></td>
                            <td class="letter-val"><?= $letter ?></td>
                            <td class="remarks-val <?= strtolower($remarks) ?>"><?= $remarks ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>