<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

function security_logs_filter_text(string $value, int $limit): string {
    $value = trim($value);
    $value = preg_replace('/[[:cntrl:]]+/', '', $value);
    return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
}

function security_logs_date(string $value): string {
    $value = trim($value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function security_logs_choice(string $value, array $allowed): string {
    $value = trim($value);
    return in_array($value, $allowed, true) ? $value : '';
}

function security_logs_categories(?string $matchedPatterns): array {
    if (!$matchedPatterns) {
        return [];
    }

    $decoded = json_decode($matchedPatterns, true);
    if (!is_array($decoded)) {
        return [];
    }

    $categories = [];
    foreach ((array)($decoded['categories'] ?? []) as $category) {
        $category = strtolower(trim((string)$category));
        if (preg_match('/^[a-z0-9_ -]{1,60}$/', $category)) {
            $categories[] = ucwords(str_replace('_', ' ', $category));
        }
    }

    return array_values(array_unique($categories));
}

function security_logs_preview(?string $preview): string {
    $preview = trim((string)$preview);
    if ($preview === '') {
        return '-';
    }

    $preview = preg_replace('/[[:cntrl:]]+/', ' ', $preview);
    $preview = preg_replace('/\s+/', ' ', $preview);

    return strlen($preview) > 120 ? substr($preview, 0, 120) . '...' : $preview;
}

function security_logs_page_url(int $page): string {
    $query = $_GET;
    $query['page'] = $page;
    return '?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8');
}

$dateFrom = security_logs_date((string)($_GET['date_from'] ?? ''));
$dateTo = security_logs_date((string)($_GET['date_to'] ?? ''));
$ip = security_logs_filter_text((string)($_GET['ip'] ?? ''), 45);
$path = security_logs_filter_text((string)($_GET['path'] ?? ''), 255);
$severity = security_logs_choice((string)($_GET['severity'] ?? ''), ['low', 'medium', 'high']);
$method = security_logs_choice(strtoupper((string)($_GET['method'] ?? '')), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']);
$action = security_logs_choice((string)($_GET['action'] ?? ''), ['logged', 'blocked', 'temporarily_blocked']);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$allowedFilters = [
    'date_from' => 'created_at >= ?',
    'date_to' => 'created_at <= ?',
    'ip' => 'LOCATE(?, ip_address) > 0',
    'path' => 'LOCATE(?, request_path) > 0',
    'severity' => 'severity = ?',
    'method' => 'request_method = ?',
    'action' => 'action_taken = ?',
];

$where = [];
$params = [];
if ($dateFrom !== '') {
    $where[] = $allowedFilters['date_from'];
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = $allowedFilters['date_to'];
    $params[] = $dateTo . ' 23:59:59';
}
if ($ip !== '') {
    $where[] = $allowedFilters['ip'];
    $params[] = $ip;
}
if ($path !== '') {
    $where[] = $allowedFilters['path'];
    $params[] = $path;
}
if ($severity !== '') {
    $where[] = $allowedFilters['severity'];
    $params[] = $severity;
}
if ($method !== '') {
    $where[] = $allowedFilters['method'];
    $params[] = $method;
}
if ($action !== '') {
    $where[] = $allowedFilters['action'];
    $params[] = $action;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)(db_row("SELECT COUNT(*) AS c FROM security_logs{$whereSql}", $params)['c'] ?? 0);
$logs = db_all(
    "SELECT id, admin_id, ip_address, request_method, request_path, input_field,
            input_preview, matched_patterns, risk_score, severity, action_taken,
            user_agent, created_at
     FROM security_logs
     {$whereSql}
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);
$totalPages = max(1, (int)ceil($total / $perPage));

require_once __DIR__ . '/../includes/layout.php';
render_header('Security Logs', true);
?>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Security Logs</div>
      <div class="card-subtitle"><?= number_format($total) ?> matching event(s)</div>
    </div>
    <a href="<?= BASE_URL ?>/admin/security_dashboard.php" class="btn btn-ghost btn-sm">Dashboard</a>
  </div>

  <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:20px">
    <input type="date" name="date_from" class="form-control" value="<?= clean($dateFrom) ?>" aria-label="Date from">
    <input type="date" name="date_to" class="form-control" value="<?= clean($dateTo) ?>" aria-label="Date to">
    <input type="text" name="ip" class="form-control" placeholder="IP address" value="<?= clean($ip) ?>">
    <input type="text" name="path" class="form-control" placeholder="Request path" value="<?= clean($path) ?>">
    <select name="severity" class="form-control">
      <option value="">All Severities</option>
      <?php foreach (['low', 'medium', 'high'] as $option): ?>
      <option value="<?= $option ?>" <?= $severity === $option ? 'selected' : '' ?>><?= clean(ucfirst($option)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="method" class="form-control">
      <option value="">All Methods</option>
      <?php foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $option): ?>
      <option value="<?= $option ?>" <?= $method === $option ? 'selected' : '' ?>><?= $option ?></option>
      <?php endforeach; ?>
    </select>
    <select name="action" class="form-control">
      <option value="">All Actions</option>
      <?php foreach (['logged', 'blocked', 'temporarily_blocked'] as $option): ?>
      <option value="<?= $option ?>" <?= $action === $option ? 'selected' : '' ?>><?= clean(ucwords(str_replace('_', ' ', $option))) ?></option>
      <?php endforeach; ?>
    </select>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="<?= BASE_URL ?>/admin/security_logs.php" class="btn btn-secondary btn-sm">Clear</a>
    </div>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date and Time</th>
          <th>IP Address</th>
          <th>Method</th>
          <th>Request Path</th>
          <th>Input Field</th>
          <th>Severity</th>
          <th>Risk</th>
          <th>Action</th>
          <th>View Details</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($logs): foreach ($logs as $log):
            $categories = security_logs_categories($log['matched_patterns'] ?? null);
        ?>
        <tr>
          <td class="mono"><?= clean(date('M d, Y H:i', strtotime($log['created_at']))) ?></td>
          <td class="mono"><?= clean($log['ip_address'] ?? 'unknown') ?></td>
          <td class="mono"><?= clean($log['request_method']) ?></td>
          <td style="max-width:260px;word-break:break-word"><?= clean($log['request_path']) ?></td>
          <td class="mono"><?= clean($log['input_field'] ?? '-') ?></td>
          <td><span class="badge <?= $log['severity'] === 'high' ? 'badge-carryover' : ($log['severity'] === 'medium' ? 'badge-c' : 'badge-pass') ?>"><?= clean($log['severity']) ?></span></td>
          <td class="mono"><?= (int)$log['risk_score'] ?></td>
          <td class="mono"><?= clean($log['action_taken']) ?></td>
          <td>
            <details>
              <summary class="btn btn-ghost btn-sm" style="display:inline-flex">View</summary>
              <div style="margin-top:10px;min-width:240px;color:var(--text-secondary);font-size:13px">
                <div><strong>Preview:</strong> <?= clean(security_logs_preview($log['input_preview'] ?? null)) ?></div>
                <div><strong>Categories:</strong> <?= clean($categories ? implode(', ', $categories) : '-') ?></div>
                <div><strong>Admin ID:</strong> <?= clean((string)($log['admin_id'] ?? '-')) ?></div>
              </div>
            </details>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="9">
          <div class="empty-state" style="padding:40px">
            <h3>No Logs Found</h3>
            <p>No security records match the current filters.</p>
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
    <a href="<?= security_logs_page_url($page - 1) ?>" class="page-btn">Prev</a>
    <?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
    <a href="<?= security_logs_page_url($i) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
    <a href="<?= security_logs_page_url($page + 1) ?>" class="page-btn">Next</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php render_footer(true); ?>
