<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

function security_dashboard_count(string $sql, array $params = []): int {
    $row = db_row($sql, $params);
    return (int)($row['c'] ?? 0);
}

function security_dashboard_categories(?string $matchedPatterns): array {
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
            $categories[] = $category;
        }
    }

    return array_values(array_unique($categories));
}

function security_dashboard_label(string $value): string {
    return ucwords(str_replace('_', ' ', $value));
}

function security_dashboard_json(array $data): string {
    $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    return $json === false ? '{}' : $json;
}

$totalAttempts = security_dashboard_count('SELECT COUNT(*) AS c FROM security_logs');
$attemptsToday = security_dashboard_count('SELECT COUNT(*) AS c FROM security_logs WHERE DATE(created_at) = CURDATE()');
$blockedRequests = security_dashboard_count("SELECT COUNT(*) AS c FROM security_logs WHERE action_taken IN ('blocked', 'temporarily_blocked')");
$highRiskEvents = security_dashboard_count("SELECT COUNT(*) AS c FROM security_logs WHERE severity = 'high'");
$activeBlockedIps = security_dashboard_count('SELECT COUNT(*) AS c FROM blocked_ips WHERE unblocked_at IS NULL AND expires_at > NOW()');

$topEndpoint = db_row(
    'SELECT request_path, COUNT(*) AS c
     FROM security_logs
     GROUP BY request_path
     ORDER BY c DESC, request_path ASC
     LIMIT 1'
);

$attemptsByDate = db_all(
    'SELECT DATE(created_at) AS day, COUNT(*) AS count
     FROM security_logs
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC'
);

$attemptDateMap = [];
foreach ($attemptsByDate as $row) {
    $attemptDateMap[$row['day']] = (int)$row['count'];
}

$dateLabels = [];
$dateCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $dateLabels[] = date('M d', strtotime($day));
    $dateCounts[] = $attemptDateMap[$day] ?? 0;
}

$severityRows = db_all(
    'SELECT severity, COUNT(*) AS count
     FROM security_logs
     GROUP BY severity
     ORDER BY FIELD(severity, \'low\', \'medium\', \'high\')'
);
$severityLabels = [];
$severityCounts = [];
foreach ($severityRows as $row) {
    $severityLabels[] = security_dashboard_label((string)$row['severity']);
    $severityCounts[] = (int)$row['count'];
}

$endpointRows = db_all(
    'SELECT request_path, COUNT(*) AS count
     FROM security_logs
     GROUP BY request_path
     ORDER BY count DESC, request_path ASC
     LIMIT 8'
);
$endpointLabels = [];
$endpointCounts = [];
foreach ($endpointRows as $row) {
    $endpointLabels[] = (string)$row['request_path'];
    $endpointCounts[] = (int)$row['count'];
}

$patternRows = db_all(
    'SELECT matched_patterns
     FROM security_logs
     WHERE matched_patterns IS NOT NULL
     ORDER BY created_at DESC
     LIMIT 500'
);
$patternCounts = [];
foreach ($patternRows as $row) {
    foreach (security_dashboard_categories($row['matched_patterns'] ?? null) as $category) {
        $patternCounts[$category] = ($patternCounts[$category] ?? 0) + 1;
    }
}
arsort($patternCounts);
$patternCounts = array_slice($patternCounts, 0, 8, true);
$patternLabels = array_map('security_dashboard_label', array_keys($patternCounts));
$patternValues = array_values($patternCounts);

$recentEvents = db_all(
    'SELECT id, ip_address, request_method, request_path, input_field, risk_score, severity, action_taken, created_at
     FROM security_logs
     ORDER BY created_at DESC
     LIMIT 8'
);

require_once __DIR__ . '/../includes/layout.php';
render_header('Security Dashboard', true);
?>

<div class="stats-grid">
  <div class="stat-card" style="--accent-color:var(--accent)">
    <div class="stat-label">Total Attempts</div>
    <div class="stat-value"><?= number_format($totalAttempts) ?></div>
    <div class="stat-sub">Detected security events</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--green)">
    <div class="stat-label">Attempts Today</div>
    <div class="stat-value"><?= number_format($attemptsToday) ?></div>
    <div class="stat-sub"><?= clean(date('M d, Y')) ?></div>
  </div>
  <div class="stat-card" style="--accent-color:var(--orange)">
    <div class="stat-label">Blocked Requests</div>
    <div class="stat-value"><?= number_format($blockedRequests) ?></div>
    <div class="stat-sub">Blocked or temporarily blocked</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--red)">
    <div class="stat-label">High-Risk Events</div>
    <div class="stat-value"><?= number_format($highRiskEvents) ?></div>
    <div class="stat-sub">Severity marked high</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--purple)">
    <div class="stat-label">Blocked IPs</div>
    <div class="stat-value"><?= number_format($activeBlockedIps) ?></div>
    <div class="stat-sub">Currently active blocks</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--yellow)">
    <div class="stat-label">Top Endpoint</div>
    <div class="stat-value" style="font-size:18px;word-break:break-word"><?= clean($topEndpoint['request_path'] ?? 'None') ?></div>
    <div class="stat-sub"><?= number_format((int)($topEndpoint['c'] ?? 0)) ?> attempt(s)</div>
  </div>
</div>

<div class="grid-2" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Attempts by Date</div>
        <div class="card-subtitle">Last 14 days</div>
      </div>
    </div>
    <div class="chart-box"><canvas id="security-date-chart"></canvas></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Attempts by Severity</div>
        <div class="card-subtitle">Low, medium and high risk events</div>
      </div>
    </div>
    <div class="chart-box"><canvas id="security-severity-chart"></canvas></div>
  </div>
</div>

<div class="grid-2" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Targeted Endpoints</div>
        <div class="card-subtitle">Most frequent request paths</div>
      </div>
    </div>
    <div class="chart-box"><canvas id="security-endpoint-chart"></canvas></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Detection Categories</div>
        <div class="card-subtitle">Pattern categories only</div>
      </div>
    </div>
    <div class="chart-box"><canvas id="security-pattern-chart"></canvas></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Recent Security Events</div>
      <div class="card-subtitle">Latest detections without raw payloads</div>
    </div>
    <div style="display:flex;gap:10px">
      <a href="<?= BASE_URL ?>/admin/security_logs.php" class="btn btn-ghost btn-sm">View Logs</a>
      <a href="<?= BASE_URL ?>/admin/blocked_ips.php" class="btn btn-secondary btn-sm">Blocked IPs</a>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>IP</th>
          <th>Method</th>
          <th>Path</th>
          <th>Field</th>
          <th>Severity</th>
          <th>Score</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recentEvents): foreach ($recentEvents as $event): ?>
        <tr>
          <td class="mono"><?= clean(date('M d, H:i', strtotime($event['created_at']))) ?></td>
          <td class="mono"><?= clean($event['ip_address'] ?? 'unknown') ?></td>
          <td class="mono"><?= clean($event['request_method']) ?></td>
          <td style="max-width:260px;word-break:break-word"><?= clean($event['request_path']) ?></td>
          <td class="mono"><?= clean($event['input_field'] ?? '-') ?></td>
          <td><span class="badge <?= $event['severity'] === 'high' ? 'badge-carryover' : ($event['severity'] === 'medium' ? 'badge-c' : 'badge-pass') ?>"><?= clean($event['severity']) ?></span></td>
          <td class="mono"><?= (int)$event['risk_score'] ?></td>
          <td class="mono"><?= clean($event['action_taken']) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8">
          <div class="empty-state" style="padding:32px">
            <h3>No Security Events</h3>
            <p>Detected requests will appear here after logging is enabled and the database upgrade is applied.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const securityChartData = <?= security_dashboard_json([
    'dates' => ['labels' => $dateLabels, 'counts' => $dateCounts],
    'severity' => ['labels' => $severityLabels, 'counts' => $severityCounts],
    'endpoints' => ['labels' => $endpointLabels, 'counts' => $endpointCounts],
    'patterns' => ['labels' => $patternLabels, 'counts' => $patternValues],
]) ?>;

function buildSecurityChart(id, type, labels, counts, color) {
  const canvas = document.getElementById(id);
  if (!canvas || typeof Chart === 'undefined') return;
  new Chart(canvas, {
    type,
    data: {
      labels,
      datasets: [{
        label: 'Attempts',
        data: counts,
        backgroundColor: color,
        borderColor: Array.isArray(color) ? '#ffffff' : color,
        borderWidth: type === 'line' ? 2 : 1,
        tension: .3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: type !== 'bar' && type !== 'line' } },
      scales: type === 'doughnut' ? {} : {
        x: { ticks: { color: '#7a8798', font: { family: 'DM Mono', size: 11 } }, grid: { color: '#d7e0ea' } },
        y: { beginAtZero: true, ticks: { color: '#7a8798', precision: 0, font: { family: 'DM Mono' } }, grid: { color: '#d7e0ea' } }
      }
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  buildSecurityChart('security-date-chart', 'line', securityChartData.dates.labels, securityChartData.dates.counts, '#1f5f8b');
  buildSecurityChart('security-severity-chart', 'doughnut', securityChartData.severity.labels, securityChartData.severity.counts, ['#238064', '#b7791f', '#b8323a']);
  buildSecurityChart('security-endpoint-chart', 'bar', securityChartData.endpoints.labels, securityChartData.endpoints.counts, 'rgba(31,95,139,.18)');
  buildSecurityChart('security-pattern-chart', 'bar', securityChartData.patterns.labels, securityChartData.patterns.counts, 'rgba(109,92,174,.18)');
});
</script>

<?php render_footer(true); ?>
