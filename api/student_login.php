<?php
/**
 * API: Student Login
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$token = $_POST['csrf_token'] ?? '';
if (!csrf_verify($token)) {
    json_response(['success' => false, 'message' => 'Invalid request token.'], 403);
}

$result = student_login($_POST['matric_no'] ?? '', $_POST['password'] ?? '');
if ($result['success']) {
    $result['redirect'] = BASE_URL . '/student_result.php';
}

json_response($result);
