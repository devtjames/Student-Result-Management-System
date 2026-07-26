<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
require_once __DIR__ . '/../includes/layout.php';

$enabled = securityTestPageEnabled();
$samples = securityTestPayloadSamples();
$csrfToken = csrf_token();

function security_test_json(array $data): string {
    $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    return $json === false ? '{}' : $json;
}

render_header('Security Test', true);
?>

<?php if (!$enabled): ?>
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Controlled Security Test</div>
      <div class="card-subtitle">Disabled by configuration</div>
    </div>
  </div>
  <div class="alert alert-warning">
    Security testing is disabled. Enable it only for controlled demonstrations.
  </div>
</div>
<?php else: ?>

<div class="grid-2" style="align-items:start">
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Controlled Payloads</div>
        <div class="card-subtitle">Detector-only project demonstration</div>
      </div>
    </div>

    <form id="security-test-form">
      <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
      <input type="hidden" name="sample_id" id="security-sample-id" value="tautology">

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-bottom:16px">
        <?php foreach ($samples as $id => $sample): ?>
        <button
          type="button"
          class="btn <?= $id === 'tautology' ? 'btn-primary' : 'btn-secondary' ?> btn-sm security-sample-btn"
          data-sample-id="<?= clean($id) ?>"
          data-payload="<?= clean($sample['payload']) ?>"
          style="justify-content:center">
          <?= clean($sample['label']) ?>
        </button>
        <?php endforeach; ?>
      </div>

      <div class="form-group">
        <label class="form-label">Submitted Payload</label>
        <textarea name="payload" id="security-test-payload" class="form-control" rows="5" maxlength="500"><?= clean($samples['tautology']['payload']) ?></textarea>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="submit" class="btn btn-primary">Run Test</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Detection Result</div>
        <div class="card-subtitle">No database query is executed</div>
      </div>
    </div>

    <div id="security-test-status" class="empty-state" style="padding:28px">
      <h3>Ready</h3>
      <p>Select a payload and run the detector.</p>
    </div>

    <div id="security-test-result" style="display:none">
      <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:18px">
        <div class="stat-card" style="--accent-color:var(--accent);padding:16px">
          <div class="stat-label">Detection</div>
          <div class="stat-value" id="result-status" style="font-size:24px">-</div>
          <div class="stat-sub" id="result-sample">-</div>
        </div>
        <div class="stat-card" style="--accent-color:var(--red);padding:16px">
          <div class="stat-label">Risk Score</div>
          <div class="stat-value" id="result-score" style="font-size:24px">0</div>
          <div class="stat-sub" id="result-severity">-</div>
        </div>
        <div class="stat-card" style="--accent-color:var(--purple);padding:16px">
          <div class="stat-label">Would Block</div>
          <div class="stat-value" id="result-block" style="font-size:24px">-</div>
          <div class="stat-sub">Medium and high risk requests</div>
        </div>
        <div class="stat-card" style="--accent-color:var(--green);padding:16px">
          <div class="stat-label">Executed</div>
          <div class="stat-value" id="result-executed" style="font-size:24px">No</div>
          <div class="stat-sub">Detector-only test</div>
        </div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px" id="result-categories"></div>

      <div class="table-wrap">
        <table class="data-table">
          <tbody>
            <tr>
              <th style="width:180px">Submitted Payload</th>
              <td><code class="mono" id="result-payload" style="white-space:pre-wrap;word-break:break-word"></code></td>
            </tr>
            <tr>
              <th>Unsafe Concatenation</th>
              <td><code class="mono" id="result-unsafe" style="white-space:pre-wrap;word-break:break-word"></code></td>
            </tr>
            <tr>
              <th>Prepared Statement</th>
              <td><code class="mono" id="result-prepared" style="white-space:pre-wrap;word-break:break-word"></code></td>
            </tr>
            <tr>
              <th>Bound Value</th>
              <td><code class="mono" id="result-bound" style="white-space:pre-wrap;word-break:break-word"></code></td>
            </tr>
            <tr>
              <th>Safety Confirmation</th>
              <td>
                <div id="result-execution-message"></div>
                <div id="result-prepared-message" style="color:var(--text-muted);font-size:13px;margin-top:4px"></div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const SECURITY_TEST_API = '<?= BASE_URL ?>/api/run_security_test.php';
const SECURITY_TEST_SAMPLES = <?= security_test_json($samples) ?>;

function setSecuritySample(button) {
  document.querySelectorAll('.security-sample-btn').forEach(btn => {
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-secondary');
  });
  button.classList.remove('btn-secondary');
  button.classList.add('btn-primary');
  document.getElementById('security-sample-id').value = button.dataset.sampleId || '';
  document.getElementById('security-test-payload').value = button.dataset.payload || '';
}

document.querySelectorAll('.security-sample-btn').forEach(button => {
  button.addEventListener('click', () => setSecuritySample(button));
});

document.getElementById('security-test-payload').addEventListener('input', () => {
  document.getElementById('security-sample-id').value = '';
});

document.getElementById('security-test-form').addEventListener('submit', async event => {
  event.preventDefault();
  const status = document.getElementById('security-test-status');
  const result = document.getElementById('security-test-result');
  status.style.display = 'block';
  status.innerHTML = '<div class="loader-wrap" style="padding:20px"><div class="spinner"></div><div class="loader-text">Running detector...</div></div>';
  result.style.display = 'none';

  try {
    const response = await fetch(SECURITY_TEST_API, { method: 'POST', body: new FormData(event.target) });
    const json = await response.json();
    if (!json.success) {
      status.innerHTML = `<div class="alert alert-error" style="margin:0">${json.message || 'Security test failed.'}</div>`;
      return;
    }

    renderSecurityResult(json.data);
  } catch (error) {
    status.innerHTML = '<div class="alert alert-error" style="margin:0">Unable to run the security test.</div>';
  }
});

function setText(id, value) {
  const element = document.getElementById(id);
  if (element) element.textContent = value == null ? '-' : String(value);
}

function renderSecurityResult(data) {
  document.getElementById('security-test-status').style.display = 'none';
  document.getElementById('security-test-result').style.display = 'block';

  setText('result-status', data.detection_status);
  setText('result-sample', data.sample_label);
  setText('result-score', data.risk_score);
  setText('result-severity', data.severity);
  setText('result-block', data.would_block_label);
  setText('result-executed', data.executed ? 'Yes' : 'No');
  setText('result-payload', data.submitted_payload);
  setText('result-unsafe', data.unsafe_query_example);
  setText('result-prepared', data.prepared_query_example);
  setText('result-bound', data.bound_value_preview);
  setText('result-execution-message', data.execution_message);
  setText('result-prepared-message', data.prepared_message);

  const categories = document.getElementById('result-categories');
  categories.innerHTML = '';
  const list = Array.isArray(data.categories) && data.categories.length ? data.categories : ['None'];
  list.forEach(category => {
    const badge = document.createElement('span');
    badge.className = 'badge ' + (category === 'None' ? 'badge-pass' : 'badge-carryover');
    badge.textContent = category;
    categories.appendChild(badge);
  });
}
</script>

<?php endif; ?>

<?php render_footer(true); ?>
