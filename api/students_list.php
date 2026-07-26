<?php
// api/students_list.php — Search/list students (admin)
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');
if (!is_logged_in()) json_response(['success'=>false,'message'=>'Unauthorized.'],401);

$q    = clean($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 50;
$off  = ($page - 1) * $per;

if ($q) {
    $like = "%$q%";
    $rows = db_all(
        'SELECT * FROM students WHERE matric_no LIKE ? OR full_name LIKE ? OR department LIKE ? ORDER BY matric_no LIMIT ? OFFSET ?',
        [$like, $like, $like, $per, $off]
    );
} else {
    $rows = db_all('SELECT * FROM students ORDER BY matric_no LIMIT ? OFFSET ?', [$per, $off]);
}
json_response(['success'=>true,'data'=>$rows]);
