<?php
// modules/faculty/ecr-inputdata.php
// require_once '../../config/database.php';
// require_once '../../includes/header.php';

// Fetch existing section roster from database if available
$male_students = [];   // Populate from DB query later
$female_students = []; // Populate from DB query later
?>

<link rel="stylesheet" href="../../assets/css/style.css">

<div class="ecr-container">
    <!-- Header Banner -->
    <header class="ecr-header">
        <img src="../../assets/icons/kagawaran-logo.png" alt="Kagawaran Logo" class="logo-sm">
        <div class="header-text">
            <h2>Input Data Sheet for Electronic-Class Record (ECR)</h2>
            <p>Strengthened Senior High School System</p>
        </div>
        <img src="../../assets/icons/deped-logo.png" alt="DepEd Logo" class="logo-md">
    </header>

    <!-- Navigation Tabs -->
    <nav class="ecr-nav">
        <a href="ecr-inputdata.php" class="nav-tab active">INPUT DATA</a>
        <a href="ecr-view.php?term=1" class="nav-tab">TERM 1</a>
        <a href="ecr-view.php?term=2" class="nav-tab">TERM 2</a>
        <a href="ecr-view.php?term=3" class="nav-tab">TERM 3</a>
        <a href="ecr-summary.php" class="nav-tab tab-summary">SUMMARY</a>
    </nav>

    <form action="../../api/save-ecr-input.php" method="POST" class="input-layout">
        <!-- Left Column: School & Class Configuration -->
        <div class="config-column">
            <div class="ecr-card">
                <div class="card-title">SCHOOL INFO</div>
                <div class="card-content">
                    <div class="field-group">
                        <label>REGION:</label>
                        <input type="text" name="region" placeholder="e.g. Region III" class="form-input">
                    </div>
                    <div class="field-group">
                        <label>DIVISION:</label>
                        <input type="text" name="division" placeholder="e.g. Tarlac" class="form-input">
                    </div>
                    <div class="field-group">
                        <label>SCHOOL ID:</label>
                        <input type="text" name="school_id" placeholder="e.g. 301000" class="form-input">
                    </div>
                    <div class="field-group">
                        <label>SCHOOL NAME:</label>
                        <input type="text" name="school_name" placeholder="School Name" class="form-input">
                    </div>
                    <div class="field-group">
                        <label>SCHOOL YEAR:</label>
                        <input type="text" name="school_year" placeholder="2026-2027" class="form-input">
                    </div>
                </div>
            </div>

            <div class="ecr-card">
                <div class="card-title">CLASS & SUBJECT INFO</div>
                <div class="card-content">
                    <div class="field-group">
                        <label>TEACHER:</label>
                        <input type="text" name="teacher_name" placeholder="Teacher Name" class="form-input">
                    </div>
                    <div class="field-group">
                        <label>TRACK:</label>
                        <select name="track" class="form-input">
                            <option value="">-- Select Track --</option>
                            <option value="TECHNICAL">TECHNICAL PROFESSIONAL</option>
                            <option value="ACADEMIC">ACADEMIC</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>GRADE LEVEL:</label>
                        <select name="grade_level" class="form-input">
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>SECTION:</label>
                        <input type="text" name="section" placeholder="Section Name" class="form-input">
                    </div>
                    <div class="field-group">
                        <label>SUBJECT TYPE:</label>
                        <select name="subject_type" class="form-input">
                            <option value="Core">Core Subject</option>
                            <option value="Applied">Applied Subject</option>
                            <option value="Specialized">Specialized Subject</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>SUBJECT:</label>
                        <input type="text" name="subject_name" placeholder="Subject Title" class="form-input">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Dynamic Student Roster -->
        <div class="roster-column">
            <div class="ecr-card">
                <div class="card-title">LEARNERS' ROSTER</div>
                <div class="roster-tables">
                    <!-- Male Section -->
                    <div class="gender-block">
                        <div class="gender-tag male-tag">MALE</div>
                        <div class="roster-list">
                            <?php for ($i = 1; $i <= 25; $i++): ?>
                                <div class="roster-entry">
                                    <span class="entry-index"><?= $i ?></span>
                                    <input type="text" name="male_students[]" 
                                           value="<?= htmlspecialchars($male_students[$i-1] ?? '') ?>" 
                                           placeholder="Last Name, First Name M.I." class="roster-field">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Female Section -->
                    <div class="gender-block">
                        <div class="gender-tag female-tag">FEMALE</div>
                        <div class="roster-list">
                            <?php for ($i = 1; $i <= 25; $i++): ?>
                                <div class="roster-entry">
                                    <span class="entry-index"><?= $i ?></span>
                                    <input type="text" name="female_students[]" 
                                           value="<?= htmlspecialchars($female_students[$i-1] ?? '') ?>" 
                                           placeholder="Last Name, First Name M.I." class="roster-field">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-save">Save Roster & Setup</button>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>