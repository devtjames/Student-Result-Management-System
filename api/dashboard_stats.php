<?php
/**
 * API: Dashboard statistics (AJAX)
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized.'], 401);
}

// ── Totals ────────────────────────────────────────────────────────────────────
$total_students = (int)db_row('SELECT COUNT(*) AS c FROM students')['c'];
$total_courses  = (int)db_row('SELECT COUNT(*) AS c FROM courses')['c'];
$total_results  = (int)db_row('SELECT COUNT(*) AS c FROM results_raw')['c'];
$carryovers     = (int)db_row('SELECT COUNT(*) AS c FROM results_raw WHERE is_carryover = 1')['c'];

// ── Grade distribution ────────────────────────────────────────────────────────
$gradeRows = db_all(
    "SELECT
        CASE
            WHEN score >= 70 THEN 'A'
            WHEN score >= 60 THEN 'B'
            WHEN score >= 50 THEN 'C'
            WHEN score >= 45 THEN 'D'
            WHEN score >= 40 THEN 'E'
            ELSE 'F'
        END AS grade,
        COUNT(*) AS count
     FROM results_raw
     GROUP BY grade
     ORDER BY grade ASC"
);

// ── Pass / Fail ───────────────────────────────────────────────────────────────
$passFail = db_row(
    "SELECT
        SUM(score >= 40) AS pass,
        SUM(score <  40) AS fail
     FROM results_raw"
);

// ── GPA distribution (buckets 0–1, 1–2, 2–3, 3–4, 4–5) ──────────────────────
// Per student CGPA
$cgpaRows = db_all(
    'SELECT
        r.matric_no,
        SUM(
            CASE
                WHEN r.score >= 70 THEN 5
                WHEN r.score >= 60 THEN 4
                WHEN r.score >= 50 THEN 3
                WHEN r.score >= 45 THEN 2
                WHEN r.score >= 40 THEN 1
                ELSE 0
            END * c.course_unit
        ) / SUM(c.course_unit) AS cgpa
     FROM results_raw r
     JOIN courses c ON r.course_code = c.course_code
     GROUP BY r.matric_no'
);

$buckets = [
    ['range' => '0.0–1.0', 'min' => 0.0, 'max' => 1.0, 'count' => 0],
    ['range' => '1.0–2.0', 'min' => 1.0, 'max' => 2.0, 'count' => 0],
    ['range' => '2.0–3.0', 'min' => 2.0, 'max' => 3.0, 'count' => 0],
    ['range' => '3.0–4.0', 'min' => 3.0, 'max' => 4.0, 'count' => 0],
    ['range' => '4.0–5.0', 'min' => 4.0, 'max' => 5.01,'count' => 0],
];
foreach ($cgpaRows as $row) {
    $cgpa = (float)$row['cgpa'];
    foreach ($buckets as &$b) {
        if ($cgpa >= $b['min'] && $cgpa < $b['max']) { $b['count']++; break; }
    }
    unset($b);
}

// ── Top 10 students by CGPA ───────────────────────────────────────────────────
$topStudents = db_all(
    'SELECT
        r.matric_no,
        s.full_name,
        s.department,
        ROUND(
            SUM(
                CASE
                    WHEN r.score >= 70 THEN 5
                    WHEN r.score >= 60 THEN 4
                    WHEN r.score >= 50 THEN 3
                    WHEN r.score >= 45 THEN 2
                    WHEN r.score >= 40 THEN 1
                    ELSE 0
                END * c.course_unit
            ) / SUM(c.course_unit), 2
        ) AS cgpa
     FROM results_raw r
     JOIN courses c ON r.course_code = c.course_code
     JOIN students s ON r.matric_no = s.matric_no
     GROUP BY r.matric_no, s.full_name, s.department
     ORDER BY cgpa DESC
     LIMIT 10'
);

json_response([
    'success' => true,
    'data'    => [
        'total_students' => $total_students,
        'total_courses'  => $total_courses,
        'total_results'  => $total_results,
        'carryovers'     => $carryovers,
        'grade_dist'     => $gradeRows,
        'pass_fail'      => ['pass' => (int)($passFail['pass'] ?? 0), 'fail' => (int)($passFail['fail'] ?? 0)],
        'gpa_buckets'    => $buckets,
        'top_students'   => $topStudents,
    ],
]);
