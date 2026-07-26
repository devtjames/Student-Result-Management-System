/**
 * SRMS — Main JavaScript (vanilla, no frameworks)
 */

/* ── Toast Notifications ─────────────────────────────────────────────────── */
function showToast(message, type = 'info', duration = 4000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const icons = { success: '✓', error: '✕', info: 'ℹ' };
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<span style="font-size:16px">${icons[type] || '•'}</span> ${message}`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'fadeOut .3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

/* ── Alert dismissal ─────────────────────────────────────────────────────── */
document.addEventListener('click', e => {
  if (e.target.classList.contains('alert-close')) {
    e.target.closest('.alert').remove();
  }
});

/* ── Modal helpers ───────────────────────────────────────────────────────── */
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('show'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('show'); document.body.style.overflow = ''; }
}
// Close modal on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('show');
    document.body.style.overflow = '';
  }
});
// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.show').forEach(m => {
      m.classList.remove('show');
      document.body.style.overflow = '';
    });
  }
});

/* ── Collapsible semester blocks ─────────────────────────────────────────── */
document.addEventListener('click', e => {
  const hdr = e.target.closest('.semester-header');
  if (!hdr) return;
  const body = hdr.nextElementSibling;
  if (body && body.classList.contains('semester-body')) {
    const isHidden = body.style.display === 'none';
    body.style.display = isHidden ? '' : 'none';
    const arrow = hdr.querySelector('.toggle-arrow');
    if (arrow) arrow.textContent = isHidden ? '▾' : '▸';
  }
});

/* ── Sidebar mobile overlay close ────────────────────────────────────────── */
document.addEventListener('click', e => {
  const sidebar = document.getElementById('sidebar');
  const toggle  = document.querySelector('.menu-toggle');
  if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
    sidebar.classList.remove('open');
  }
});

/* ── Generic AJAX form submit (login) ────────────────────────────────────── */
const loginForm = document.getElementById('login-form');
if (loginForm) {
  loginForm.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = loginForm.querySelector('[type=submit]');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Authenticating…';

    const data = new FormData(loginForm);
    try {
      const res  = await fetch(loginForm.action, { method: 'POST', body: data });
      const json = await res.json();
      if (json.success) {
        showToast('Login successful. Redirecting…', 'success');
        setTimeout(() => window.location = json.redirect, 800);
      } else {
        showToast(json.message || 'Login failed.', 'error');
        btn.disabled = false;
        btn.textContent = orig;
      }
    } catch (err) {
      showToast('Network error. Please try again.', 'error');
      btn.disabled = false;
      btn.textContent = orig;
    }
  });
}

/* ── Student result lookup ───────────────────────────────────────────────── */
async function submitStudentRegistration(e) {
  e.preventDefault();
  const form = e.target;
  if (form.dataset.submitting === '1') return false;
  form.dataset.submitting = '1';

  const btn = form.querySelector('[type=submit]');
  const orig = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Creating account...';

  const data = new FormData(form);
  try {
    const res = await fetch(form.action, {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message || 'Registration successful. Redirecting to login...', 'success');
      setTimeout(() => window.location = json.redirect || `${BASE_URL}/student_login.php?registered=1`, 1000);
    } else {
      showToast(json.message || 'Registration failed.', 'error');
      btn.disabled = false;
      btn.textContent = orig;
      form.dataset.submitting = '0';
    }
  } catch (err) {
    showToast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.textContent = orig;
    form.dataset.submitting = '0';
  }

  return false;
}

const studentRegisterForm = document.getElementById('student-register-form');
if (studentRegisterForm && !studentRegisterForm.hasAttribute('onsubmit')) {
  studentRegisterForm.addEventListener('submit', submitStudentRegistration);
}

async function loadStudentResults(e) {
  e.preventDefault();
  const form = e.target;
  const matricInput = document.getElementById('matric_input');
  const matric = form.dataset.matric || (matricInput ? matricInput.value.trim() : '');
  const session = document.getElementById('session_filter') ? document.getElementById('session_filter').value : '';
  const semester = document.getElementById('semester_filter') ? document.getElementById('semester_filter').value : '';
  const container = document.getElementById('result-container');
  const btn = form.querySelector('[type=submit]');
  const originalText = btn ? btn.textContent : '';

  if (!container) return false;

  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Loading...';
  }
  container.innerHTML = `<div class="loader-wrap"><div class="spinner"></div><p class="loader-text">Fetching results...</p></div>`;

  const params = new URLSearchParams();
  if (matric) params.append('matric_no', matric);
  if (session) params.append('session', session);
  if (semester) params.append('semester', semester);

  try {
    const res = await fetch(`${BASE_URL}/api/get_result.php?${params}`);
    const json = await res.json();
    if (json.success) {
      container.innerHTML = renderResultSheet(json.data);
      initGpaChart(json.data.gpa_history);
    } else {
      container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⊘</div><h3>No Results Found</h3><p>${json.message}</p></div>`;
    }
  } catch (err) {
    container.innerHTML = `<div class="alert alert-error">Failed to fetch results. Please try again.</div>`;
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = originalText || 'Load Results';
    }
  }

  return false;
}

const resultForm = document.getElementById('result-search-form');
if (resultForm && !resultForm.hasAttribute('onsubmit')) {
  resultForm.addEventListener('submit', async e => {
    e.preventDefault();
    const matricInput = document.getElementById('matric_input');
    const matric  = resultForm.dataset.matric || (matricInput ? matricInput.value.trim() : '');
    const session = document.getElementById('session_filter') ? document.getElementById('session_filter').value : '';
    const semester = document.getElementById('semester_filter') ? document.getElementById('semester_filter').value : '';

    const container = document.getElementById('result-container');
    container.innerHTML = `<div class="loader-wrap"><div class="spinner"></div><p class="loader-text">Fetching results…</p></div>`;

    const params = new URLSearchParams();
    if (matric) params.append('matric_no', matric);
    if (session)  params.append('session', session);
    if (semester) params.append('semester', semester);

    try {
      const res  = await fetch(`${BASE_URL}/api/get_result.php?${params}`);
      const json = await res.json();
      if (json.success) {
        container.innerHTML = renderResultSheet(json.data);
        initGpaChart(json.data.gpa_history);
      } else {
        container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⊘</div><h3>No Results Found</h3><p>${json.message}</p></div>`;
      }
    } catch (err) {
      container.innerHTML = `<div class="alert alert-error">Failed to fetch results. Please try again.</div>`;
    }
  });
}

/* ── Render result sheet HTML from JSON data ─────────────────────────────── */
function renderResultSheet(data) {
  const student = data.student;
  const sems    = data.semesters;

  const gradeClass = g => `badge badge-${g.toLowerCase()}`;
  const classColor = c => {
    if (c.includes('First'))        return '#238064';
    if (c.includes('Second Upper')) return '#1f5f8b';
    if (c.includes('Second Lower')) return '#6d5cae';
    if (c.includes('Third'))        return '#b7791f';
    if (c.includes('Pass'))         return '#c55a27';
    return '#b8323a';
  };

  let html = `
    <div class="cgpa-banner">
      <div>
        <div class="cgpa-main">
          <div class="cgpa-value">${data.final_cgpa.toFixed(2)}</div>
          <div class="cgpa-max">/ 5.00</div>
        </div>
        <div class="cgpa-class" style="color:${classColor(data.final_class)}">${data.final_class}</div>
      </div>
      <div class="student-info-grid" style="flex:1">
        <div class="info-item">
          <div class="info-label">Matric No</div>
          <div class="info-value accent">${student.matric_no}</div>
        </div>
        <div class="info-item">
          <div class="info-label">Full Name</div>
          <div class="info-value">${student.full_name}</div>
        </div>
        <div class="info-item">
          <div class="info-label">Department</div>
          <div class="info-value">${student.department}</div>
        </div>
      </div>
      <div class="cgpa-actions no-print">
        <button class="btn btn-ghost btn-sm" onclick="window.print()">🖨 Print</button>
        <button class="btn btn-secondary btn-sm" onclick="exportPDF()">⬇ PDF</button>
      </div>
    </div>`;

  // GPA Chart
  html += `<div class="card student-print-hide" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">GPA Trend</span></div>
    <div class="chart-box"><canvas id="gpa-chart"></canvas></div>
  </div>`;

  // Semester blocks
  sems.forEach((sem, idx) => {
    const semLabel = sem.semester === 1 ? '1st Semester' : '2nd Semester';
    html += `
    <div class="semester-block">
      <div class="semester-header">
        <div class="semester-label">
          <span class="toggle-arrow">▾</span>
          ${sem.level}L — ${semLabel} &nbsp;·&nbsp; ${sem.session}
        </div>
        <div class="semester-meta">
          <span class="gpa-chip">GPA: ${sem.gpa.toFixed(2)}</span>
          <span class="gpa-chip" style="background:#e9f2f8;color:#1f5f8b">CGPA: ${sem.cgpa.toFixed(2)}</span>
        </div>
      </div>
      <div class="semester-body">
        <div class="table-wrap" style="margin-top:16px">
          <table class="data-table">
            <thead>
              <tr>
                <th>Course Code</th>
                <th>Course Title</th>
                <th>Units</th>
                <th>Score</th>
                <th>Grade</th>
                <th>GP</th>
                <th>QP</th>
                <th>Remark</th>
              </tr>
            </thead>
            <tbody>`;

    sem.courses.forEach(c => {
      const carryTag = c.is_carryover ? `<span class="badge badge-carryover" style="margin-left:6px;font-size:10px">CARRY</span>` : '';
      html += `<tr>
        <td class="mono">${c.course_code}${carryTag}</td>
        <td>${c.course_title}</td>
        <td class="mono" style="text-align:center">${c.course_unit}</td>
        <td class="mono" style="text-align:center">${parseFloat(c.score).toFixed(1)}</td>
        <td><span class="${gradeClass(c.grade)}">${c.grade}</span></td>
        <td class="mono" style="text-align:center">${c.grade_point}</td>
        <td class="mono" style="text-align:center">${c.quality_point}</td>
        <td style="font-size:13px;color:var(--text-muted)">${c.remark}</td>
      </tr>`;
    });

    html += `</tbody></table></div></div>
      <div class="semester-footer">
        <div class="footer-item"><span class="footer-item-label">Total Units</span><span class="footer-item-value">${sem.sem_units}</span></div>
        <div class="footer-item"><span class="footer-item-label">Total QP</span><span class="footer-item-value">${sem.sem_qp}</span></div>
        <div class="footer-item"><span class="footer-item-label">GPA</span><span class="footer-item-value gpa-val">${sem.gpa.toFixed(2)}</span></div>
        <div class="footer-item"><span class="footer-item-label">CGPA</span><span class="footer-item-value gpa-val">${sem.cgpa.toFixed(2)}</span></div>
      </div>
    </div>`;
  });

  return html;
}

/* ── Render GPA chart ────────────────────────────────────────────────────── */
function initGpaChart(history) {
  const canvas = document.getElementById('gpa-chart');
  if (!canvas || !history || !history.length) return;
  const labels = history.map(h => h.label);
  const gpas   = history.map(h => h.gpa);
  const cgpas  = history.map(h => h.cgpa);
  new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'GPA',
          data: gpas,
          borderColor: '#1f5f8b',
          backgroundColor: 'rgba(31,95,139,.08)',
          pointBackgroundColor: '#1f5f8b',
          tension: .35, fill: true, pointRadius: 5
        },
        {
          label: 'CGPA',
          data: cgpas,
          borderColor: '#238064',
          backgroundColor: 'rgba(35,128,100,.07)',
          pointBackgroundColor: '#238064',
          tension: .35, fill: true, pointRadius: 5, borderDash: [5,4]
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { labels: { color: '#53627a', font: { family: 'DM Mono' } } },
        tooltip: { mode: 'index', intersect: false }
      },
      scales: {
        x: { ticks: { color: '#7a8798', font: { family: 'DM Mono', size: 11 } }, grid: { color: '#d7e0ea' } },
        y: { min: 0, max: 5, ticks: { color: '#7a8798', font: { family: 'DM Mono' } }, grid: { color: '#d7e0ea' } }
      }
    }
  });
}

/* ── PDF export via browser print ────────────────────────────────────────── */
function exportPDF() { window.print(); }

/* ── Number animation ────────────────────────────────────────────────────── */
function animateNumber(el, target, duration = 800) {
  const start = parseFloat(el.textContent) || 0;
  const step  = (target - start) / (duration / 16);
  let cur = start;
  const timer = setInterval(() => {
    cur += step;
    if ((step > 0 && cur >= target) || (step < 0 && cur <= target)) {
      cur = target;
      clearInterval(timer);
    }
    el.textContent = Number.isInteger(target) ? Math.round(cur) : cur.toFixed(2);
  }, 16);
}

function enhanceResponsiveTables(root = document) {
  root.querySelectorAll('.data-table').forEach(table => {
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    if (!headers.length) return;

    table.querySelectorAll('tbody tr').forEach(row => {
      Array.from(row.children).forEach((cell, index) => {
        if (cell.tagName !== 'TD' || cell.hasAttribute('colspan')) return;
        cell.dataset.label = headers[index] || '';
      });
    });
  });
}

function watchResponsiveTables() {
  enhanceResponsiveTables();
  const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType !== 1) return;
        if (node.matches?.('.data-table') || node.querySelector?.('.data-table')) {
          enhanceResponsiveTables(node.matches('.data-table') ? node.parentElement || document : node);
        }
      });
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });
}

// Animate stat values on load
document.addEventListener('DOMContentLoaded', () => {
  watchResponsiveTables();
  document.querySelectorAll('.stat-value[data-val]').forEach(el => {
    const val = parseFloat(el.dataset.val);
    animateNumber(el, val);
  });
});
