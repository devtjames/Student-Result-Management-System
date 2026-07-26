<?php
/**
 * Central SQL injection detection engine.
 *
 * This file defines the SQL injection detector and request guard helpers.
 */

function normalizeSqlInjectionInput(string $value): string {
    $normalized = str_replace("\0", '', $value);

    for ($i = 0; $i < 2; $i++) {
        $decodedUrl = rawurldecode($normalized);
        $decodedHtml = html_entity_decode($decodedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decodedHtml === $normalized) {
            break;
        }

        $normalized = $decodedHtml;
    }

    $normalized = preg_replace('/[[:cntrl:]]+/', ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    return strtolower(trim($normalized));
}

function sqlInjectionSeverity(int $score): string {
    $thresholds = security_config_risk_thresholds();

    if ($score >= $thresholds['high']) {
        return 'high';
    }

    if ($score >= $thresholds['medium']) {
        return 'medium';
    }

    if ($score >= $thresholds['low']) {
        return 'low';
    }

    return 'none';
}

function sqlInjectionDetectionEnabled(): bool {
    return security_config_settings()['detection_enabled'];
}

function detectSqlInjection(string $value): array {
    $normalized = normalizeSqlInjectionInput($value);
    if (!sqlInjectionDetectionEnabled()) {
        return [
            'detected' => false,
            'score' => 0,
            'severity' => 'none',
            'patterns' => [],
            'categories' => [],
            'normalized_preview' => substr($normalized, 0, 120),
            'enabled' => false,
        ];
    }

    $matchedPatterns = [];
    $categories = [];
    $score = 0;

    foreach (sql_injection_patterns() as $name => $pattern) {
        if (preg_match($pattern['regex'], $normalized)) {
            $matchedPatterns[] = $name;
            $categories[] = $pattern['category'];
            $score += (int)$pattern['score'];
        }
    }

    $score = min(100, $score);
    $severity = sqlInjectionSeverity($score);

    return [
        'detected' => $score >= security_config_risk_thresholds()['low'],
        'score' => $score,
        'severity' => $severity,
        'patterns' => array_values(array_unique($matchedPatterns)),
        'categories' => array_values(array_unique($categories)),
        'normalized_preview' => substr($normalized, 0, 120),
        'enabled' => true,
    ];
}

function isRequestBlocked(array $detectionResult): bool {
    $score = (int)($detectionResult['score'] ?? 0);
    return $score >= security_config_risk_thresholds()['medium'];
}

function security_is_excluded_field(string $field): bool {
    $field = strtolower($field);
    $excludedParts = [
        'password',
        'csrf',
        'token',
        'session_id',
        'sessionid',
        'cookie',
    ];

    foreach ($excludedParts as $part) {
        if (strpos($field, $part) !== false) {
            return true;
        }
    }

    return false;
}

function scanRequestInput(array $input, array $excludedFields = []): array {
    $defaultExcluded = [
        'password',
        'confirm_password',
        'current_password',
        'new_password',
        'csrf_token',
        'token',
        'session_id',
    ];
    $excludedLookup = array_fill_keys(array_map('strtolower', array_merge($defaultExcluded, $excludedFields)), true);

    $fieldResults = [];
    $maxScore = 0;
    $patterns = [];
    $categories = [];

    $walker = function (array $values, string $prefix = '') use (&$walker, &$fieldResults, &$maxScore, &$patterns, &$categories, $excludedLookup): void {
        foreach ($values as $key => $value) {
            $field = $prefix === '' ? (string)$key : $prefix . '.' . (string)$key;
            $keyText = strtolower((string)$key);

            if (isset($excludedLookup[$keyText]) || security_is_excluded_field($field)) {
                continue;
            }

            if (is_array($value)) {
                $walker($value, $field);
                continue;
            }

            if ((!is_scalar($value) && $value !== null) || security_value_looks_binary((string)$value)) {
                continue;
            }

            $result = detectSqlInjection((string)$value);
            if (!$result['detected']) {
                continue;
            }

            $fieldResults[] = array_merge([
                'field' => $field,
                'input_preview' => SecurityLogger::previewForField($field, (string)$value),
            ], $result);
            $maxScore = max($maxScore, (int)$result['score']);
            $patterns = array_merge($patterns, $result['patterns']);
            $categories = array_merge($categories, $result['categories']);
        }
    };

    $walker($input);

    $aggregate = [
        'detected' => !empty($fieldResults),
        'score' => $maxScore,
        'severity' => sqlInjectionSeverity($maxScore),
        'patterns' => array_values(array_unique($patterns)),
        'categories' => array_values(array_unique($categories)),
        'fields' => $fieldResults,
    ];
    $aggregate['blocked'] = isRequestBlocked($aggregate);

    return $aggregate;
}

function security_value_looks_binary(string $value): bool {
    return $value !== '' && preg_match('/[\x00-\x08\x0E-\x1F]/', $value) === 1;
}

function security_field_name(string $field): string {
    $parts = explode('.', $field);
    $last = strtolower((string)end($parts));
    return preg_replace('/[^a-z0-9_]/', '', $last);
}

function security_validation_failure(string $field, string $rule, string $value = ''): array {
    $mediumScore = security_config_risk_thresholds()['medium'];

    return [
        'valid' => false,
        'field' => $field,
        'rule' => $rule,
        'input_preview' => SecurityLogger::previewForField($field, $value),
        'score' => $mediumScore,
        'severity' => sqlInjectionSeverity($mediumScore),
    ];
}

function validateSecurityField(string $field, string $value): array {
    $name = security_field_name($field);
    $value = trim($value);

    if ($value === '') {
        return ['valid' => true];
    }

    if (in_array($name, ['matric', 'matric_no'], true)) {
        return preg_match('/^[A-Za-z0-9\/-]{3,50}$/', $value)
            ? ['valid' => true]
            : security_validation_failure($field, 'matric_number', $value);
    }

    if (in_array($name, ['course_code', 'code'], true)) {
        return preg_match('/^[A-Za-z]{2,10}[0-9]{2,4}$/', $value)
            ? ['valid' => true]
            : security_validation_failure($field, 'course_code', $value);
    }

    if (in_array($name, ['academic_session', 'session'], true)) {
        if (!preg_match('/^([0-9]{4})\/([0-9]{4})$/', $value, $matches)) {
            return security_validation_failure($field, 'academic_session', $value);
        }

        return ((int)$matches[2] === (int)$matches[1] + 1)
            ? ['valid' => true]
            : security_validation_failure($field, 'academic_session_sequence', $value);
    }

    if ($name === 'level') {
        return in_array($value, ['100', '200', '300', '400', '500'], true)
            ? ['valid' => true]
            : security_validation_failure($field, 'level', $value);
    }

    if ($name === 'semester') {
        return in_array($value, ['1', '2'], true)
            ? ['valid' => true]
            : security_validation_failure($field, 'semester', $value);
    }

    if ($name === 'score') {
        if (!is_numeric($value)) {
            return security_validation_failure($field, 'score', $value);
        }

        $score = (float)$value;
        return ($score >= 0 && $score <= 100)
            ? ['valid' => true]
            : security_validation_failure($field, 'score_range', $value);
    }

    if ($name === 'id' || substr($name, -3) === '_id') {
        return preg_match('/^[1-9][0-9]*$/', $value)
            ? ['valid' => true]
            : security_validation_failure($field, 'numeric_id', $value);
    }

    if (in_array($name, ['page', 'limit', 'offset'], true)) {
        if (!preg_match('/^[0-9]+$/', $value)) {
            return security_validation_failure($field, 'pagination_number', $value);
        }

        $number = (int)$value;
        $maximum = $name === 'limit' ? 500 : 1000000;
        return ($number >= 0 && $number <= $maximum && ($name !== 'page' || $number >= 1))
            ? ['valid' => true]
            : security_validation_failure($field, 'pagination_range', $value);
    }

    return ['valid' => true];
}

function validateSecurityRequestFields(array $input): array {
    $failures = [];

    $walker = function (array $values, string $prefix = '') use (&$walker, &$failures): void {
        foreach ($values as $key => $value) {
            $field = $prefix === '' ? (string)$key : $prefix . '.' . (string)$key;

            if (security_is_excluded_field($field)) {
                continue;
            }

            if (is_array($value)) {
                $walker($value, $field);
                continue;
            }

            if ((!is_scalar($value) && $value !== null) || security_value_looks_binary((string)$value)) {
                continue;
            }

            $result = validateSecurityField($field, (string)$value);
            if (!$result['valid']) {
                $failures[] = $result;
            }
        }
    };

    $walker($input);

    return [
        'valid' => empty($failures),
        'detected' => !empty($failures),
        'score' => empty($failures) ? 0 : security_config_risk_thresholds()['medium'],
        'severity' => empty($failures) ? 'none' : sqlInjectionSeverity(security_config_risk_thresholds()['medium']),
        'failures' => $failures,
    ];
}

function securityJsonRequestInput(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        return [];
    }

    $body = file_get_contents('php://input');
    if ($body === false || trim($body) === '' || strlen($body) > 1048576) {
        return [];
    }

    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    return is_array($decoded) ? $decoded : ['json' => $decoded];
}

function collectSecurityRequestInput(): array {
    return [
        'get' => $_GET ?? [],
        'post' => $_POST ?? [],
        'json' => securityJsonRequestInput(),
    ];
}

function securityIsApiRequest(): bool {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    return strpos($script, '/api/') !== false
        || stripos($accept, 'application/json') !== false
        || strcasecmp($requestedWith, 'XMLHttpRequest') === 0
        || stripos($contentType, 'application/json') !== false;
}

function securityTestPageEnabled(): bool {
    return (defined('DEV_MODE') && DEV_MODE)
        || security_config_settings()['test_page_enabled'];
}

function securityIsControlledTestRoute(): bool {
    if (!securityTestPageEnabled()) {
        return false;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return str_ends_with($script, '/admin/security_test.php')
        || str_ends_with($script, '/api/run_security_test.php');
}

function securityTestPayloadSamples(): array {
    return [
        'tautology' => [
            'label' => 'Tautology Login Bypass',
            'payload' => "' OR 1=1 --",
            'category' => 'Tautology attack',
        ],
        'comment' => [
            'label' => 'Comment-Based Username',
            'payload' => "admin' --",
            'category' => 'SQL comment attack',
        ],
        'union' => [
            'label' => 'Union Data Discovery',
            'payload' => '1 UNION SELECT username, password FROM admins',
            'category' => 'UNION attack',
        ],
        'stacked' => [
            'label' => 'Stacked Destructive Query',
            'payload' => '1; DROP TABLE students',
            'category' => 'Stacked query attack',
        ],
        'time_based' => [
            'label' => 'Time-Based Delay',
            'payload' => '1 AND SLEEP(5)',
            'category' => 'Time-based attack',
        ],
        'discovery' => [
            'label' => 'Database Discovery',
            'payload' => 'information_schema.tables',
            'category' => 'Database discovery',
        ],
    ];
}

function securityTestPayloadById(string $sampleId): ?array {
    $samples = securityTestPayloadSamples();
    return $samples[$sampleId] ?? null;
}

function securityBlockRequest(string $reason, array $context = []): void {
    security_log_private('Request blocked by security guard.', array_merge(['reason' => $reason], $context));
    http_response_code(403);

    if (securityIsApiRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'The request contains unsupported or suspicious input.',
            'error_code' => 'SECURITY_REQUEST_BLOCKED',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Request Blocked</title></head><body>';
    echo '<h1>Request Blocked</h1>';
    echo '<p>The request contains unsupported or suspicious input.</p>';
    echo '</body></html>';
    exit;
}

function securityBlockTemporarilyBlockedIp(string $reason, array $context = []): void {
    security_log_private('Request blocked by temporary IP block.', array_merge(['reason' => $reason], $context));
    http_response_code(429);

    if (securityIsApiRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Too many suspicious requests. Please try again later.',
            'error_code' => 'SECURITY_TEMPORARILY_BLOCKED',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Request Temporarily Blocked</title></head><body>';
    echo '<h1>Request Temporarily Blocked</h1>';
    echo '<p>Too many suspicious requests. Please try again later.</p>';
    echo '</body></html>';
    exit;
}

function securityLogValidationFailures(array $validation): ?array {
    $firstEvent = null;

    foreach ($validation['failures'] ?? [] as $failure) {
        $event = [
            'input_field' => $failure['field'] ?? null,
            'input_preview' => $failure['input_preview'] ?? null,
            'categories' => ['field_validation'],
            'patterns' => [$failure['rule'] ?? 'invalid_field'],
            'risk_score' => (int)($failure['score'] ?? 40),
            'severity' => $failure['severity'] ?? 'medium',
            'action_taken' => 'blocked',
        ];

        SecurityLogger::logEvent($event);
        $firstEvent ??= $event;
    }

    return $firstEvent;
}

function securityLogDetection(array $detection, string $action): ?array {
    $firstEvent = null;

    foreach ($detection['fields'] ?? [] as $field) {
        $event = [
            'input_field' => $field['field'] ?? null,
            'input_preview' => $field['input_preview'] ?? null,
            'categories' => $field['categories'] ?? $detection['categories'] ?? [],
            'patterns' => $field['patterns'] ?? $detection['patterns'] ?? [],
            'risk_score' => (int)($field['score'] ?? $detection['score'] ?? 0),
            'severity' => $field['severity'] ?? $detection['severity'] ?? 'medium',
            'action_taken' => $action,
        ];

        SecurityLogger::logEvent($event);
        $firstEvent ??= $event;
    }

    return $firstEvent;
}

function securityGuardRequest(): void {
    if (PHP_SAPI === 'cli') {
        return;
    }

    $activeBlock = SecurityBlocker::activeBlock();
    if ($activeBlock) {
        SecurityLogger::logEvent([
            'input_field' => null,
            'input_preview' => null,
            'categories' => ['temporary_block'],
            'patterns' => ['active_block'],
            'risk_score' => security_config_risk_thresholds()['medium'],
            'severity' => sqlInjectionSeverity(security_config_risk_thresholds()['medium']),
            'action_taken' => 'temporarily_blocked',
        ]);
        securityBlockTemporarilyBlockedIp('active_temporary_block');
    }

    if (securityIsControlledTestRoute()) {
        return;
    }

    $input = collectSecurityRequestInput();
    $validation = validateSecurityRequestFields($input);
    if (!$validation['valid']) {
        $event = securityLogValidationFailures($validation);
        $block = $event ? SecurityBlocker::recordAttemptAndMaybeBlock($event) : ['blocked' => false];
        if (!empty($block['blocked'])) {
            securityBlockTemporarilyBlockedIp('threshold_reached', [
                'attempt_count' => $block['attempt_count'] ?? 0,
            ]);
        }

        securityBlockRequest('validation_failed', [
            'severity' => $validation['severity'],
            'failure_count' => count($validation['failures']),
            'first_field' => $validation['failures'][0]['field'] ?? '',
            'first_rule' => $validation['failures'][0]['rule'] ?? '',
        ]);
    }

    $detection = scanRequestInput($input);
    if (!empty($detection['blocked'])) {
        $event = securityLogDetection($detection, 'blocked');
        $block = $event ? SecurityBlocker::recordAttemptAndMaybeBlock($event) : ['blocked' => false];
        if (!empty($block['blocked'])) {
            securityBlockTemporarilyBlockedIp('threshold_reached', [
                'attempt_count' => $block['attempt_count'] ?? 0,
                'score' => $detection['score'],
            ]);
        }

        securityBlockRequest('sql_injection_detected', [
            'severity' => $detection['severity'],
            'score' => $detection['score'],
            'fields' => implode(',', array_map(static fn(array $field): string => $field['field'], $detection['fields'])),
        ]);
    }

    if (!empty($detection['detected'])) {
        securityLogDetection($detection, 'logged');
        security_log_private('Low-risk SQL injection pattern detected.', [
            'severity' => $detection['severity'],
            'score' => $detection['score'],
        ]);
    }
}

function sqlInjectionDetectorTestCases(): array {
    return [
        ['label' => 'valid matric number', 'value' => 'CSC/2020/001', 'detected' => false, 'blocked' => false],
        ['label' => 'valid course code', 'value' => 'CSC101', 'detected' => false, 'blocked' => false],
        ['label' => 'valid academic session', 'value' => '2023/2024', 'detected' => false, 'blocked' => false],
        ['label' => 'name with apostrophe', 'value' => "O'Connor", 'detected' => false, 'blocked' => false],
        ['label' => 'hyphenated name', 'value' => 'Aminu-Sule', 'detected' => false, 'blocked' => false],
        ['label' => 'normal union text', 'value' => 'Union Secondary School', 'detected' => false, 'blocked' => false],
        ['label' => 'normal drop text', 'value' => 'Drop Zone Department', 'detected' => false, 'blocked' => false],
        ['label' => 'normal selected text', 'value' => 'SELECTED STUDENT', 'detected' => false, 'blocked' => false],
        ['label' => 'normal search text', 'value' => 'Please update selected student profile', 'detected' => false, 'blocked' => false],
        ['label' => 'tautology attack', 'value' => "' OR 1=1 --", 'detected' => true, 'blocked' => true],
        ['label' => 'union attack', 'value' => '1 UNION SELECT username, password FROM admins', 'detected' => true, 'blocked' => true],
        ['label' => 'comment attack', 'value' => "admin' --", 'detected' => true, 'blocked' => false],
        ['label' => 'block comment attack', 'value' => "' AND 'a'='a' /*comment*/", 'detected' => true, 'blocked' => true],
        ['label' => 'stacked query attack', 'value' => '1; DROP TABLE students', 'detected' => true, 'blocked' => true],
        ['label' => 'database discovery attack', 'value' => 'information_schema.tables', 'detected' => true, 'blocked' => true],
        ['label' => 'time based attack', 'value' => '1 AND SLEEP(5)', 'detected' => true, 'blocked' => true],
        ['label' => 'file command attack', 'value' => "1 INTO OUTFILE '/tmp/result.txt'", 'detected' => true, 'blocked' => true],
        ['label' => 'encoded attack', 'value' => '%27%20OR%201%3D1%20--', 'detected' => true, 'blocked' => true],
    ];
}

function runSqlInjectionDetectorSelfTest(): array {
    $results = [];
    $passed = 0;
    $failed = 0;

    foreach (sqlInjectionDetectorTestCases() as $case) {
        $detection = detectSqlInjection($case['value']);
        $blocked = isRequestBlocked($detection);
        $ok = $detection['detected'] === $case['detected'] && $blocked === $case['blocked'];

        if ($ok) {
            $passed++;
        } else {
            $failed++;
        }

        $results[] = [
            'label' => $case['label'],
            'passed' => $ok,
            'expected_detected' => $case['detected'],
            'actual_detected' => $detection['detected'],
            'expected_blocked' => $case['blocked'],
            'actual_blocked' => $blocked,
            'score' => $detection['score'],
            'severity' => $detection['severity'],
            'patterns' => $detection['patterns'],
        ];
    }

    return [
        'passed' => $passed,
        'failed' => $failed,
        'results' => $results,
    ];
}
