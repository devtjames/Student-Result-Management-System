<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

// Filter inputs
$filterSession  = clean($_GET['session']  ?? '');
$filterSemester = (int)($_GET['semester'] ?? 0);
$filterLevel    = (int)($_GET['level']    ?? 0);
$filterMatric   = sanitize_matric($_GET['matric'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 50;
$offset         = ($page - 1) * $perPage;

// Build query
$allowedFilters = [
    'session'  => 'r.academic_session = ?',
    'semester' => 'r.semester = ?',
    'level'    => 'r.level = ?',
    'matric'   => 'r.matric_no = ?',
];
$where  = [];
$params = [];
if ($filterSession)  { $where[] = $allowedFilters['session'];  $params[] = $filterSession; }
if ($filterSemester) { $where[] = $allowedFilters['semester']; $params[] = $filterSemester; }
if ($filterLevel)    { $where[] = $allowedFilters['level'];    $params[] = $filterLevel; }
if ($filterMatric)   { $where[] = $allowedFilters['matric'];   $params[] = $filterMatric; }
$whereStr = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total   = (int)db_row("SELECT COUNT(*) AS c FROM results_raw r$whereStr", $params)['c'];
$results = db_all(
    "SELECT r.*, s.full_name, c.course_title, c.course_unit
     FROM results_raw r
     JOIN students s ON r.matric_no = s.matric_no
     JOIN courses  c ON r.course_code = c.course_code
     $whereStr
     ORDER BY r.academic_session DESC, r.semester DESC, r.matric_no ASC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);
$totalPages = ceil($total / $perPage);

// Available sessions for filter
$sessions = db_all('SELECT DISTINCT academic_session FROM results_raw ORDER BY academic_session DESC');

require_once __DIR__ . '/../includes/layout.php';
render_header('All Results', true);
?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">≡ Result Records</div>
      <div class="card-subtitle"><?= number_format($total) ?> records found</div>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
    <select name="session" class="form-control" style="width:160px">
      <option value="">All Sessions</option>
      <?php foreach ($sessions as $s): ?>
      <option value="<?= clean($s['academic_session']) ?>" <?= $filterSession===$s['academic_session']?'selected':'' ?>><?= clean($s['academic_session']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="semester" class="form-control" style="width:140px">
      <option value="">All Semesters</option>
      <option value="1" <?= $filterSemester===1?'selected':'' ?>>1st Semester</option>
      <option value="2" <?= $filterSemester===2?'selected':'' ?>>2nd Semester</option>
    </select>
    <select name="level" class="form-control" style="width:120px">
      <option value="">All Levels</option>
      <?php foreach ([100,200,300,400,500] as $l): ?>
      <option value="<?=$l?>" <?=$filterLevel===$l?'selected':''?>><?=$l?>L</option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="matric" class="form-control" placeholder="Matric No" value="<?= clean($filterMatric) ?>" style="width:160px">
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= BASE_URL ?>/admin/results.php" class="btn btn-secondary btn-sm">Clear</a>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Matric No</th><th>Student</th><th>Course</th><th>Title</th>
          <th>Units</th><th>Session</th><th>Sem</th><th>Level</th>
          <th>Score</th><th>Grade</th><th>Flag</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($results): foreach ($results as $r):
          $g = compute_grade((float)$r['score']);
        ?>
        <tr>
          <td class="mono"><a href="<?= BASE_URL ?>/admin/view_student.php?matric=<?= urlencode($r['matric_no']) ?>"><?= clean($r['matric_no']) ?></a></td>
          <td style="white-space:nowrap"><?= clean($r['full_name']) ?></td>
          <td class="mono"><?= clean($r['course_code']) ?></td>
          <td style="font-size:13px"><?= clean($r['course_title']) ?></td>
          <td class="mono" style="text-align:center"><?= $r['course_unit'] ?></td>
          <td class="mono"><?= clean($r['academic_session']) ?></td>
          <td class="mono" style="text-align:center"><?= $r['semester'] ?></td>
          <td class="mono" style="text-align:center"><?= $r['level'] ?></td>
          <td class="mono" style="text-align:center"><?= fmt_score((float)$r['score']) ?></td>
          <td><span class="badge badge-<?= strtolower($g['grade']) ?>"><?= $g['grade'] ?></span></td>
          <td><?= $r['is_carryover'] ? '<span class="badge badge-carryover">CARRY</span>' : '' ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="11">
          <div class="empty-state" style="padding:40px">
            <div class="empty-state-icon">⊘</div>
            <h3>No Records</h3>
            <p>No results match your filter criteria.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= min($totalPages, 10); $i++):
      $q = http_build_query(array_merge($_GET, ['page' => $i]));
    ?>
    <a href="?<?= $q ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($totalPages > 10): ?>
    <span style="color:var(--text-muted);padding:0 8px">… <?= $totalPages ?> pages</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php render_footer(true); ?>
