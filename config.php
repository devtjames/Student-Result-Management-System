<?php
/**
 * SRMS - Configuration File
 * ─────────────────────────
 * Update DB credentials before running.
 */

// ── Error Reporting ───────────────────────────────────────────────────────────
// Set to 0 in production
define('DEV_MODE', true);
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'srms_db');
define('DB_USER', 'root');       // ← change me
define('DB_PASS', '');           // ← change me
define('DB_CHARSET', 'utf8mb4');

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME',    'SRMS Portal');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/srms');   // ← change me (no trailing slash)
define('BASE_PATH',   __DIR__);

// ── Session ───────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600); // seconds → 1 hour
define('SESSION_NAME',     'SRMS_SESSION');

// ── Upload ────────────────────────────────────────────────────────────────────
define('UPLOAD_DIR',      BASE_PATH . '/uploads/temp/');
define('MAX_FILE_SIZE',   10 * 1024 * 1024); // 10 MB
define('ALLOWED_TYPES',   ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel']);

// Security controls
define('SQLI_DETECTION_ENABLED', true);
define('SQLI_LOW_SCORE', 20);
define('SQLI_MEDIUM_SCORE', 40);
define('SQLI_HIGH_SCORE', 70);
define('SQLI_BLOCK_THRESHOLD', 5);
define('SQLI_ATTEMPT_WINDOW_MINUTES', 10);
define('SQLI_BLOCK_DURATION_MINUTES', 30);
define('SECURITY_TEST_PAGE_ENABLED', false); // set true only for controlled demos outside DEV_MODE
define('SECURITY_LOG_RETENTION_DAYS', 90);

// ── Grading ───────────────────────────────────────────────────────────────────
// Score ranges → [grade, grade_point]
define('GRADE_SCALE', [
    ['min' => 70, 'max' => 100, 'grade' => 'A', 'gp' => 5, 'remark' => 'Excellent'],
    ['min' => 60, 'max' => 69,  'grade' => 'B', 'gp' => 4, 'remark' => 'Good'],
    ['min' => 50, 'max' => 59,  'grade' => 'C', 'gp' => 3, 'remark' => 'Average'],
    ['min' => 45, 'max' => 49,  'grade' => 'D', 'gp' => 2, 'remark' => 'Below Average'],
    ['min' => 40, 'max' => 44,  'grade' => 'E', 'gp' => 1, 'remark' => 'Poor'],
    ['min' => 0,  'max' => 39,  'grade' => 'F', 'gp' => 0, 'remark' => 'Fail'],
]);

// ── CGPA Classification ───────────────────────────────────────────────────────
define('CGPA_CLASS', [
    ['min' => 4.50, 'max' => 5.00, 'class' => 'First Class Honours'],
    ['min' => 3.50, 'max' => 4.49, 'class' => 'Second Class Upper'],
    ['min' => 2.40, 'max' => 3.49, 'class' => 'Second Class Lower'],
    ['min' => 1.50, 'max' => 2.39, 'class' => 'Third Class'],
    ['min' => 1.00, 'max' => 1.49, 'class' => 'Pass'],
    ['min' => 0.00, 'max' => 0.99, 'class' => 'Fail'],
]);
