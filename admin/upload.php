<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
require_once __DIR__ . '/../includes/layout.php';
render_header('Upload Results', true);
$currentSession = current_session();
?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<div class="grid-2" style="align-items:flex-start">
  <!-- Upload Card -->
  <div>
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">↑ Upload Result File</div>
          <div class="card-subtitle">Accepts .xlsx files up to 10 MB</div>
        </div>
      </div>

      <form id="upload-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
          <label class="form-label">Academic Session</label>
          <input type="text" name="academic_session" class="form-control"
            placeholder="e.g. 2023/2024" value="<?= clean($currentSession) ?>" required
            pattern="\d{4}/\d{4}" title="Format: YYYY/YYYY">
          <div class="form-hint">Format: YYYY/YYYY — e.g. 2023/2024</div>
        </div>

        <div class="form-group">
          <label class="form-label">Excel File (.xlsx)</label>
          <div class="drop-zone" id="drop-zone">
            <input type="file" id="file-input" name="result_file" accept=".xlsx">
            <div class="drop-zone-icon">📊</div>
            <div class="drop-zone-text" id="drop-label">Drag & drop your .xlsx file here</div>
            <div class="drop-zone-hint">or click to browse · max 10 MB</div>
          </div>
        </div>

        <div id="progress-section" style="display:none;margin-bottom:16px">
          <div class="progress-wrap"><div class="progress-bar" id="progress-bar"></div></div>
          <div class="progress-label" id="progress-label">Starting…</div>
        </div>

        <div id="upload-result"></div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
          ↑ Process Upload
        </button>
      </form>
    </div>

    <!-- Template download hint -->
    <div class="card" style="margin-top:16px">
      <div class="card-header"><div class="card-title">📋 File Format</div></div>
      <p style="font-size:14px;color:var(--text-secondary);margin-bottom:14px">
        Your Excel file must have these exact column headers in row 1:
      </p>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Column</th><th>Type</th><th>Example</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td class="mono">matric_no</td><td>Text</td><td>CSC/2020/001</td><td>Must exist in Students table</td></tr>
            <tr><td class="mono">course_code</td><td>Text</td><td>CSC101</td><td>Must exist in Courses table</td></tr>
            <tr><td class="mono">level</td><td>Number</td><td>100</td><td>100, 200, 300, 400, 500</td></tr>
            <tr><td class="mono">semester</td><td>Number</td><td>1</td><td>1 = First, 2 = Second</td></tr>
            <tr><td class="mono">score</td><td>Number</td><td>72.5</td><td>0.00 – 100.00</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right: Grading scale + Rules -->
  <div>
    <div class="card">
      <div class="card-header"><div class="card-title">Grading Scale</div></div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Score Range</th><th>Grade</th><th>Grade Point</th><th>Remark</th></tr></thead>
          <tbody>
            <?php foreach (GRADE_SCALE as $g): ?>
            <tr>
              <td class="mono"><?= $g['min'] ?>–<?= $g['max'] ?></td>
              <td><span class="badge badge-<?= strtolower($g['grade']) ?>"><?= $g['grade'] ?></span></td>
              <td class="mono" style="text-align:center"><?= $g['gp'] ?></td>
              <td style="font-size:13px;color:var(--text-muted)"><?= $g['remark'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><div class="card-title">Duplicate Handling Rules</div></div>
      <div style="font-size:14px;color:var(--text-secondary);line-height:1.8">
        <p><strong style="color:var(--yellow)">↺ Update:</strong> Same matric + course + session + level + semester → score is updated.</p>
        <p style="margin-top:10px"><strong style="color:var(--red)">⚑ Carry-over:</strong> Same course but higher level or newer session → inserted as new row flagged as carry-over.</p>
        <p style="margin-top:10px"><strong style="color:var(--text-muted)">⊘ Skip:</strong> Row with invalid data, unknown student, or unknown course is skipped with an error note.</p>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><div class="card-title">CGPA Classification</div></div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>CGPA Range</th><th>Class</th></tr></thead>
          <tbody>
            <?php foreach (CGPA_CLASS as $c): ?>
            <tr>
              <td class="mono"><?= $c['min'] ?> – <?= $c['max'] ?></td>
              <td style="font-weight:600"><?= $c['class'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php render_footer(true); ?>
