<?php
/**
 * API: Login
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!csrf_verify($token)) {
    json_response(['success' => false, 'message' => 'Invalid request token.'], 403);
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$result = admin_login($username, $password);
if ($result['success']) {
    $result['redirect'] = BASE_URL . '/admin/dashboard.php';
}
json_response($result);
