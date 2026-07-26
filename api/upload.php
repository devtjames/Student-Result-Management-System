<?php
/**
 * API: Upload Results (.xlsx)
 * POST /api/upload.php
 * Requires admin login.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Auth
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// CSRF
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_response(['success' => false, 'message' => 'Invalid request token.'], 403);
}

// ── File validation ───────────────────────────────────────────────────────────
if (empty($_FILES['result_file']) || $_FILES['result_file']['error'] !== UPLOAD_ERR_OK) {
    $errMsg = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
    ];
    $code = $_FILES['result_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    json_response(['success' => false, 'message' => $errMsg[$code] ?? 'File upload error.']);
}

$file     = $_FILES['result_file'];
$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if ($ext !== 'xlsx') {
    json_response(['success' => false, 'message' => 'Only .xlsx files are supported.']);
}
if ($file['size'] > MAX_FILE_SIZE) {
    json_response(['success' => false, 'message' => 'File exceeds 10 MB limit.']);
}

// Move file to temp folder
$safeName  = 'upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.xlsx';
$tmpPath   = UPLOAD_DIR . $safeName;

if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
    json_response(['success' => false, 'message' => 'Failed to save uploaded file.']);
}

// ── Load PhpSpreadsheet ───────────────────────────────────────────────────────
$autoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    @unlink($tmpPath);
    json_response(['success' => false, 'message' => 'PhpSpreadsheet not installed. Run: composer require phpoffice/phpspreadsheet']);
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;

// ── Required form fields ──────────────────────────────────────────────────────
$academic_session = clean($_POST['academic_session'] ?? '');
if (!valid_session($academic_session)) {
    @unlink($tmpPath);
    json_response(['success' => false, 'message' => 'Invalid academic session format. Use YYYY/YYYY (e.g. 2023/2024).']);
}

// ── Parse spreadsheet ─────────────────────────────────────────────────────────
try {
    $spreadsheet = IOFactory::load($tmpPath);
    $sheet       = $spreadsheet->getActiveSheet();
    $rows        = $sheet->toArray(null, true, true, true); // assoc keys: A, B, C…
} catch (Exception $e) {
    @unlink($tmpPath);
    json_response(['success' => false, 'message' => 'Could not read file: ' . $e->getMessage()]);
}

// ── Expected columns: matric_no, course_code, level, semester, score ──────────
// Header row is row 1; detect column positions
$headerRow = array_shift($rows);
if (!$headerRow) {
    @unlink($tmpPath);
    json_response(['success' => false, 'message' => 'Spreadsheet appears to be empty.']);
}

$colMap = [];
foreach ($headerRow as $col => $header) {
    if ($header === null) continue;
    $colMap[strtolower(trim((string)$header))] = $col;
}

$required = ['matric_no', 'course_code', 'level', 'semester', 'score'];
foreach ($required as $field) {
    if (!isset($colMap[$field])) {
        @unlink($tmpPath);
        json_response(['success' => false, 'message' => "Missing column: '$field'. Required columns: " . implode(', ', $required)]);
    }
}

// ── Create upload log entry ───────────────────────────────────────────────────
$logId = null;
try {
    db_run(
        'INSERT INTO upload_logs (admin_id, filename, total_rows, status) VALUES (?, ?, ?, ?)',
        [$_SESSION['admin_id'], $origName, count($rows), 'processing']
    );
    $logId = db_last_id();
} catch (Exception $e) { /* non-fatal */ }

// ── Process rows ──────────────────────────────────────────────────────────────
$inserted  = 0;
$updated   = 0;
$skipped   = 0;
$rowErrors = [];
$rowNum    = 1; // starts after header

$pdo = get_db();

// Preload courses into memory for fast lookup
$courseCache = [];
$allCourses  = db_all('SELECT course_code, course_unit FROM courses');
foreach ($allCourses as $c) $courseCache[$c['course_code']] = $c;

// Preload students
$studentCache = [];
$allStudents  = db_all('SELECT matric_no FROM students');
foreach ($allStudents as $s) $studentCache[$s['matric_no']] = true;

$pdo->beginTransaction();
try {
    foreach ($rows as $row) {
        $rowNum++;
        $matric     = strtoupper(trim((string)($row[$colMap['matric_no']]  ?? '')));
        $courseCode = strtoupper(trim((string)($row[$colMap['course_code']] ?? '')));
        $level      = (int)($row[$colMap['level']]    ?? 0);
        $semester   = (int)($row[$colMap['semester']] ?? 0);
        $score      = (float)($row[$colMap['score']]  ?? -1);

        // ── Row-level validation ───────────────────────────────────────────────
        if (empty($matric) || empty($courseCode)) {
            $rowErrors[] = "Row $rowNum: Empty matric_no or course_code — skipped.";
            $skipped++;
            continue;
        }
        if (!preg_match('/^\d{3}$/', (string)$level) || !in_array($level, [100,200,300,400,500,600])) {
            $rowErrors[] = "Row $rowNum ($matric): Invalid level '$level' — must be 100–600.";
            $skipped++;
            continue;
        }
        if (!in_array($semester, [1, 2])) {
            $rowErrors[] = "Row $rowNum ($matric): Invalid semester '$semester' — must be 1 or 2.";
            $skipped++;
            continue;
        }
        if ($score < 0 || $score > 100) {
            $rowErrors[] = "Row $rowNum ($matric): Score '$score' out of range (0–100).";
            $skipped++;
            continue;
        }
        if (!isset($studentCache[$matric])) {
            $rowErrors[] = "Row $rowNum: Student '$matric' not found in database — skipped.";
            $skipped++;
            continue;
        }
        if (!isset($courseCache[$courseCode])) {
            $rowErrors[] = "Row $rowNum: Course '$courseCode' not found in database — skipped.";
            $skipped++;
            continue;
        }

        // ── Carry-over detection ───────────────────────────────────────────────
        // Check if student already has this course at a lower level/session
        $prevRecord = db_row(
            'SELECT id, level, academic_session FROM results_raw
             WHERE matric_no = ? AND course_code = ?
             ORDER BY level ASC, academic_session ASC
             LIMIT 1',
            [$matric, $courseCode]
        );

        $isCarryover = 0;
        if ($prevRecord) {
            // If the new record is for a higher level or newer session → carryover
            if ((int)$prevRecord['level'] < $level || $prevRecord['academic_session'] < $academic_session) {
                $isCarryover = 1;
            }
        }

        // ── Upsert ────────────────────────────────────────────────────────────
        // ON DUPLICATE KEY: same matric+course+session+semester+level → update score
        $sql = 'INSERT INTO results_raw
                    (matric_no, course_code, academic_session, semester, level, score, is_carryover, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    score        = VALUES(score),
                    is_carryover = VALUES(is_carryover),
                    uploaded_by  = VALUES(uploaded_by),
                    uploaded_at  = NOW()';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$matric, $courseCode, $academic_session, $semester, $level, $score, $isCarryover, $_SESSION['admin_id']]);

        if ($stmt->rowCount() === 1) {
            $inserted++;
        } elseif ($stmt->rowCount() === 2) {
            $updated++;
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    @unlink($tmpPath);
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// ── Update log ────────────────────────────────────────────────────────────────
if ($logId) {
    db_run(
        'UPDATE upload_logs SET total_rows=?, inserted=?, updated=?, skipped=?, errors=?, status=? WHERE id=?',
        [
            $rowNum - 1, $inserted, $updated, $skipped,
            $rowErrors ? implode("\n", $rowErrors) : null,
            'completed',
            $logId,
        ]
    );
}

// ── Cleanup temp file ─────────────────────────────────────────────────────────
@unlink($tmpPath);

json_response([
    'success'    => true,
    'message'    => 'Upload processed successfully.',
    'total_rows' => $rowNum - 1,
    'inserted'   => $inserted,
    'updated'    => $updated,
    'skipped'    => $skipped,
    'errors'     => array_slice($rowErrors, 0, 20), // cap at 20 for response size
]);
