<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    json_response(['success' => false, 'message' => 'Invalid request token.'], 403);
}

$ipAddress = trim((string)($_POST['ip_address'] ?? ''));
if ($ipAddress === '' || strlen($ipAddress) > 45 || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
    json_response(['success' => false, 'message' => 'Invalid IP address.'], 400);
}

try {
    $unblocked = SecurityBlocker::unblockIp($ipAddress);
    if (!$unblocked) {
        json_response(['success' => false, 'message' => 'No active block was found for this IP address.'], 404);
    }

    json_response(['success' => true, 'message' => 'IP address unblocked.']);
} catch (Throwable $e) {
    security_log_private('Unblock API failed.', [
        'error' => $e->getMessage(),
        'ip_address' => $ipAddress,
    ]);
    json_response(['success' => false, 'message' => 'The unblock request could not be processed.'], 500);
}
