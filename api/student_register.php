<?php
/**
 * API: Student Registration
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

$result = student_register(
    $_POST['matric_no'] ?? '',
    $_POST['email'] ?? '',
    $_POST['password'] ?? '',
    $_POST['confirm_password'] ?? ''
);

if ($result['success']) {
    $result['redirect'] = BASE_URL . '/student_login.php?registered=1';
}

json_response($result);
