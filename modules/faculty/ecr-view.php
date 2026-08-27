<?php
// modules/faculty/ecr-view.php
// require_once '../../config/database.php';
// require_once '../../includes/header.php';

$males = [];
$females = [];
?>

<link rel="stylesheet" href="../../assets/css/style.css">

<div class="ecr-container">
    <!-- Flexible Banner Header -->
    <header class="ecr-header">
        <div class="brand-block">
            <img src="../../assets/icons/kagawaran-logo.png" alt="Kagawaran Logo" class="logo-sm">
            <div class="header-text">
                <h2>SHS Class Record</h2>
                <p>DepEd Order No. 15, s. 2026</p>
            </div>
        </div>
        <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" class="logo-md">
    </header>

    <!-- Mobile Touch-Scroll Navigation -->
    <nav class="ecr-nav">
        <a href="ecr-inputdata.php" class="nav-tab">INPUT DATA</a>
        <a href="ecr-view.php?term=1" class="nav-tab active">TERM 1</a>
        <a href="ecr-view.php?term=2" class="nav-tab">TERM 2</a>
        <a href="ecr-view.php?term=3" class="nav-tab">TERM 3</a>
        <a href="ecr-summary.php" class="nav-tab tab-summary">SUMMARY</a>
    </nav>

    <!-- Mobile Scrollable Table Wrapper -->
    <div class="table-responsive-wrapper">
        <table class="ecr-grid">
            <thead>
                <tr>
                    <th rowspan="3" class="col-sticky student-col">LEARNERS' NAMES</th>
                    <th colspan="8" class="head-ww">WRITTEN WORKS (20%)</th>
                    <th colspan="6" class="head-pt">PERFORMANCE (50%)</th>
                    <th colspan="6" class="head-qa">EXAMS (30%)</th>
                    <th rowspan="3" class="col-calc">Initial</th>
                    <th rowspan="3" class="col-calc">Transmuted</th>
                    <th rowspan="3" class="col-calc">Grade</th>
                </tr>
                <tr>
                    <th>1</th>
                    <th>2</th>
                    <th>3</th>
                    <th>4</th>
                    <th>5</th>
                    <th>Total</th>
                    <th>PS</th>
                    <th>WS</th>
                    <th>1</th>
                    <th>2</th>
                    <th>3</th>
                    <th>Total</th>
                    <th>PS</th>
                    <th>WS</th>
                    <th>SA1</th>
                    <th>SA2</th>
                    <th>TE</th>
                    <th>Total</th>
                    <th>PS</th>
                    <th>WS</th>
                </tr>
                <tr class="hps-row">
                    <td>HIGHEST POSSIBLE SCORE</td>
                    <td><input type="number" class="grid-cell hps" id="hps_ww1" value="0"></td>
                    <td><input type="number" class="grid-cell hps" id="hps_ww2" value="0"></td>
                    <td><input type="number" class="grid-cell hps" id="hps_ww3" value="0"></td>
                    <td><input type="number" class="grid-cell hps" id="hps_ww4" value="0"></td>
                    <td><input type="number" class="grid-cell hps" id="hps_ww5" value="0"></td>
                    <td id="hps_ww_total" class="calc-val">0</td>
                    <td class="calc-val">100</td>
                    <td class="calc-val">20%</td>

                    <td><input type="number" class="grid-cell hps" id="hps_pt1" value="0"></td>
                    <td><input type="number" class="grid-cell hps" id="hps_pt2" value="0"></td>
                    <td><input type="number" class="grid-cell hps" id="hps_pt3" value="0"></td>
                    <td id="hps_pt_total" class="calc-val">0</td>
                    <td class="calc-val">100</td>
                    <td class="calc-val">50%</td>

                    <td><input type="number" class="grid-cell hps" id="hps_qa1" value="0"></td>
                    <td><input type="number" class="grid-cell hps" id="hps_qa2" value="0"></td>
                    <td><input type="number" class="grid-cell hps" id="hps_qa3" value="0"></td>
                    <td id="hps_qa_total" class="calc-val">0</td>
                    <td class="calc-val">100</td>
                    <td class="calc-val">30%</td>
                </tr>
            </thead>
            <tbody>
                <tr class="group-header">
                    <td colspan="24">MALE</td>
                </tr>
                <?php if (empty($males)): ?>
                    <tr class="empty-row">
                        <td colspan="24">No male students registered.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($males as $index => $student): ?>
                        <tr class="student-row" data-id="<?= $student['id'] ?>">
                            <td class="col-sticky student-col"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                            <td><input type="number" class="grid-cell score ww"></td>
                            <td><input type="number" class="grid-cell score ww"></td>
                            <td><input type="number" class="grid-cell score ww"></td>
                            <td><input type="number" class="grid-cell score ww"></td>
                            <td><input type="number" class="grid-cell score ww"></td>
                            <td class="total-ww calc-val">0</td>
                            <td class="ps-ww calc-val">0.00</td>
                            <td class="ws-ww calc-val">0.00%</td>

                            <td><input type="number" class="grid-cell score pt"></td>
                            <td><input type="number" class="grid-cell score pt"></td>
                            <td><input type="number" class="grid-cell score pt"></td>
                            <td class="total-pt calc-val">0</td>
                            <td class="ps-pt calc-val">0.00</td>
                            <td class="ws-pt calc-val">0.00%</td>

                            <td><input type="number" class="grid-cell score qa"></td>
                            <td><input type="number" class="grid-cell score qa"></td>
                            <td><input type="number" class="grid-cell score qa"></td>
                            <td class="total-qa calc-val">0</td>
                            <td class="ps-qa calc-val">0.00</td>
                            <td class="ws-qa calc-val">0.00%</td>

                            <td class="initial-grade calc-val highlight">0.00</td>
                            <td class="transmuted-grade calc-val highlight-main">60</td>
                            <td class="letter-grade calc-val">Did Not Meet</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <tr class="group-header">
                    <td colspan="24">FEMALE</td>
                </tr>
                <?php if (empty($females)): ?>
                    <tr class="empty-row">
                        <td colspan="24">No female students registered.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($females as $index => $student): ?>
                        <tr class="student-row" data-id="<?= $student['id'] ?>">
                            <td class="col-sticky student-col"><?= ($index + 1) . '. ' . htmlspecialchars($student['name']) ?></td>
                            <!-- Same input cells structure -->
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="../../assets/js/ecr-grid.js"></script>
<?php require_once '../../includes/footer.php'; ?>