// assets/js/ecr-grid.js

document.addEventListener('DOMContentLoaded', function () {
  const table = document.querySelector('.ecr-table');
  table.addEventListener('input', calculateRowGrades);
  calculateRowGrades(); // Initial calculation trigger
});

function calculateRowGrades() {
  // 1. Fetch Highest Possible Scores
  const hpsWW = Array.from(
    document.querySelectorAll('.hps-input[id^="hps_ww"]'),
  ).map((i) => parseFloat(i.value) || 0);
  const hpsPT = Array.from(
    document.querySelectorAll('.hps-input[id^="hps_pt"]'),
  ).map((i) => parseFloat(i.value) || 0);
  const hpsQA = Array.from(
    document.querySelectorAll('.hps-input[id^="hps_qa"]'),
  ).map((i) => parseFloat(i.value) || 0);

  const totalHpsWW = hpsWW.reduce((a, b) => a + b, 0);
  const totalHpsPT = hpsPT.reduce((a, b) => a + b, 0);
  const totalHpsQA = hpsQA.reduce((a, b) => a + b, 0);

  document.getElementById('hps_ww_total').innerText = totalHpsWW;
  document.getElementById('hps_pt_total').innerText = totalHpsPT;
  document.getElementById('hps_qa_total').innerText = totalHpsQA;

  // 2. Iterate Student Rows
  const rows = document.querySelectorAll('.student-row');
  rows.forEach((row) => {
    // Calculate Written Works (20%)
    const wwScores = Array.from(row.querySelectorAll('.score-input.ww')).map(
      (i) => parseFloat(i.value) || 0,
    );
    const sumWW = wwScores.reduce((a, b) => a + b, 0);
    const psWW = totalHpsWW > 0 ? (sumWW / totalHpsWW) * 100 : 0;
    const wsWW = psWW * 0.2;

    row.querySelector('.total-ww').innerText = sumWW;
    row.querySelector('.ps-ww').innerText = psWW.toFixed(2);
    row.querySelector('.ws-ww').innerText = wsWW.toFixed(2) + '%';

    // Calculate Performance Tasks (50%)
    const ptScores = Array.from(row.querySelectorAll('.score-input.pt')).map(
      (i) => parseFloat(i.value) || 0,
    );
    const sumPT = ptScores.reduce((a, b) => a + b, 0);
    const psPT = totalHpsPT > 0 ? (sumPT / totalHpsPT) * 100 : 0;
    const wsPT = psPT * 0.5;

    row.querySelector('.total-pt').innerText = sumPT;
    row.querySelector('.ps-pt').innerText = psPT.toFixed(2);
    row.querySelector('.ws-pt').innerText = wsPT.toFixed(2) + '%';

    // Calculate Quarterly Assessment (30%)
    const qaScores = Array.from(row.querySelectorAll('.score-input.qa')).map(
      (i) => parseFloat(i.value) || 0,
    );
    const sumQA = qaScores.reduce((a, b) => a + b, 0);
    const psQA = totalHpsQA > 0 ? (sumQA / totalHpsQA) * 100 : 0;
    const wsQA = psQA * 0.3;

    row.querySelector('.total-qa').innerText = sumQA;
    row.querySelector('.ps-qa').innerText = psQA.toFixed(2);
    row.querySelector('.ws-qa').innerText = wsQA.toFixed(2) + '%';

    // Initial & Transmuted Grade
    const initialGrade = wsWW + wsPT + wsQA;
    row.querySelector('.initial-grade').innerText = initialGrade.toFixed(2);

    // Transmutation Logic Call
    const transmuted = getTransmutedGrade(initialGrade);
    row.querySelector('.transmuted-grade').innerText = transmuted;
    row.querySelector('.letter-grade').innerText = getLetterGrade(transmuted);
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
  document
    .querySelectorAll('.tab-btn')
    .forEach((btn) => btn.classList.remove('active'));
  document
    .querySelectorAll('.tab-content')
    .forEach((content) => content.classList.remove('active'));

  event.target.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}
