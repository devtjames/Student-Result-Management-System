<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$matric = sanitize_matric($_GET['matric'] ?? '');
if (!$matric) redirect(BASE_URL . '/admin/students.php');

$student = db_row('SELECT * FROM students WHERE matric_no = ?', [$matric]);
if (!$student) {
    flash('error', 'Student not found.');
    redirect(BASE_URL . '/admin/students.php');
}

$resultData = get_student_results($matric);

require_once __DIR__ . '/../includes/layout.php';
render_header('Student: ' . $student['full_name'], true);
?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<div class="result-header" style="margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <h2 style="font-family:var(--font-display);font-size:22px;font-weight:800"><?= clean($student['full_name']) ?></h2>
      <p style="color:var(--text-muted);font-size:14px"><?= clean($student['department']) ?> · <?= clean($student['programme'] ?? 'B.Sc') ?> · Admitted <?= clean($student['admission_year'] ?? 'N/A') ?></p>
    </div>
    <div class="no-print" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">Print</button>
      <button class="btn btn-secondary btn-sm" type="button" onclick="exportPDF()">Export PDF</button>
    </div>
  </div>
  <div class="student-info-grid" style="margin-top:16px">
    <div class="info-item"><div class="info-label">Matric No</div><div class="info-value accent"><?= clean($student['matric_no']) ?></div></div>
    <div class="info-item"><div class="info-label">CGPA</div><div class="info-value" style="font-size:22px;color:var(--accent)"><?= fmt_gpa($resultData['final_cgpa']) ?></div></div>
    <div class="info-item"><div class="info-label">Classification</div><div class="info-value" style="color:<?= class_color($resultData['final_class']) ?>"><?= $resultData['final_class'] ?></div></div>
  </div>
</div>

<?php if (!empty($resultData['gpa_history'])): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-header"><span class="card-title">GPA Trend</span></div>
  <div class="chart-box"><canvas id="gpa-chart"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const history = <?= json_encode($resultData['gpa_history']) ?>;
  initGpaChart(history);
});
</script>
<?php endif; ?>

<?php if (empty($resultData['semesters'])): ?>
<div class="empty-state">
  <div class="empty-state-icon">⊘</div>
  <h3>No Results Found</h3>
  <p>No result records uploaded for this student yet.</p>
</div>
<?php else: ?>
<?php foreach ($resultData['semesters'] as $sem): ?>
<div class="semester-block">
  <div class="semester-header">
    <div class="semester-label">
      <span class="toggle-arrow">▾</span>
      <?= $sem['level'] ?>L - <?= $sem['semester_label'] ?> &nbsp;·&nbsp; <?= clean($sem['session']) ?>
    </div>
    <div class="semester-meta">
      <span class="gpa-chip">GPA: <?= fmt_gpa($sem['gpa']) ?></span>
      <span class="gpa-chip" style="background:#e9f2f8;color:#1f5f8b">CGPA: <?= fmt_gpa($sem['cgpa']) ?></span>
    </div>
  </div>
  <div class="semester-body">
    <div class="table-wrap" style="margin-top:16px">
      <table class="data-table">
        <thead>
          <tr><th>Code</th><th>Title</th><th>Units</th><th>Score</th><th>Grade</th><th>GP</th><th>QP</th><th>Remark</th></tr>
        </thead>
        <tbody>
          <?php foreach ($sem['courses'] as $c): ?>
          <tr>
            <td class="mono">
              <?= clean($c['course_code']) ?>
              <?php if ($c['is_carryover']): ?><span class="badge badge-carryover" style="margin-left:4px;font-size:10px">CARRY</span><?php endif; ?>
            </td>
            <td><?= clean($c['course_title']) ?></td>
            <td class="mono" style="text-align:center"><?= $c['course_unit'] ?></td>
            <td class="mono" style="text-align:center"><?= fmt_score((float)$c['score']) ?></td>
            <td><span class="badge badge-<?= strtolower($c['grade']) ?>"><?= $c['grade'] ?></span></td>
            <td class="mono" style="text-align:center"><?= $c['grade_point'] ?></td>
            <td class="mono" style="text-align:center"><?= $c['quality_point'] ?></td>
            <td style="font-size:13px;color:var(--text-muted)"><?= $c['remark'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="semester-footer">
    <div class="footer-item"><span class="footer-item-label">Total Units</span><span class="footer-item-value"><?= $sem['sem_units'] ?></span></div>
    <div class="footer-item"><span class="footer-item-label">Total QP</span><span class="footer-item-value"><?= $sem['sem_qp'] ?></span></div>
    <div class="footer-item"><span class="footer-item-label">GPA</span><span class="footer-item-value gpa-val"><?= fmt_gpa($sem['gpa']) ?></span></div>
    <div class="footer-item"><span class="footer-item-label">CGPA</span><span class="footer-item-value gpa-val"><?= fmt_gpa($sem['cgpa']) ?></span></div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php render_footer(true); ?>
