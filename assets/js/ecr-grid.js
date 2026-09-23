// assets/js/ecr-grid.js

document.addEventListener('DOMContentLoaded', function () {
  // Target either .ecr-grid or .ecr-table depending on view markup
  const table = document.querySelector('.ecr-grid') || document.querySelector('.ecr-table');
  if (table) {
    table.addEventListener('input', calculateRowGrades);
  }
  calculateRowGrades(); // Initial calculation trigger
});

function calculateRowGrades() {
  // 1. Fetch Highest Possible Scores (supports both .hps and .hps-input class names)
  const hpsWW = Array.from(
    document.querySelectorAll('.hps[id^="hps_ww"], .hps-input[id^="hps_ww"]')
  ).map((i) => parseFloat(i.value) || 0);

  const hpsPT = Array.from(
    document.querySelectorAll('.hps[id^="hps_pt"], .hps-input[id^="hps_pt"]')
  ).map((i) => parseFloat(i.value) || 0);

  const hpsQA = Array.from(
    document.querySelectorAll('.hps[id^="hps_qa"], .hps-input[id^="hps_qa"]')
  ).map((i) => parseFloat(i.value) || 0);

  const totalHpsWW = hpsWW.reduce((a, b) => a + b, 0);
  const totalHpsPT = hpsPT.reduce((a, b) => a + b, 0);
  const totalHpsQA = hpsQA.reduce((a, b) => a + b, 0);

  if (document.getElementById('hps_ww_total')) document.getElementById('hps_ww_total').innerText = totalHpsWW;
  if (document.getElementById('hps_pt_total')) document.getElementById('hps_pt_total').innerText = totalHpsPT;
  if (document.getElementById('hps_qa_total')) document.getElementById('hps_qa_total').innerText = totalHpsQA;

  // 2. Iterate Student Rows
  const rows = document.querySelectorAll('.student-row');
  rows.forEach((row) => {
    // Calculate Written Works (20%)
    const wwScores = Array.from(row.querySelectorAll('.score.ww, .score-input.ww')).map(
      (i) => parseFloat(i.value) || 0
    );
    const sumWW = wwScores.reduce((a, b) => a + b, 0);
    const psWW = totalHpsWW > 0 ? (sumWW / totalHpsWW) * 100 : 0;
    const wsWW = psWW * 0.2;

    if (row.querySelector('.total-ww')) row.querySelector('.total-ww').innerText = sumWW;
    if (row.querySelector('.ps-ww')) row.querySelector('.ps-ww').innerText = psWW.toFixed(2);
    if (row.querySelector('.ws-ww')) row.querySelector('.ws-ww').innerText = wsWW.toFixed(2) + '%';

    // Calculate Performance Tasks (50%)
    const ptScores = Array.from(row.querySelectorAll('.score.pt, .score-input.pt')).map(
      (i) => parseFloat(i.value) || 0
    );
    const sumPT = ptScores.reduce((a, b) => a + b, 0);
    const psPT = totalHpsPT > 0 ? (sumPT / totalHpsPT) * 100 : 0;
    const wsPT = psPT * 0.5;

    if (row.querySelector('.total-pt')) row.querySelector('.total-pt').innerText = sumPT;
    if (row.querySelector('.ps-pt')) row.querySelector('.ps-pt').innerText = psPT.toFixed(2);
    if (row.querySelector('.ws-pt')) row.querySelector('.ws-pt').innerText = wsPT.toFixed(2) + '%';

    // Calculate Quarterly Assessment (30%)
    const qaScores = Array.from(row.querySelectorAll('.score.qa, .score-input.qa')).map(
      (i) => parseFloat(i.value) || 0
    );
    const sumQA = qaScores.reduce((a, b) => a + b, 0);
    const psQA = totalHpsQA > 0 ? (sumQA / totalHpsQA) * 100 : 0;
    const wsQA = psQA * 0.3;

    if (row.querySelector('.total-qa')) row.querySelector('.total-qa').innerText = sumQA;
    if (row.querySelector('.ps-qa')) row.querySelector('.ps-qa').innerText = psQA.toFixed(2);
    if (row.querySelector('.ws-qa')) row.querySelector('.ws-qa').innerText = wsQA.toFixed(2) + '%';

    // Initial & Transmuted Grade Calculation
    const initialGrade = wsWW + wsPT + wsQA;
    if (row.querySelector('.initial-grade')) {
      row.querySelector('.initial-grade').innerText = initialGrade.toFixed(2);
    }

    const transmuted = getTransmutedGrade(initialGrade);
    if (row.querySelector('.transmuted-grade')) {
      row.querySelector('.transmuted-grade').innerText = transmuted;
    }
    if (row.querySelector('.letter-grade')) {
      row.querySelector('.letter-grade').innerText = getLetterGrade(transmuted);
    }
  });
}

function getTransmutedGrade(initial) {
  if (initial >= 100) return 100;
  if (initial >= 98.4) return 99;
  if (initial >= 96.8) return 98;
  if (initial >= 95.2) return 97;
  if (initial >= 93.6) return 96;
  if (initial >= 92.0) return 95;
  if (initial >= 90.4) return 94;
  if (initial >= 88.8) return 93;
  if (initial >= 87.2) return 92;
  if (initial >= 85.6) return 91;
  if (initial >= 84.0) return 90;
  if (initial >= 82.4) return 89;
  if (initial >= 80.8) return 88;
  if (initial >= 79.2) return 87;
  if (initial >= 77.6) return 86;
  if (initial >= 76.0) return 85;
  if (initial >= 74.4) return 84;
  if (initial >= 72.8) return 83;
  if (initial >= 71.2) return 82;
  if (initial >= 69.6) return 81;
  if (initial >= 68.0) return 80;
  if (initial >= 66.4) return 79;
  if (initial >= 64.8) return 78;
  if (initial >= 63.2) return 77;
  if (initial >= 61.6) return 76;
  if (initial >= 60.0) return 75;
  return 60;
}

function getLetterGrade(transmuted) {
  if (transmuted >= 90) return 'O';
  if (transmuted >= 85) return 'VS';
  if (transmuted >= 80) return 'S';
  if (transmuted >= 75) return 'FS';
  return 'Did Not Meet Expectations';
}

function switchTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach((btn) => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach((content) => content.classList.remove('active'));

  if (event && event.target) {
    event.target.classList.add('active');
  }
  const targetElement = document.getElementById(tabId);
  if (targetElement) {
    targetElement.classList.add('active');
  }
}

/**
 * Batch AJAX Score Persistence Handler
 * Sends populated student scores and term averages to api/save-ecr-scores.php
 */
function saveEcrScores(sectionId, subjectId, term) {
  const scoresPayload = [];
  const summariesPayload = [];

  const studentRows = document.querySelectorAll('.student-row');
  studentRows.forEach((row) => {
    const studentId = row.getAttribute('data-id');

    // Written Works
    row.querySelectorAll('.score.ww, .score-input.ww').forEach((input, idx) => {
      if (input.value !== '') {
        scoresPayload.push({
          student_id: studentId,
          category: 'ww',
          item_no: idx + 1,
          score: input.value,
        });
      }
    });

    // Performance Tasks
    row.querySelectorAll('.score.pt, .score-input.pt').forEach((input, idx) => {
      if (input.value !== '') {
        scoresPayload.push({
          student_id: studentId,
          category: 'pt',
          item_no: idx + 1,
          score: input.value,
        });
      }
    });

    // Quarterly Assessments
    row.querySelectorAll('.score.qa, .score-input.qa').forEach((input, idx) => {
      if (input.value !== '') {
        scoresPayload.push({
          student_id: studentId,
          category: 'qa',
          item_no: idx + 1,
          score: input.value,
        });
      }
    });

    // Term Summaries
    const initialEl = row.querySelector('.initial-grade');
    const transmutedEl = row.querySelector('.transmuted-grade');

    const initial = initialEl ? parseFloat(initialEl.innerText) || 0 : 0;
    const transmuted = transmutedEl ? parseInt(transmutedEl.innerText) || 60 : 60;

    if (studentId) {
      summariesPayload.push({
        student_id: studentId,
        initial_grade: initial,
        transmuted_grade: transmuted,
      });
    }
  });

  fetch('../../api/save-ecr-scores.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      section_id: sectionId,
      subject_id: subjectId,
      term: term,
      scores: scoresPayload,
      summaries: summariesPayload,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        alert('✅ Term ' + term + ' scores saved successfully!');
      } else {
        alert('❌ Error saving scores: ' + data.message);
      }
    })
    .catch((err) => {
      console.error('Save error:', err);
      alert('❌ A system error occurred while communicating with the database.');
    });
}