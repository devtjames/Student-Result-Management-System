<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_student_login();

$matric = sanitize_matric($_SESSION['student_matric'] ?? '');
$student = db_row('SELECT * FROM students WHERE matric_no = ?', [$matric]);
if (!$student) {
    student_logout();
    redirect(BASE_URL . '/student_login.php');
}

$sessions = db_all(
    'SELECT DISTINCT academic_session FROM results_raw WHERE matric_no = ? ORDER BY academic_session DESC',
    [$matric]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Secure SRMS student result portal">
<title>My Results - SRMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<div class="student-portal">

  <header class="portal-header no-print">
    <div class="portal-logo">
      <span class="portal-logo-icon">●</span>
      <span class="portal-logo-text">SRMS Portal</span>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
      <span class="admin-name"><?= clean($student['full_name']) ?></span>
      <a href="<?= BASE_URL ?>/api/student_logout.php" class="btn-ghost" style="font-size:13px;color:var(--text-muted)">Log Out</a>
    </div>
  </header>

  <div class="portal-body">
    <div class="search-box no-print">
      <h2>My Academic Results</h2>
      <p>View your semester results, GPA, and CGPA after secure login.</p>

      <form id="result-search-form" data-matric="<?= clean($student['matric_no']) ?>" onsubmit="return loadStudentResults(event)">
        <div class="result-filter-actions">
          <select id="session_filter" class="form-control" style="width:180px">
            <option value="">All Sessions</option>
            <?php foreach ($sessions as $s): ?>
            <option value="<?= clean($s['academic_session']) ?>"><?= clean($s['academic_session']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="semester_filter" class="form-control" style="width:160px">
            <option value="">All Semesters</option>
            <option value="1">1st Semester</option>
            <option value="2">2nd Semester</option>
          </select>
          <button type="submit" class="btn btn-primary result-load-btn">Load Results</button>
        </div>
      </form>
    </div>

    <div id="result-container"></div>

    <details class="student-print-hide" style="margin-top:24px">
      <summary style="cursor:pointer;color:var(--text-muted);font-size:14px;padding:12px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius)">
        Grading Scale & Classification Guide
      </summary>
      <div class="grid-2" style="margin-top:12px;gap:12px">
        <div class="card" style="padding:20px">
          <div class="card-title" style="margin-bottom:12px">Score to Grade</div>
          <table class="data-table">
            <thead><tr><th>Score</th><th>Grade</th><th>GP</th><th>Remark</th></tr></thead>
            <tbody>
              <?php foreach (GRADE_SCALE as $g): ?>
              <tr>
                <td class="mono"><?= $g['min'] ?>-<?= $g['max'] ?></td>
                <td><span class="badge badge-<?= strtolower($g['grade']) ?>"><?= $g['grade'] ?></span></td>
                <td class="mono"><?= $g['gp'] ?></td>
                <td style="font-size:13px;color:var(--text-muted)"><?= $g['remark'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card" style="padding:20px">
          <div class="card-title" style="margin-bottom:12px">CGPA to Classification</div>
          <table class="data-table">
            <thead><tr><th>CGPA</th><th>Classification</th></tr></thead>
            <tbody>
              <?php foreach (CGPA_CLASS as $c): ?>
              <tr>
                <td class="mono"><?= $c['min'] ?> - <?= $c['max'] ?></td>
                <td style="font-weight:600"><?= $c['class'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </details>
  </div>
</div>

<div id="toast-container"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('result-search-form');
  if (form) loadStudentResults({ preventDefault() {}, target: form });
});
</script>
</body>
</html>
