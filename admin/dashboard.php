<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
require_once __DIR__ . '/../includes/layout.php';
render_header('Dashboard', true);
?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card" style="--accent-color:var(--accent)">
    <div class="stat-label">Total Students</div>
    <div class="stat-value" id="stat-students">0</div>
    <div class="stat-sub">Registered in system</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--green)">
    <div class="stat-label">Total Courses</div>
    <div class="stat-value" id="stat-courses">0</div>
    <div class="stat-sub">In course catalog</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--purple)">
    <div class="stat-label">Result Entries</div>
    <div class="stat-value" id="stat-results">0</div>
    <div class="stat-sub">Across all sessions</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--red)">
    <div class="stat-label">Carry-Overs</div>
    <div class="stat-value" id="stat-carryovers">0</div>
    <div class="stat-sub">Detected in records</div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-3" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Grade Distribution</div>
        <div class="card-subtitle">All result entries</div>
      </div>
    </div>
    <div class="chart-box" style="min-height:220px">
      <canvas id="dash-grade-chart"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">CGPA Distribution</div>
        <div class="card-subtitle">Student spread</div>
      </div>
    </div>
    <div class="chart-box" style="min-height:220px">
      <canvas id="dash-gpa-chart"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Pass / Fail Ratio</div>
        <div class="card-subtitle">Score threshold: 40</div>
      </div>
    </div>
    <div class="chart-box" style="min-height:220px">
      <canvas id="dash-passfail-chart"></canvas>
    </div>
  </div>
</div>

<!-- Top Students -->
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">★ Top 10 Students by CGPA</div>
      <div class="card-subtitle">Cumulative across all sessions</div>
    </div>
    <a href="<?= BASE_URL ?>/admin/top_students.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Matric No</th>
          <th>Full Name</th>
          <th>Department</th>
          <th>CGPA</th>
        </tr>
      </thead>
      <tbody id="top-students-tbody">
        <tr><td colspan="5">
          <div class="loader-wrap"><div class="spinner"></div></div>
        </td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Quick Links -->
<div class="grid-2" style="margin-top:20px">
  <div class="card">
    <div class="card-header"><div class="card-title">Quick Actions</div></div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <a href="<?= BASE_URL ?>/admin/upload.php"   class="btn btn-primary" style="justify-content:center">↑ Upload Results</a>
      <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-secondary" style="justify-content:center">✦ Manage Students</a>
      <a href="<?= BASE_URL ?>/admin/courses.php"  class="btn btn-secondary" style="justify-content:center">⊞ Manage Courses</a>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Recent Upload Logs</div></div>
    <?php
    $logs = db_all('SELECT ul.*, a.full_name FROM upload_logs ul JOIN admins a ON ul.admin_id=a.id ORDER BY ul.uploaded_at DESC LIMIT 5');
    if ($logs): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>File</th><th>In/Up</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td style="font-size:12px;max-width:140px;overflow:hidden;text-overflow:ellipsis"><?= clean($log['filename']) ?></td>
            <td class="mono" style="font-size:12px"><?= $log['inserted'] ?> / <?= $log['updated'] ?></td>
            <td><span class="badge <?= $log['status']==='completed'?'badge-pass':'badge-carryover' ?>"><?= $log['status'] ?></span></td>
            <td style="font-size:12px;color:var(--text-muted)"><?= date('M d, H:i', strtotime($log['uploaded_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:24px">
      <div style="color:var(--text-muted);font-size:14px">No uploads yet.</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php render_footer(true); ?>
