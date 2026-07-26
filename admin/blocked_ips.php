<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

function blocked_ips_filter_text(string $value, int $limit): string {
    $value = trim($value);
    $value = preg_replace('/[[:cntrl:]]+/', '', $value);
    return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
}

function blocked_ips_choice(string $value, array $allowed): string {
    $value = trim($value);
    return in_array($value, $allowed, true) ? $value : '';
}

function blocked_ips_status(array $row): string {
    if (!empty($row['unblocked_at'])) {
        return 'unblocked';
    }

    $expiresAt = strtotime((string)$row['expires_at']);
    return $expiresAt !== false && $expiresAt <= time() ? 'expired' : 'active';
}

function blocked_ips_page_url(int $page): string {
    $query = $_GET;
    $query['page'] = $page;
    return '?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8');
}

$ip = blocked_ips_filter_text((string)($_GET['ip'] ?? ''), 45);
$status = blocked_ips_choice((string)($_GET['status'] ?? 'active'), ['all', 'active', 'expired', 'unblocked']);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$allowedFilters = [
    'ip' => 'LOCATE(?, ip_address) > 0',
    'active' => 'unblocked_at IS NULL AND expires_at > NOW()',
    'expired' => 'unblocked_at IS NULL AND expires_at <= NOW()',
    'unblocked' => 'unblocked_at IS NOT NULL',
];

$where = [];
$params = [];
if ($ip !== '') {
    $where[] = $allowedFilters['ip'];
    $params[] = $ip;
}
if ($status !== '' && $status !== 'all') {
    $where[] = $allowedFilters[$status];
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)(db_row("SELECT COUNT(*) AS c FROM blocked_ips{$whereSql}", $params)['c'] ?? 0);
$blockedIps = db_all(
    "SELECT id, ip_address, reason, attempt_count, blocked_at, expires_at, manually_blocked, unblocked_at
     FROM blocked_ips
     {$whereSql}
     ORDER BY blocked_at DESC, id DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);
$totalPages = max(1, (int)ceil($total / $perPage));
$csrfToken = csrf_token();

require_once __DIR__ . '/../includes/layout.php';
render_header('Blocked IPs', true);
?>

<div id="blocked-ip-message" style="display:none"></div>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Blocked IP Addresses</div>
      <div class="card-subtitle"><?= number_format($total) ?> matching record(s)</div>
    </div>
    <a href="<?= BASE_URL ?>/admin/security_dashboard.php" class="btn btn-ghost btn-sm">Dashboard</a>
  </div>

  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
    <input type="text" name="ip" class="form-control" placeholder="IP address" value="<?= clean($ip) ?>" style="width:220px">
    <select name="status" class="form-control" style="width:180px">
      <?php foreach (['active' => 'Active', 'expired' => 'Expired', 'unblocked' => 'Unblocked', 'all' => 'All Statuses'] as $value => $label): ?>
      <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= BASE_URL ?>/admin/blocked_ips.php" class="btn btn-secondary btn-sm">Clear</a>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>IP Address</th>
          <th>Reason</th>
          <th>Attempts</th>
          <th>Blocked Date</th>
          <th>Expiry Date</th>
          <th>Status</th>
          <th>Unblock Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($blockedIps): foreach ($blockedIps as $row):
            $rowStatus = blocked_ips_status($row);
        ?>
        <tr>
          <td class="mono"><?= clean($row['ip_address']) ?></td>
          <td><?= clean($row['reason'] ?? 'Repeated suspicious requests') ?></td>
          <td class="mono"><?= (int)$row['attempt_count'] ?></td>
          <td class="mono"><?= clean(date('M d, Y H:i', strtotime($row['blocked_at']))) ?></td>
          <td class="mono"><?= clean(date('M d, Y H:i', strtotime($row['expires_at']))) ?></td>
          <td>
            <span class="badge <?= $rowStatus === 'active' ? 'badge-carryover' : ($rowStatus === 'expired' ? 'badge-c' : 'badge-pass') ?>">
              <?= clean($rowStatus) ?>
            </span>
          </td>
          <td>
            <?php if ($rowStatus === 'active'): ?>
            <form class="security-unblock-form" method="POST" action="<?= BASE_URL ?>/api/unblock_ip.php" style="display:inline-flex">
              <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
              <input type="hidden" name="ip_address" value="<?= clean($row['ip_address']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">Unblock</button>
            </form>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:13px">No action</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7">
          <div class="empty-state" style="padding:40px">
            <h3>No Blocked IP Records</h3>
            <p>Temporary blocks will appear after repeated medium or high-risk detections.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($page > 1): ?>
    <a href="<?= blocked_ips_page_url($page - 1) ?>" class="page-btn">Prev</a>
    <?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
    <a href="<?= blocked_ips_page_url($i) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
    <a href="<?= blocked_ips_page_url($page + 1) ?>" class="page-btn">Next</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('submit', async event => {
  const form = event.target;
  if (!form.classList.contains('security-unblock-form')) return;

  event.preventDefault();
  const button = form.querySelector('button[type="submit"]');
  if (button) button.disabled = true;

  try {
    const response = await fetch(form.action, { method: 'POST', body: new FormData(form) });
    const json = await response.json();
    const box = document.getElementById('blocked-ip-message');
    if (box) {
      box.style.display = 'block';
      box.className = json.success ? 'alert alert-success' : 'alert alert-error';
      box.textContent = json.message || (json.success ? 'IP address unblocked.' : 'Unable to unblock IP address.');
    }
    if (json.success) {
      setTimeout(() => window.location.reload(), 700);
    } else if (button) {
      button.disabled = false;
    }
  } catch (error) {
    const box = document.getElementById('blocked-ip-message');
    if (box) {
      box.style.display = 'block';
      box.className = 'alert alert-error';
      box.textContent = 'Unable to process the unblock request.';
    }
    if (button) button.disabled = false;
  }
});
</script>

<?php render_footer(true); ?>
