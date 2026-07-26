<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
require_once __DIR__ . '/../includes/layout.php';
render_header('Students', true);
$total = (int)db_row('SELECT COUNT(*) AS c FROM students')['c'];
?>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">✦ Students</div>
      <div class="card-subtitle"><?= number_format($total) ?> registered students</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <input type="text" id="student-search" class="form-control" placeholder="Search by name, matric, dept…" style="width:260px">
      <button class="btn btn-primary btn-sm" onclick="editStudent(null)">+ Add Student</button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Matric No</th>
          <th>Full Name</th>
          <th>Department</th>
          <th>Programme</th>
          <th>Admitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="students-tbody">
        <?php
        $students = db_all('SELECT * FROM students ORDER BY matric_no LIMIT 50');
        if ($students): foreach ($students as $s): ?>
        <tr>
          <td class="mono"><?= clean($s['matric_no']) ?></td>
          <td><?= clean($s['full_name']) ?></td>
          <td><?= clean($s['department']) ?></td>
          <td><?= clean($s['programme'] ?? '—') ?></td>
          <td><?= clean($s['admission_year'] ?? '—') ?></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/view_student.php?matric=<?= urlencode($s['matric_no']) ?>" class="btn btn-ghost btn-sm">View</a>
            <button class="btn btn-secondary btn-sm" onclick="editStudent('<?= clean($s['matric_no']) ?>')">Edit</button>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6">
          <div class="empty-state" style="padding:32px">
            <div class="empty-state-icon">✦</div>
            <h3>No Students Yet</h3>
            <p>Add students manually or via Excel upload.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Student Modal -->
<div class="modal-overlay" id="student-modal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="student-modal-title">Add New Student</h2>
      <button class="modal-close" onclick="closeModal('student-modal')">✕</button>
    </div>
    <form id="student-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Matric No *</label>
          <input type="text" name="matric_no" class="form-control" placeholder="e.g. CSC/2020/001" required>
        </div>
        <div class="form-group">
          <label class="form-label">Programme</label>
          <select name="programme" class="form-control">
            <option value="B.Sc">B.Sc</option>
            <option value="HND">HND</option>
            <option value="B.Eng">B.Eng</option>
            <option value="B.Tech">B.Tech</option>
            <option value="B.A">B.A</option>
            <option value="B.Ed">B.Ed</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" name="full_name" class="form-control" placeholder="Surname Firstname Middlename" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Department *</label>
          <input type="text" name="department" class="form-control" placeholder="e.g. Computer Science" required>
        </div>
        <div class="form-group">
          <label class="form-label">Admission Year</label>
          <input type="number" name="admission_year" class="form-control" placeholder="e.g. 2020" min="2000" max="2099">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" placeholder="student@email.com">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-secondary" onclick="closeModal('student-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Student</button>
      </div>
    </form>
  </div>
</div>

<?php render_footer(true); ?>
