<?php
/**
 * General Helper Functions
 */

// ── Output a JSON response and die ───────────────────────────────────────────
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Sanitize a plain string ───────────────────────────────────────────────────
function clean(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// ── Format a float as GPA (2 dp) ─────────────────────────────────────────────
function fmt_gpa(float $v): string {
    return number_format($v, 2);
}

// ── Grade badge color ─────────────────────────────────────────────────────────
function grade_color(string $grade): string {
    return match($grade) {
        'A' => '#10b981',
        'B' => '#3b82f6',
        'C' => '#f59e0b',
        'D' => '#f97316',
        'E' => '#ef4444',
        'F' => '#7f1d1d',
        default => '#6b7280',
    };
}

// ── Classification badge color ────────────────────────────────────────────────
function class_color(string $class): string {
    return match(true) {
        str_contains($class, 'First')        => '#10b981',
        str_contains($class, 'Second Upper') => '#3b82f6',
        str_contains($class, 'Second Lower') => '#6366f1',
        str_contains($class, 'Third')        => '#f59e0b',
        str_contains($class, 'Pass')         => '#f97316',
        default                              => '#ef4444',
    };
}

// ── Redirect helper ───────────────────────────────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── Flash message (session-based) ────────────────────────────────────────────
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ── Validate academic session string (e.g. 2023/2024) ────────────────────────
function valid_session(string $s): bool {
    return (bool) preg_match('/^\d{4}\/\d{4}$/', $s);
}

// ── Current academic session guess ───────────────────────────────────────────
function current_session(): string {
    $y = (int) date('Y');
    $m = (int) date('n');
    // Nigerian academic year: Oct–Sep
    return $m >= 10 ? "$y/" . ($y + 1) : ($y - 1) . "/$y";
}
