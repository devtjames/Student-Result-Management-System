<?php
// api/get_course.php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');
if (!is_logged_in()) json_response(['success'=>false,'message'=>'Unauthorized.'],401);
$code = strtoupper(clean($_GET['code'] ?? ''));
if (!$code) json_response(['success'=>false,'message'=>'Course code required.']);
$c = db_row('SELECT * FROM courses WHERE course_code = ?', [$code]);
if (!$c) json_response(['success'=>false,'message'=>'Course not found.'],404);
json_response(['success'=>true,'data'=>$c]);
