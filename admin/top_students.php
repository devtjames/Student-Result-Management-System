<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

// Top students by CGPA (all students with results)
$topStudents = db_all(
    'SELECT
        r.matric_no,
        s.full_name,
        s.department,
        s.programme,
        COUNT(DISTINCT CONCAT(r.academic_session,r.semester)) AS sem_count,
        SUM(c.course_unit) AS total_units,
        ROUND(
            SUM(
                CASE
                    WHEN r.score >= 70 THEN 5
                    WHEN r.score >= 60 THEN 4
                    WHEN r.score >= 50 THEN 3
                    WHEN r.score >= 45 THEN 2
                    WHEN r.score >= 40 THEN 1
                    ELSE 0
                END * c.course_unit
            ) / SUM(c.course_unit), 2
        ) AS cgpa,
        SUM(CASE WHEN r.score < 40 THEN 1 ELSE 0 END) AS fail_count,
        SUM(CASE WHEN r.is_carryover = 1 THEN 1 ELSE 0 END) AS carryover_count
     FROM results_raw r
     JOIN courses c ON r.course_code = c.course_code
     JOIN students s ON r.matric_no = s.matric_no
     GROUP BY r.matric_no, s.full_name, s.department, s.programme
     ORDER BY cgpa DESC'
);

require_once __DIR__ . '/../includes/layout.php';
render_header('Top Students', true);
?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<div class="grid-2" style="align-items:flex-start">
  <!-- Rankings Table -->
  <div style="grid-column:1 / -1" class="card">
    <div class="card-header">
      <div>
        <div class="card-title">★ Student Rankings by CGPA</div>
        <div class="card-subtitle"><?= count($topStudents) ?> students with results</div>
      </div>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Rank</th><th>Matric No</th><th>Full Name</th><th>Department</th>
            <th>Semesters</th><th>Total Units</th><th>CGPA</th><th>Class</th>
            <th>Fails</th><th>Carryovers</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($topStudents): foreach ($topStudents as $i => $s):
            $rank  = $i + 1;
            $cgpa  = (float)$s['cgpa'];
            $class = get_classification($cgpa);
            $rankColor = $rank <= 3 ? ['#b7791f','#7a8798','#c55a27'][$rank-1] : 'var(--text-muted)';
          ?>
          <tr>
            <td><span style="font-family:var(--font-display);font-weight:800;font-size:20px;color:<?= $rankColor ?>"><?= $rank ?></span></td>
            <td class="mono"><a href="<?= BASE_URL ?>/admin/view_student.php?matric=<?= urlencode($s['matric_no']) ?>"><?= clean($s['matric_no']) ?></a></td>
            <td><?= clean($s['full_name']) ?></td>
            <td style="font-size:13px"><?= clean($s['department']) ?></td>
            <td class="mono" style="text-align:center"><?= $s['sem_count'] ?></td>
            <td class="mono" style="text-align:center"><?= $s['total_units'] ?></td>
            <td><span class="gpa-chip"><?= fmt_gpa($cgpa) ?></span></td>
            <td style="font-size:13px;font-weight:600;color:<?= class_color($class) ?>"><?= $class ?></td>
            <td class="mono" style="text-align:center;color:<?= $s['fail_count'] > 0 ? 'var(--red)' : 'var(--text-muted)' ?>"><?= $s['fail_count'] ?></td>
            <td class="mono" style="text-align:center;color:<?= $s['carryover_count'] > 0 ? 'var(--orange)' : 'var(--text-muted)' ?>"><?= $s['carryover_count'] ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="10">
            <div class="empty-state" style="padding:40px">
              <div class="empty-state-icon">★</div>
              <h3>No Data Yet</h3>
              <p>Upload results to see student rankings.</p>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- GPA Calculator Tool -->
<div class="card" style="margin-top:20px">
  <div class="card-header">
    <div>
      <div class="card-title">⊕ GPA Calculator Tool</div>
      <div class="card-subtitle">Estimate GPA for any combination of courses</div>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="addCalcRow()">+ Add Row</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Course</th><th>Units</th><th>Score (0–100)</th><th>Grade</th><th>QP</th><th></th></tr>
      </thead>
      <tbody id="calc-tbody"></tbody>
    </table>
  </div>

  <div style="display:flex;align-items:center;justify-content:flex-end;gap:24px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
    <div style="text-align:right">
      <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted)">Computed GPA</div>
      <div style="font-family:var(--font-display);font-size:36px;font-weight:800;color:var(--accent)" id="calc-gpa-result">—</div>
    </div>
    <div style="text-align:right">
      <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted)">Classification</div>
      <div style="font-weight:700;font-size:16px;color:var(--text-primary)" id="calc-class-result">—</div>
    </div>
  </div>
</div>

<script>
// Pre-populate calculator with 3 rows
document.addEventListener('DOMContentLoaded', () => {
  addCalcRow(); addCalcRow(); addCalcRow();
});
</script>

<?php render_footer(true); ?>
