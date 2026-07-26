<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
require_once __DIR__ . '/../includes/layout.php';
render_header('Courses', true);
$courses = db_all('SELECT * FROM courses ORDER BY level ASC, course_code ASC');
?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">⊞ Course Catalog</div>
      <div class="card-subtitle"><?= count($courses) ?> courses</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="editCourse(null)">+ Add Course</button>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Code</th><th>Title</th><th>Units</th><th>Level</th><th>Semester</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($courses): foreach ($courses as $c): ?>
        <tr>
          <td class="mono"><?= clean($c['course_code']) ?></td>
          <td><?= clean($c['course_title']) ?></td>
          <td class="mono" style="text-align:center"><?= $c['course_unit'] ?></td>
          <td><?= $c['level'] ? $c['level'] . 'L' : '—' ?></td>
          <td><?= $c['semester'] ? sem_label((int)$c['semester']) : '—' ?></td>
          <td>
            <button class="btn btn-ghost btn-sm" onclick="editCourse('<?= clean($c['course_code']) ?>')">Edit</button>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6">
          <div class="empty-state" style="padding:32px">
            <div class="empty-state-icon">⊞</div>
            <h3>No Courses Yet</h3>
            <p>Add courses to get started.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Course Modal -->
<div class="modal-overlay" id="course-modal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="course-modal-title">Add New Course</h2>
      <button class="modal-close" onclick="closeModal('course-modal')">✕</button>
    </div>
    <form id="course-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" class="form-control" placeholder="e.g. CSC101" required>
        </div>
        <div class="form-group">
          <label class="form-label">Credit Units *</label>
          <input type="number" name="course_unit" class="form-control" value="3" min="1" max="6" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Course Title *</label>
        <input type="text" name="course_title" class="form-control" placeholder="e.g. Introduction to Computer Science" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Level</label>
          <select name="level" class="form-control">
            <option value="">— Any Level —</option>
            <option value="100">100</option><option value="200">200</option>
            <option value="300">300</option><option value="400">400</option>
            <option value="500">500</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Semester</label>
          <select name="semester" class="form-control">
            <option value="">— Any —</option>
            <option value="1">1st Semester</option>
            <option value="2">2nd Semester</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-secondary" onclick="closeModal('course-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Course</button>
      </div>
    </form>
  </div>
</div>

<?php render_footer(true); ?>
