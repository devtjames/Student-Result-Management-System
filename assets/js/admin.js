/**
 * SRMS — Admin JavaScript
 * Handles: file upload, dashboard charts, data tables, modals
 */

/* ── File Upload System ──────────────────────────────────────────────────── */
const dropZone   = document.getElementById('drop-zone');
const fileInput  = document.getElementById('file-input');
const uploadForm = document.getElementById('upload-form');
const progressSection = document.getElementById('progress-section');
const progressBar     = document.getElementById('progress-bar');
const progressLabel   = document.getElementById('progress-label');
const uploadResult    = document.getElementById('upload-result');

if (dropZone) {
  // Drag events
  ['dragenter','dragover'].forEach(evt => {
    dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
  });
  ['dragleave','drop'].forEach(evt => {
    dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('drag-over'); });
  });
  dropZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length) { fileInput.files = files; updateDropLabel(files[0].name); }
  });
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) updateDropLabel(fileInput.files[0].name);
  });
}

function updateDropLabel(name) {
  const label = document.getElementById('drop-label');
  if (label) label.textContent = '📄 ' + name;
}

if (uploadForm) {
  uploadForm.addEventListener('submit', async e => {
    e.preventDefault();
    if (!fileInput || !fileInput.files.length) {
      showToast('Please select an Excel file first.', 'error'); return;
    }
    const file = fileInput.files[0];
    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
      showToast('File exceeds 10 MB limit.', 'error'); return;
    }
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'xlsx') {
      showToast('Only .xlsx files are accepted.', 'error'); return;
    }

    // Show progress
    progressSection.style.display = 'block';
    uploadResult.innerHTML = '';
    setProgress(10, 'Uploading file…');

    const formData = new FormData(uploadForm);
    try {
      // Simulate progress while uploading
      let fakeProgress = 10;
      const fakeTimer = setInterval(() => {
        if (fakeProgress < 70) { fakeProgress += 5; setProgress(fakeProgress, 'Processing rows…'); }
      }, 300);

      const res  = await fetch(`${BASE_URL}/api/upload.php`, { method: 'POST', body: formData });
      clearInterval(fakeTimer);
      setProgress(90, 'Finalizing…');
      const json = await res.json();
      setProgress(100, 'Done.');

      if (json.success) {
        showToast('Upload successful!', 'success');
        renderUploadSummary(json);
      } else {
        showToast(json.message || 'Upload failed.', 'error');
        uploadResult.innerHTML = `<div class="alert alert-error">${json.message}</div>`;
      }
    } catch (err) {
      setProgress(0, 'Upload failed.');
      showToast('Network error during upload.', 'error');
    }
  });
}

function setProgress(pct, label) {
  if (progressBar)   progressBar.style.width  = pct + '%';
  if (progressLabel) progressLabel.textContent = label;
}

function renderUploadSummary(json) {
  if (!uploadResult) return;
  uploadResult.innerHTML = `
    <div class="alert alert-success" style="flex-direction:column;align-items:flex-start;gap:8px">
      <strong>✓ Upload Complete</strong>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;width:100%;margin-top:8px">
        <div style="text-align:center">
          <div style="font-size:24px;font-weight:800;font-family:var(--font-display)">${json.total_rows}</div>
          <div style="font-size:12px;opacity:.7">Total Rows</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:24px;font-weight:800;font-family:var(--font-display);color:var(--green)">${json.inserted}</div>
          <div style="font-size:12px;opacity:.7">Inserted</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:24px;font-weight:800;font-family:var(--font-display);color:var(--yellow)">${json.updated}</div>
          <div style="font-size:12px;opacity:.7">Updated</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:24px;font-weight:800;font-family:var(--font-display);color:var(--red)">${json.skipped}</div>
          <div style="font-size:12px;opacity:.7">Skipped</div>
        </div>
      </div>
      ${json.errors && json.errors.length ? `<details style="margin-top:8px;font-size:13px"><summary style="cursor:pointer">⚠ ${json.errors.length} row error(s)</summary><ul style="margin-top:8px;padding-left:16px">${json.errors.map(e => `<li>${e}</li>`).join('')}</ul></details>` : ''}
    </div>`;
}

/* ── Dashboard Charts ────────────────────────────────────────────────────── */
async function loadDashboard() {
  if (!document.getElementById('dash-gpa-chart')) return;
  try {
    const res  = await fetch(`${BASE_URL}/api/dashboard_stats.php`);
    const json = await res.json();
    if (!json.success) return;
    const d = json.data;

    // Stat values
    animateStat('stat-students',   d.total_students);
    animateStat('stat-courses',    d.total_courses);
    animateStat('stat-results',    d.total_results);
    animateStat('stat-carryovers', d.carryovers);

    // Grade distribution (donut)
    buildGradeChart(d.grade_dist);
    // GPA distribution histogram
    buildGpaHistogram(d.gpa_buckets);
    // Pass/fail pie
    buildPassFailChart(d.pass_fail);
    // Top students table
    renderTopStudents(d.top_students);
  } catch (e) {
    console.error('Dashboard load error', e);
  }
}

function animateStat(id, val) {
  const el = document.getElementById(id);
  if (el) animateNumber(el, val);
}

function buildGradeChart(dist) {
  const canvas = document.getElementById('dash-grade-chart');
  if (!canvas || !dist) return;
  const labels = dist.map(d => `Grade ${d.grade}`);
  const counts = dist.map(d => d.count);
  const colors = ['#238064','#1f5f8b','#b7791f','#c55a27','#b8323a','#8f1f2d'];
  new Chart(canvas, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: counts, backgroundColor: colors, borderWidth: 2, borderColor: '#ffffff' }] },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'right', labels: { color: '#53627a', font: { family: 'DM Mono', size: 12 }, padding: 14 } },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}` } }
      },
      cutout: '60%'
    }
  });
}

function buildGpaHistogram(buckets) {
  const canvas = document.getElementById('dash-gpa-chart');
  if (!canvas || !buckets) return;
  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: buckets.map(b => b.range),
      datasets: [{
        label: 'Students',
        data: buckets.map(b => b.count),
        backgroundColor: 'rgba(31,95,139,.18)',
        borderColor: '#1f5f8b',
        borderWidth: 2,
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#7a8798', font: { family: 'DM Mono', size: 11 } }, grid: { color: '#d7e0ea' } },
        y: { ticks: { color: '#7a8798', font: { family: 'DM Mono' } }, grid: { color: '#d7e0ea' } }
      }
    }
  });
}

function buildPassFailChart(pf) {
  const canvas = document.getElementById('dash-passfail-chart');
  if (!canvas || !pf) return;
  new Chart(canvas, {
    type: 'pie',
    data: {
      labels: ['Pass', 'Fail'],
      datasets: [{ data: [pf.pass, pf.fail], backgroundColor: ['#238064','#b8323a'], borderWidth: 2, borderColor: '#ffffff' }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom', labels: { color: '#53627a', font: { family: 'DM Mono', size: 12 } } }
      }
    }
  });
}

function renderTopStudents(students) {
  const tbody = document.getElementById('top-students-tbody');
  if (!tbody || !students) return;
  if (!students.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px">No data yet</td></tr>';
    return;
  }
  tbody.innerHTML = students.map((s, i) => `
    <tr>
      <td><span style="font-family:var(--font-display);font-weight:800;font-size:18px;color:${i < 3 ? ['#b7791f','#7a8798','#c55a27'][i] : 'var(--text-muted)'}">${i + 1}</span></td>
      <td class="mono">${s.matric_no}</td>
      <td>${s.full_name}</td>
      <td>${s.department}</td>
      <td><span class="gpa-chip">${parseFloat(s.cgpa).toFixed(2)}</span></td>
    </tr>`).join('');
}

/* ── Student search in admin ─────────────────────────────────────────────── */
const studentSearch = document.getElementById('student-search');
if (studentSearch) {
  let searchTimer;
  studentSearch.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchStudents(studentSearch.value), 400);
  });
}

async function searchStudents(q) {
  const tbody = document.getElementById('students-tbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="6"><div class="loader-wrap" style="padding:20px"><div class="spinner"></div></div></td></tr>`;
  const res  = await fetch(`${BASE_URL}/api/students_list.php?q=${encodeURIComponent(q)}`);
  const json = await res.json();
  if (!json.success || !json.data.length) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">No students found.</td></tr>`;
    return;
  }
  tbody.innerHTML = json.data.map(s => `
    <tr>
      <td class="mono">${s.matric_no}</td>
      <td>${s.full_name}</td>
      <td>${s.department}</td>
      <td>${s.programme || '—'}</td>
      <td>${s.admission_year || '—'}</td>
      <td>
        <a href="${BASE_URL}/admin/view_student.php?matric=${encodeURIComponent(s.matric_no)}" class="btn btn-ghost btn-sm">View</a>
        <button class="btn btn-secondary btn-sm" onclick="editStudent('${s.matric_no}')">Edit</button>
      </td>
    </tr>`).join('');
}

/* ── Add/Edit Student modal ──────────────────────────────────────────────── */
function editStudent(matricNo) {
  if (!matricNo) {
    // New student
    document.getElementById('student-form').reset();
    document.getElementById('student-modal-title').textContent = 'Add New Student';
    openModal('student-modal');
    return;
  }
  // Fetch and populate
  fetch(`${BASE_URL}/api/get_student.php?matric=${encodeURIComponent(matricNo)}`)
    .then(r => r.json())
    .then(json => {
      if (!json.success) { showToast('Student not found.', 'error'); return; }
      const s  = json.data;
      const fm = document.getElementById('student-form');
      fm.querySelector('[name=matric_no]').value       = s.matric_no;
      fm.querySelector('[name=full_name]').value       = s.full_name;
      fm.querySelector('[name=department]').value      = s.department;
      fm.querySelector('[name=programme]').value       = s.programme || '';
      fm.querySelector('[name=admission_year]').value  = s.admission_year || '';
      fm.querySelector('[name=email]').value           = s.email || '';
      document.getElementById('student-modal-title').textContent = 'Edit Student';
      openModal('student-modal');
    });
}

const studentForm = document.getElementById('student-form');
if (studentForm) {
  studentForm.addEventListener('submit', async e => {
    e.preventDefault();
    const fd   = new FormData(studentForm);
    const res  = await fetch(`${BASE_URL}/api/save_student.php`, { method: 'POST', body: fd });
    const json = await res.json();
    showToast(json.message, json.success ? 'success' : 'error');
    if (json.success) { closeModal('student-modal'); searchStudents(''); }
  });
}

/* ── Courses management ──────────────────────────────────────────────────── */
function editCourse(code) {
  if (!code) {
    document.getElementById('course-form').reset();
    document.getElementById('course-modal-title').textContent = 'Add New Course';
    openModal('course-modal');
    return;
  }
  fetch(`${BASE_URL}/api/get_course.php?code=${encodeURIComponent(code)}`)
    .then(r => r.json())
    .then(json => {
      if (!json.success) { showToast('Course not found.', 'error'); return; }
      const c  = json.data;
      const fm = document.getElementById('course-form');
      fm.querySelector('[name=course_code]').value  = c.course_code;
      fm.querySelector('[name=course_title]').value = c.course_title;
      fm.querySelector('[name=course_unit]').value  = c.course_unit;
      fm.querySelector('[name=semester]').value     = c.semester || '';
      fm.querySelector('[name=level]').value        = c.level || '';
      document.getElementById('course-modal-title').textContent = 'Edit Course';
      openModal('course-modal');
    });
}

const courseForm = document.getElementById('course-form');
if (courseForm) {
  courseForm.addEventListener('submit', async e => {
    e.preventDefault();
    const fd   = new FormData(courseForm);
    const res  = await fetch(`${BASE_URL}/api/save_course.php`, { method: 'POST', body: fd });
    const json = await res.json();
    showToast(json.message, json.success ? 'success' : 'error');
    if (json.success) { closeModal('course-modal'); location.reload(); }
  });
}

/* ── GPA Calculator ──────────────────────────────────────────────────────── */
function addCalcRow() {
  const tbody = document.getElementById('calc-tbody');
  if (!tbody) return;
  const row = document.createElement('tr');
  row.innerHTML = `
    <td><input type="text" class="form-control" placeholder="e.g. CSC101" style="width:100%"></td>
    <td><input type="number" class="form-control calc-unit" min="1" max="6" value="3" style="width:70px"></td>
    <td><input type="number" class="form-control calc-score" min="0" max="100" step="0.1" style="width:90px" oninput="calcUpdate()"></td>
    <td class="calc-grade mono" style="text-align:center">—</td>
    <td class="calc-qp mono" style="text-align:center">—</td>
    <td><button type="button" class="btn btn-danger btn-sm btn-icon" onclick="this.closest('tr').remove();calcUpdate()">✕</button></td>`;
  tbody.appendChild(row);
}

function calcUpdate() {
  const rows = document.querySelectorAll('#calc-tbody tr');
  let totalUnits = 0, totalQP = 0;

  const scale = [[70,5,'A'],[60,4,'B'],[50,3,'C'],[45,2,'D'],[40,1,'E'],[0,0,'F']];
  function getGrade(score) {
    for (const [min, gp, g] of scale) if (score >= min) return { grade: g, gp };
    return { grade: 'F', gp: 0 };
  }

  rows.forEach(row => {
    const scoreEl = row.querySelector('.calc-score');
    const unitEl  = row.querySelector('.calc-unit');
    const gradeEl = row.querySelector('.calc-grade');
    const qpEl    = row.querySelector('.calc-qp');
    if (!scoreEl || !unitEl) return;
    const score = parseFloat(scoreEl.value);
    const unit  = parseInt(unitEl.value) || 0;
    if (isNaN(score) || score === '') { gradeEl.textContent = '—'; qpEl.textContent = '—'; return; }
    const { grade, gp } = getGrade(score);
    const qp = gp * unit;
    gradeEl.textContent = grade;
    gradeEl.style.color = { A:'#238064',B:'#1f5f8b',C:'#b7791f',D:'#c55a27',E:'#b8323a',F:'#8f1f2d' }[grade];
    qpEl.textContent    = qp;
    totalUnits += unit;
    totalQP    += qp;
  });

  const gpa = totalUnits > 0 ? (totalQP / totalUnits).toFixed(2) : '—';
  const el  = document.getElementById('calc-gpa-result');
  if (el) el.textContent = gpa;
  const classEl = document.getElementById('calc-class-result');
  if (classEl && gpa !== '—') {
    const classes = [[4.5,'First Class Honours'],[3.5,'Second Class Upper'],[2.4,'Second Class Lower'],[1.5,'Third Class'],[1.0,'Pass'],[0,'Fail']];
    classEl.textContent = classes.find(([min]) => parseFloat(gpa) >= min)?.[1] || 'Fail';
  }
}

// Init dashboard on load
document.addEventListener('DOMContentLoaded', () => {
  loadDashboard();
});
