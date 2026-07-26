<?php
/**
 * Grading, GPA, CGPA Helper Functions
 */

// ── Compute grade & grade point from a score ──────────────────────────────────
function compute_grade(float $score): array {
    foreach (GRADE_SCALE as $g) {
        if ($score >= $g['min'] && $score <= $g['max']) {
            return ['grade' => $g['grade'], 'gp' => $g['gp'], 'remark' => $g['remark']];
        }
    }
    return ['grade' => 'F', 'gp' => 0, 'remark' => 'Fail'];
}

// ── Compute GPA for a list of result rows ─────────────────────────────────────
// Each row must have: score, course_unit
function compute_gpa(array $rows): array {
    $total_units = 0;
    $total_qp    = 0;

    foreach ($rows as $row) {
        $g            = compute_grade((float)$row['score']);
        $unit         = (int)$row['course_unit'];
        $qp           = $g['gp'] * $unit;
        $total_units += $unit;
        $total_qp    += $qp;
    }

    $gpa = $total_units > 0 ? round($total_qp / $total_units, 2) : 0.00;

    return [
        'total_units' => $total_units,
        'total_qp'    => $total_qp,
        'gpa'         => $gpa,
    ];
}

// ── Get classification from CGPA ─────────────────────────────────────────────
function get_classification(float $cgpa): string {
    foreach (CGPA_CLASS as $c) {
        if ($cgpa >= $c['min'] && $cgpa <= $c['max']) {
            return $c['class'];
        }
    }
    return 'Fail';
}

// ── Fetch all results for a student (with grades computed) ────────────────────
function get_student_results(string $matric_no): array {
    $rows = db_all(
        'SELECT r.*, c.course_title, c.course_unit
         FROM results_raw r
         JOIN courses c ON r.course_code = c.course_code
         WHERE r.matric_no = ?
         ORDER BY r.level ASC, r.semester ASC, c.course_code ASC',
        [$matric_no]
    );

    // Group by session → semester
    $grouped = [];
    $cumulative_units = 0;
    $cumulative_qp    = 0;
    $semester_history = []; // for CGPA trend

    foreach ($rows as $row) {
        $g   = compute_grade((float)$row['score']);
        $qp  = $g['gp'] * (int)$row['course_unit'];
        $key = $row['academic_session'] . '|' . $row['semester'] . '|' . $row['level'];

        $grouped[$key]['session']  = $row['academic_session'];
        $grouped[$key]['semester'] = $row['semester'];
        $grouped[$key]['level']    = $row['level'];
        $grouped[$key]['courses'][] = array_merge($row, [
            'grade'         => $g['grade'],
            'grade_point'   => $g['gp'],
            'quality_point' => $qp,
            'remark'        => $g['remark'],
        ]);
    }

    // Compute GPA per semester group and running CGPA
    $result = [];
    foreach ($grouped as $key => $data) {
        $sem_units = 0;
        $sem_qp    = 0;
        foreach ($data['courses'] as $c) {
            $sem_units       += (int)$c['course_unit'];
            $sem_qp          += $c['quality_point'];
            $cumulative_units += (int)$c['course_unit'];
            $cumulative_qp   += $c['quality_point'];
        }
        $gpa  = $sem_units  > 0 ? round($sem_qp  / $sem_units,  2) : 0.00;
        $cgpa = $cumulative_units > 0 ? round($cumulative_qp / $cumulative_units, 2) : 0.00;

        $result[] = [
            'session'          => $data['session'],
            'semester'         => $data['semester'],
            'semester_label'   => $data['semester'] == 1 ? 'First Semester' : 'Second Semester',
            'level'            => $data['level'],
            'courses'          => $data['courses'],
            'sem_units'        => $sem_units,
            'sem_qp'           => $sem_qp,
            'gpa'              => $gpa,
            'cumulative_units' => $cumulative_units,
            'cumulative_qp'    => $cumulative_qp,
            'cgpa'             => $cgpa,
            'classification'   => get_classification($cgpa),
        ];

        $semester_history[] = [
            'label' => $data['session'] . ' Sem ' . $data['semester'],
            'gpa'   => $gpa,
            'cgpa'  => $cgpa,
        ];
    }

    return [
        'semesters'   => $result,
        'gpa_history' => $semester_history,
        'final_cgpa'  => $cumulative_units > 0 ? round($cumulative_qp / $cumulative_units, 2) : 0.00,
        'final_class' => get_classification(
            $cumulative_units > 0 ? round($cumulative_qp / $cumulative_units, 2) : 0.00
        ),
    ];
}

// ── Sanitize matric number input ──────────────────────────────────────────────
function sanitize_matric(string $input): string {
    return strtoupper(preg_replace('/[^A-Z0-9\/\-]/i', '', trim($input)));
}

// ── Format score with 2 decimal places ───────────────────────────────────────
function fmt_score(float $s): string {
    return number_format($s, 2);
}

// ── Semester label ────────────────────────────────────────────────────────────
function sem_label(int $sem): string {
    return $sem === 1 ? '1st Semester' : '2nd Semester';
}
