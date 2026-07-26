<?php
/**
 * Security event logging.
 *
 * Database logging must never interrupt the application. This class avoids the
 * existing get_db() helper because get_db() intentionally terminates on
 * connection failure for normal application requests.
 */

function security_config_bool(string $constant, bool $default): bool {
    if (!defined($constant)) {
        return $default;
    }

    $value = constant($constant);
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value === 1;
    }

    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
    }

    return $default;
}

function security_config_int(string $constant, int $default, int $min, int $max): int {
    $value = defined($constant) ? constant($constant) : $default;
    if (is_string($value) && !preg_match('/^-?[0-9]+$/', trim($value))) {
        return $default;
    }

    $value = (int)$value;
    if ($value < $min || $value > $max) {
        return $default;
    }

    return $value;
}

function security_config_risk_thresholds(): array {
    $low = security_config_int('SQLI_LOW_SCORE', 20, 1, 100);
    $medium = security_config_int('SQLI_MEDIUM_SCORE', 40, 1, 100);
    $high = security_config_int('SQLI_HIGH_SCORE', 70, 1, 100);

    if (!($low < $medium && $medium < $high)) {
        return ['low' => 20, 'medium' => 40, 'high' => 70];
    }

    return ['low' => $low, 'medium' => $medium, 'high' => $high];
}

function security_config_settings(): array {
    $thresholds = security_config_risk_thresholds();

    return [
        'detection_enabled' => security_config_bool('SQLI_DETECTION_ENABLED', true),
        'low_score' => $thresholds['low'],
        'medium_score' => $thresholds['medium'],
        'high_score' => $thresholds['high'],
        'block_threshold' => security_config_int('SQLI_BLOCK_THRESHOLD', 5, 1, 100),
        'attempt_window_minutes' => security_config_int('SQLI_ATTEMPT_WINDOW_MINUTES', 10, 1, 1440),
        'block_duration_minutes' => security_config_int('SQLI_BLOCK_DURATION_MINUTES', 30, 1, 10080),
        'test_page_enabled' => security_config_bool('SECURITY_TEST_PAGE_ENABLED', false),
        'log_retention_days' => security_config_int('SECURITY_LOG_RETENTION_DAYS', 90, 1, 3650),
    ];
}

class SecurityLogger {
    private const PREVIEW_LIMIT = 500;
    private static ?PDO $pdo = null;
    private static bool $connectionFailed = false;

    public static function logEvent(array $event): bool {
        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO security_logs
                    (admin_id, ip_address, request_method, request_path, input_field,
                     input_preview, matched_patterns, risk_score, severity, action_taken,
                     user_agent)
                 VALUES
                    (:admin_id, :ip_address, :request_method, :request_path, :input_field,
                     :input_preview, :matched_patterns, :risk_score, :severity, :action_taken,
                     :user_agent)'
            );

            return $stmt->execute([
                'admin_id' => self::adminId(),
                'ip_address' => self::clientIp(),
                'request_method' => self::requestMethod(),
                'request_path' => self::requestPath(),
                'input_field' => self::limitText((string)($event['input_field'] ?? ''), 100) ?: null,
                'input_preview' => self::safeInputPreview((string)($event['input_preview'] ?? '')),
                'matched_patterns' => self::matchedPatternText($event),
                'risk_score' => max(0, min(100, (int)($event['risk_score'] ?? 0))),
                'severity' => self::severity((string)($event['severity'] ?? 'medium')),
                'action_taken' => self::action((string)($event['action_taken'] ?? 'blocked')),
                'user_agent' => self::limitText((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 500) ?: null,
            ]);
        } catch (Throwable $e) {
            security_log_private('Security database log write failed.', [
                'error' => $e->getMessage(),
                'field' => $event['input_field'] ?? '',
                'severity' => $event['severity'] ?? '',
                'action' => $event['action_taken'] ?? '',
            ]);
            return false;
        }
    }

    public static function safeInputPreview(string $value): ?string {
        if ($value === '' || self::fieldLooksSensitive((string)($GLOBALS['security_current_log_field'] ?? ''))) {
            return $value === '' ? null : '[masked]';
        }

        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[[:cntrl:]]+/', ' ', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));

        return self::limitText($value, self::PREVIEW_LIMIT) ?: null;
    }

    public static function previewForField(string $field, string $value): ?string {
        if (self::fieldLooksSensitive($field)) {
            return '[masked]';
        }

        return self::safeInputPreview($value);
    }

    public static function cleanupExpiredLogs(?int $retentionDays = null): array {
        $retentionDays = $retentionDays ?? security_config_settings()['log_retention_days'];
        if ($retentionDays < 1 || $retentionDays > 3650) {
            $retentionDays = security_config_settings()['log_retention_days'];
        }

        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return ['success' => false, 'deleted' => 0];
            }

            $cutoff = (new DateTimeImmutable('now'))
                ->modify('-' . (int)$retentionDays . ' days')
                ->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare('DELETE FROM security_logs WHERE created_at < ?');
            $stmt->execute([$cutoff]);

            return ['success' => true, 'deleted' => $stmt->rowCount(), 'retention_days' => $retentionDays, 'cutoff' => $cutoff];
        } catch (Throwable $e) {
            security_log_private('Security log cleanup failed.', [
                'error' => $e->getMessage(),
                'retention_days' => $retentionDays,
            ]);
            return ['success' => false, 'deleted' => 0, 'retention_days' => $retentionDays];
        }
    }

    private static function pdo(): ?PDO {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (self::$connectionFailed) {
            return null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return self::$pdo;
        } catch (Throwable $e) {
            self::$connectionFailed = true;
            security_log_private('Security database log connection failed.', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private static function adminId(): ?int {
        if (session_status() === PHP_SESSION_NONE && defined('SESSION_NAME') && isset($_COOKIE[SESSION_NAME])) {
            try {
                session_name(SESSION_NAME);
                session_start();
            } catch (Throwable $e) {
                security_log_private('Unable to inspect session for security log admin ID.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['admin_id'])) {
            $adminId = (int)$_SESSION['admin_id'];
            return $adminId > 0 ? $adminId : null;
        }

        return null;
    }

    private static function clientIp(): ?string {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return self::limitText($ip, 45) ?: null;
    }

    private static function requestMethod(): string {
        return self::limitText((string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 10) ?: 'CLI';
    }

    private static function requestPath(): string {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'cli'));
        $path = parse_url($uri, PHP_URL_PATH);
        return self::limitText((string)($path ?: $uri), 255) ?: 'unknown';
    }

    private static function matchedPatternText(array $event): ?string {
        $categories = array_values(array_unique(array_filter((array)($event['categories'] ?? []))));
        $patterns = array_values(array_unique(array_filter((array)($event['patterns'] ?? []))));

        $payload = [
            'categories' => $categories,
            'patterns' => $patterns,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    private static function severity(string $severity): string {
        return in_array($severity, ['low', 'medium', 'high'], true) ? $severity : 'medium';
    }

    private static function action(string $action): string {
        return in_array($action, ['logged', 'blocked', 'temporarily_blocked'], true) ? $action : 'blocked';
    }

    private static function fieldLooksSensitive(string $field): bool {
        $field = strtolower($field);
        foreach (['password', 'csrf', 'token', 'session_id', 'sessionid', 'cookie'] as $part) {
            if (strpos($field, $part) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function limitText(string $value, int $limit): string {
        return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
    }
}

class SecurityBlocker {
    private static ?PDO $pdo = null;
    private static bool $connectionFailed = false;

    public static function activeBlock(?string $ipAddress = null): ?array {
        $ipAddress = self::ipAddress($ipAddress);
        if ($ipAddress === null || self::shouldBypassIp($ipAddress)) {
            return null;
        }

        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return null;
            }

            $stmt = $pdo->prepare(
                'SELECT *
                 FROM blocked_ips
                 WHERE ip_address = ?
                   AND unblocked_at IS NULL
                   AND expires_at > NOW()
                 ORDER BY expires_at DESC
                 LIMIT 1'
            );
            $stmt->execute([$ipAddress]);
            $row = $stmt->fetch();

            return $row ?: null;
        } catch (Throwable $e) {
            security_log_private('Blocked IP lookup failed.', [
                'error' => $e->getMessage(),
                'ip_address' => $ipAddress,
            ]);
            return null;
        }
    }

    public static function recordAttemptAndMaybeBlock(array $event, ?string $ipAddress = null): array {
        $ipAddress = self::ipAddress($ipAddress);
        $severity = (string)($event['severity'] ?? 'none');
        $score = (int)($event['risk_score'] ?? $event['score'] ?? 0);

        if ($ipAddress === null || self::shouldBypassIp($ipAddress) || $score < security_config_settings()['medium_score'] || !in_array($severity, ['medium', 'high'], true)) {
            return ['blocked' => false, 'attempt_count' => 0, 'bypassed' => $ipAddress !== null && self::shouldBypassIp($ipAddress)];
        }

        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return ['blocked' => false, 'attempt_count' => 0, 'bypassed' => false];
            }

            $activeBlock = self::activeBlock($ipAddress);
            if ($activeBlock) {
                return [
                    'blocked' => true,
                    'attempt_count' => (int)$activeBlock['attempt_count'],
                    'bypassed' => false,
                    'already_blocked' => true,
                ];
            }

            $windowMinutes = self::attemptWindowMinutes();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS attempt_count
                 FROM security_logs
                 WHERE ip_address = ?
                   AND severity IN (?, ?)
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $windowMinutes . ' MINUTE)'
            );
            $stmt->execute([$ipAddress, 'medium', 'high']);
            $attemptCount = (int)($stmt->fetch()['attempt_count'] ?? 0);

            if ($attemptCount < self::blockThreshold()) {
                return ['blocked' => false, 'attempt_count' => $attemptCount, 'bypassed' => false];
            }

            $durationMinutes = self::blockDurationMinutes();
            $insert = $pdo->prepare(
                'INSERT INTO blocked_ips
                    (ip_address, reason, attempt_count, blocked_at, expires_at, manually_blocked)
                 VALUES
                    (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ' . $durationMinutes . ' MINUTE), 0)'
            );
            $insert->execute([
                $ipAddress,
                'Repeated medium or high-risk SQL injection detections',
                $attemptCount,
            ]);

            SecurityLogger::logEvent([
                'input_field' => $event['input_field'] ?? null,
                'input_preview' => $event['input_preview'] ?? null,
                'categories' => $event['categories'] ?? ['temporary_block'],
                'patterns' => $event['patterns'] ?? ['repeated_attempts'],
                'risk_score' => $score,
                'severity' => $severity,
                'action_taken' => 'temporarily_blocked',
            ]);

            return [
                'blocked' => true,
                'attempt_count' => $attemptCount,
                'bypassed' => false,
                'already_blocked' => false,
            ];
        } catch (Throwable $e) {
            security_log_private('Temporary block tracking failed.', [
                'error' => $e->getMessage(),
                'ip_address' => $ipAddress,
            ]);
            return ['blocked' => false, 'attempt_count' => 0, 'bypassed' => false];
        }
    }

    public static function unblockIp(string $ipAddress): bool {
        $ipAddress = self::ipAddress($ipAddress);
        if ($ipAddress === null) {
            return false;
        }

        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare(
                'UPDATE blocked_ips
                 SET unblocked_at = NOW()
                 WHERE ip_address = ?
                   AND unblocked_at IS NULL
                   AND expires_at > NOW()'
            );
            $stmt->execute([$ipAddress]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            security_log_private('Manual unblock failed.', [
                'error' => $e->getMessage(),
                'ip_address' => $ipAddress,
            ]);
            return false;
        }
    }

    public static function shouldBypassIp(string $ipAddress): bool {
        return defined('DEV_MODE')
            && DEV_MODE
            && in_array($ipAddress, ['127.0.0.1', '::1'], true);
    }

    public static function settings(): array {
        return [
            'threshold' => self::blockThreshold(),
            'window_minutes' => self::attemptWindowMinutes(),
            'duration_minutes' => self::blockDurationMinutes(),
        ];
    }

    private static function pdo(): ?PDO {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (self::$connectionFailed) {
            return null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return self::$pdo;
        } catch (Throwable $e) {
            self::$connectionFailed = true;
            security_log_private('Temporary block database connection failed.', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private static function ipAddress(?string $ipAddress = null): ?string {
        $ipAddress = trim((string)($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
        if ($ipAddress === '') {
            return null;
        }

        return strlen($ipAddress) > 45 ? substr($ipAddress, 0, 45) : $ipAddress;
    }

    private static function blockThreshold(): int {
        return security_config_settings()['block_threshold'];
    }

    private static function attemptWindowMinutes(): int {
        return security_config_settings()['attempt_window_minutes'];
    }

    private static function blockDurationMinutes(): int {
        return security_config_settings()['block_duration_minutes'];
    }
}

function securityCleanupExpiredLogs(?int $retentionDays = null): array {
    return SecurityLogger::cleanupExpiredLogs($retentionDays);
}

function security_mask_sensitive_context(array $context): array {
    $sensitiveKeys = ['password', 'confirm_password', 'csrf_token', 'token', 'session_id', 'sessionid', 'cookie'];
    $masked = [];

    foreach ($context as $key => $value) {
        $keyText = strtolower((string)$key);
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (strpos($keyText, $sensitiveKey) !== false) {
                $masked[$key] = '[masked]';
                continue 2;
            }
        }

        if (is_scalar($value) || $value === null) {
            $text = (string)$value;
            $text = preg_replace('/[[:cntrl:]]+/', ' ', $text);
            $masked[$key] = strlen($text) > 120 ? substr($text, 0, 120) . '...' : $text;
        } else {
            $masked[$key] = '[non-scalar]';
        }
    }

    return $masked;
}

function security_log_private(string $message, array $context = []): bool {
    $safeContext = security_mask_sensitive_context($context);
    $suffix = $safeContext ? ' ' . json_encode($safeContext, JSON_UNESCAPED_SLASHES) : '';
    return error_log('[SRMS Security] ' . $message . $suffix);
}
