<?php
/**
 * API: Get student result
 * GET /api/get_result.php?[matric_no=XXX][&session=2023/2024][&semester=1]
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$isAdmin = is_logged_in();
$isStudent = is_student_logged_in();

if (!$isAdmin && !$isStudent) {
    json_response(['success' => false, 'message' => 'Please log in to view results.'], 401);
}

$requestedMatric = sanitize_matric($_GET['matric_no'] ?? '');
$matric = $isStudent ? sanitize_matric($_SESSION['student_matric'] ?? '') : $requestedMatric;
$session  = clean($_GET['session']  ?? '');
$semester = (int)($_GET['semester'] ?? 0);

if ($isStudent && $requestedMatric && $requestedMatric !== $matric) {
    json_response(['success' => false, 'message' => 'You can only view your own results.'], 403);
}

if (empty($matric)) {
    json_response(['success' => false, 'message' => 'Matriculation number is required.']);
}

// Fetch student
$student = db_row('SELECT * FROM students WHERE matric_no = ?', [$matric]);
if (!$student) {
    json_response(['success' => false, 'message' => 'Student not found. Please verify your matriculation number.']);
}

// Get full result data
$resultData = get_student_results($matric);

// Optional filters
if (!empty($session) || $semester > 0) {
    $resultData['semesters'] = array_values(array_filter($resultData['semesters'], function($sem) use ($session, $semester) {
        $ok = true;
        if (!empty($session))  $ok = $ok && ($sem['session'] === $session);
        if ($semester > 0)     $ok = $ok && ((int)$sem['semester'] === $semester);
        return $ok;
    }));
}

if (empty($resultData['semesters'])) {
    json_response(['success' => false, 'message' => 'No results found for the specified filters.']);
}

json_response([
    'success' => true,
    'data'    => array_merge($resultData, ['student' => $student]),
]);
