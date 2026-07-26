<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!securityTestPageEnabled()) {
    json_response([
        'success' => false,
        'message' => 'Security testing is disabled.',
        'error_code' => 'SECURITY_TEST_DISABLED',
    ], 403);
}

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    json_response(['success' => false, 'message' => 'Invalid request token.'], 403);
}

$sampleId = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['sample_id'] ?? '')));
$sample = $sampleId !== '' ? securityTestPayloadById($sampleId) : null;
$payload = trim((string)($_POST['payload'] ?? ''));

if ($payload === '' && $sample) {
    $payload = $sample['payload'];
}

if ($payload === '') {
    json_response(['success' => false, 'message' => 'Select or enter a payload to test.'], 400);
}

if (strlen($payload) > 500) {
    json_response(['success' => false, 'message' => 'Payload is too long for the controlled test.'], 400);
}

$detection = detectSqlInjection($payload);
$wouldBlock = isRequestBlocked($detection);
$categories = array_map(
    static fn(string $category): string => ucwords(str_replace('_', ' ', $category)),
    (array)($detection['categories'] ?? [])
);

$unsafeQuery = "SELECT id, matric_no, full_name FROM students WHERE matric_no = '" . $payload . "'";
$preparedSql = 'SELECT id, matric_no, full_name FROM students WHERE matric_no = ?';

json_response([
    'success' => true,
    'data' => [
        'sample_id' => $sample ? $sampleId : 'custom',
        'sample_label' => $sample['label'] ?? 'Custom Payload',
        'submitted_payload' => $payload,
        'detected' => (bool)$detection['detected'],
        'detection_status' => $detection['detected'] ? 'Detected' : 'Not detected',
        'risk_score' => (int)$detection['score'],
        'severity' => (string)$detection['severity'],
        'categories' => $categories,
        'would_block' => $wouldBlock,
        'would_block_label' => $wouldBlock ? 'Yes' : 'No',
        'unsafe_query_example' => $unsafeQuery,
        'prepared_query_example' => $preparedSql,
        'bound_value_preview' => $payload,
        'executed' => false,
        'database_touched' => false,
        'execution_message' => 'No SQL was executed against the SRMS database.',
        'prepared_message' => 'A prepared statement would bind the submitted payload as data.',
    ],
]);
