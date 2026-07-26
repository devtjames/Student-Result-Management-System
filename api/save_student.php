<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');
if (!is_logged_in()) json_response(['success'=>false,'message'=>'Unauthorized.'],401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'Method not allowed.'],405);

$matric  = sanitize_matric($_POST['matric_no']      ?? '');
$name    = clean($_POST['full_name']    ?? '');
$dept    = clean($_POST['department']   ?? '');
$prog    = clean($_POST['programme']    ?? 'B.Sc');
$year    = (int)($_POST['admission_year'] ?? 0);
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

if (!$matric || !$name || !$dept) {
    json_response(['success'=>false,'message'=>'Matric no, full name, and department are required.']);
}

$existing = db_row('SELECT matric_no FROM students WHERE matric_no = ?', [$matric]);
if ($existing) {
    db_run(
        'UPDATE students SET full_name=?, department=?, programme=?, admission_year=?, email=? WHERE matric_no=?',
        [$name, $dept, $prog, $year ?: null, $email ?: null, $matric]
    );
    json_response(['success'=>true,'message'=>'Student updated successfully.']);
} else {
    db_run(
        'INSERT INTO students (matric_no, full_name, department, programme, admission_year, email) VALUES (?,?,?,?,?,?)',
        [$matric, $name, $dept, $prog, $year ?: null, $email ?: null]
    );
    json_response(['success'=>true,'message'=>'Student added successfully.']);
}
