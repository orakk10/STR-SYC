/**
 * assets/js/ecr-grid.js
 * E-Class Record Interactive Grid & Real-time Transmutation Engine
 */

// 1. Core Transmutation Engine (Mirrors core/DepEdTransmutation.php)
const DepEdTransmutation = {
    transmuteTable: [
        { min: 100.00, grade: 100 }, { min: 98.40, grade: 99 }, { min: 96.80, grade: 98 },
        { min: 95.20, grade: 97 },  { min: 93.60, grade: 96 }, { min: 92.00, grade: 95 },
        { min: 90.40, grade: 94 },  { min: 88.80, grade: 93 }, { min: 87.20, grade: 92 },
        { min: 85.60, grade: 91 },  { min: 84.00, grade: 90 }, { min: 82.40, grade: 89 },
        { min: 80.80, grade: 88 },  { min: 79.20, grade: 87 }, { min: 77.60, grade: 86 },
        { min: 76.00, grade: 85 },  { min: 74.40, grade: 84 }, { min: 72.80, grade: 83 },
        { min: 71.20, grade: 82 },  { min: 69.60, grade: 81 }, { min: 68.00, grade: 80 },
        { min: 66.40, grade: 79 },  { min: 64.80, grade: 78 }, { min: 63.20, grade: 77 },
        { min: 61.60, grade: 76 },  { min: 60.00, grade: 75 }, { min: 56.00, grade: 74 },
        { min: 52.00, grade: 73 },  { min: 48.00, grade: 72 }, { min: 44.00, grade: 71 },
        { min: 40.00, grade: 70 },  { min: 36.00, grade: 69 }, { min: 32.00, grade: 68 },
        { min: 28.00, grade: 67 },  { min: 24.00, grade: 66 }, { min: 20.00, grade: 65 },
        { min: 16.00, grade: 64 },  { min: 12.00, grade: 63 }, { min:  8.00, grade: 62 },
        { min:  4.00, grade: 61 },  { min:  0.00, grade: 60 }
    ],

    transmute(initialGrade) {
        const score = Math.round(parseFloat(initialGrade) * 100) / 100;
        for (const row of this.transmuteTable) {
            if (score >= row.min) return row.grade;
        }
        return 60;
    },

    getLetterGrade(transmutedGrade, useShortCode = false) {
        const grade = parseInt(transmutedGrade, 10);
        if (grade >= 90) return 'O';
        if (grade >= 85) return 'VS';
        if (grade >= 80) return 'S';
        if (grade >= 75) return 'FS';
        return useShortCode ? 'DNM' : 'Did Not Meet Expectations';
    },

    getRemark(transmutedGrade) {
        return parseInt(transmutedGrade, 10) >= 75 ? 'Passed' : 'Failed';
    }
};

// 2. DOM Event Listeners & Grid Data Processing
document.addEventListener('DOMContentLoaded', () => {
    const assignmentId = document.getElementById('assignment_id')?.value;
    const activeTerm = document.getElementById('active_term')?.value || 'Term 1';

    // Subject component percentage weights (e.g., Core: 25% WW, 50% PT, 25% QA)
    const weights = {
        WW: parseFloat(document.getElementById('weight_ww')?.value || 0.25),
        PT: parseFloat(document.getElementById('weight_pt')?.value || 0.50),
        QA: parseFloat(document.getElementById('weight_qa')?.value || 0.25)
    };

    /**
     * Recalculates Percentage Scores (PS), Weighted Scores (WS),
     * Initial Grade, Transmuted Grade, and Descriptors for a student row.
     */
    function recalculateRow(row) {
        const categories = ['WW', 'PT', 'QA'];
        let initialGrade = 0;

        categories.forEach(cat => {
            let totalScore = 0;
            let totalHps = 0;

            row.querySelectorAll(`.score-input[data-category="${cat}"]`).forEach(input => {
                const componentId = input.dataset.componentId;
                const score = parseFloat(input.value) || 0;
                const hps = parseFloat(document.querySelector(`.hps-input[data-component-id="${componentId}"]`)?.value || 0);

                totalScore += score;
                totalHps += hps;
            });

            const ps = totalHps > 0 ? (totalScore / totalHps) * 100 : 0;
            const ws = ps * (weights[cat] || 0);
            initialGrade += ws;

            const psCell = row.querySelector(`.ps-cell[data-category="${cat}"]`);
            const wsCell = row.querySelector(`.ws-cell[data-category="${cat}"]`);
            
            if (psCell) psCell.textContent = ps.toFixed(2);
            if (wsCell) wsCell.textContent = ws.toFixed(2);
        });

        const transmuted = DepEdTransmutation.transmute(initialGrade);
        const descriptor = DepEdTransmutation.getLetterGrade(transmuted);
        const remark = DepEdTransmutation.getRemark(transmuted);

        const initCell = row.querySelector('.initial-grade-cell');
        const transCell = row.querySelector('.transmuted-grade-cell');
        const descCell = row.querySelector('.descriptor-cell');
        const remarkCell = row.querySelector('.remark-cell');

        if (initCell) initCell.textContent = initialGrade.toFixed(2);
        if (transCell) transCell.textContent = transmuted;
        if (descCell) descCell.textContent = descriptor;
        if (remarkCell) {
            remarkCell.textContent = remark;
            remarkCell.className = `remark-cell ${remark.toLowerCase()}`;
        }
    }

    // Attach real-time input listeners to all score fields
    document.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', (e) => {
            const row = e.target.closest('tr');
            if (row) recalculateRow(row);
        });
    });

    // 3. Save Configuration Handler (api/faculty/save-ecr-input.php)
    const saveConfigBtn = document.getElementById('saveConfigBtn');
    if (saveConfigBtn) {
        saveConfigBtn.addEventListener('click', async () => {
            saveConfigBtn.disabled = true;
            saveConfigBtn.textContent = 'Saving HPS...';

            const payload = {
                assignment_id: parseInt(assignmentId, 10),
                components: []
            };

            document.querySelectorAll('.hps-input').forEach(input => {
                payload.components.push({
                    term: input.dataset.term || activeTerm,
                    category: input.dataset.category,
                    task_number: parseInt(input.dataset.taskNumber, 10),
                    highest_possible_score: parseFloat(input.value) || 0
                });
            });

            try {
                const response = await fetch('../../api/faculty/save-ecr-input.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    alert(result.message);
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                console.error(err);
                alert('A network error occurred while saving component configuration.');
            } finally {
                saveConfigBtn.disabled = false;
                saveConfigBtn.textContent = 'Save HPS Setup';
            }
        });
    }

    // 4. Save Student Scores & Term Summaries Handler (api/faculty/save-ecr-scores.php)
    const saveScoresBtn = document.getElementById('saveScoresBtn');
    if (saveScoresBtn) {
        saveScoresBtn.addEventListener('click', async () => {
            saveScoresBtn.disabled = true;
            saveScoresBtn.textContent = 'Saving Scores...';

            const payload = {
                assignment_id: parseInt(assignmentId, 10),
                scores: [],
                summaries: []
            };

            // Aggregate raw score inputs
            document.querySelectorAll('.score-input').forEach(input => {
                payload.scores.push({
                    component_id: parseInt(input.dataset.componentId, 10),
                    student_id: parseInt(input.dataset.studentId, 10),
                    score: parseFloat(input.value) || 0
                });
            });

            // Aggregate row initial grade summaries
            document.querySelectorAll('.student-row').forEach(row => {
                const studentId = parseInt(row.dataset.studentId, 10);
                const initialGrade = parseFloat(row.querySelector('.initial-grade-cell')?.textContent) || 0;

                const summaryItem = { student_id: studentId };

                if (activeTerm === 'Term 1') summaryItem.term1_initial = initialGrade;
                else if (activeTerm === 'Term 2') summaryItem.term2_initial = initialGrade;
                else if (activeTerm === 'Term 3') summaryItem.term3_initial = initialGrade;

                payload.summaries.push(summaryItem);
            });

            try {
                const response = await fetch('../../api/faculty/save-ecr-scores.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    alert(result.message);
                } else {
                    alert('Save Failed: ' + result.message);
                }
            } catch (err) {
                console.error(err);
                alert('A network error occurred while saving student scores.');
            } finally {
                saveScoresBtn.disabled = false;
                saveScoresBtn.textContent = 'Save ECR Scores';
            }
        });
    }
});