<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');
if (!is_logged_in()) json_response(['success'=>false,'message'=>'Unauthorized.'],401);

$matric = sanitize_matric($_GET['matric'] ?? '');
if (!$matric) json_response(['success'=>false,'message'=>'Matric required.']);
$s = db_row('SELECT * FROM students WHERE matric_no = ?', [$matric]);
if (!$s) json_response(['success'=>false,'message'=>'Not found.'], 404);
json_response(['success'=>true,'data'=>$s]);
