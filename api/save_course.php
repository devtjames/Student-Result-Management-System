<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');
if (!is_logged_in()) json_response(['success'=>false,'message'=>'Unauthorized.'],401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'Method not allowed.'],405);

$code  = strtoupper(clean($_POST['course_code']  ?? ''));
$title = clean($_POST['course_title'] ?? '');
$unit  = (int)($_POST['course_unit']  ?? 3);
$sem   = (int)($_POST['semester']     ?? 0);
$level = (int)($_POST['level']        ?? 0);

if (!$code || !$title || $unit < 1 || $unit > 6) {
    json_response(['success'=>false,'message'=>'Course code, title, and valid unit (1–6) are required.']);
}

$existing = db_row('SELECT course_code FROM courses WHERE course_code = ?', [$code]);
if ($existing) {
    db_run(
        'UPDATE courses SET course_title=?, course_unit=?, semester=?, level=? WHERE course_code=?',
        [$title, $unit, $sem ?: null, $level ?: null, $code]
    );
    json_response(['success'=>true,'message'=>'Course updated.']);
} else {
    db_run(
        'INSERT INTO courses (course_code, course_title, course_unit, semester, level) VALUES (?,?,?,?,?)',
        [$code, $title, $unit, $sem ?: null, $level ?: null]
    );
    json_response(['success'=>true,'message'=>'Course added.']);
}
