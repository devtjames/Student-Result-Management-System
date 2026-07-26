<?php
/**
 * Authentication Functions
 */

function session_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false, // set true in production with HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    session_boot();
    return isset($_SESSION['admin_id'], $_SESSION['admin_user']);
}

function is_student_logged_in(): bool {
    session_boot();
    return isset($_SESSION['student_matric'], $_SESSION['student_name']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/index.php?msg=session_expired');
        exit;
    }
    refresh_session_id();
}

function require_student_login(): void {
    if (!is_student_logged_in()) {
        header('Location: ' . BASE_URL . '/student_login.php?msg=session_expired');
        exit;
    }
    refresh_session_id();
}

function refresh_session_id(): void {
    if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

function ensure_student_accounts_table(): void {
    db_run(
        "CREATE TABLE IF NOT EXISTS student_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            matric_no VARCHAR(20) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME NULL,
            FOREIGN KEY (matric_no) REFERENCES students(matric_no) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB"
    );
}

function admin_login(string $username, string $password): array {
    $username = trim($username);

    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password are required.'];
    }

    $admin = db_row(
        'SELECT id, username, password, full_name
         FROM admins
         WHERE username = ?
         LIMIT 1',
        [$username]
    );

    if (!$admin || !password_verify($password, $admin['password'])) {
        sleep(1);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    session_boot();
    session_regenerate_id(true);
    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_user'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['last_regen'] = time();

    db_run('UPDATE admins SET last_login = NOW() WHERE id = ?', [$admin['id']]);

    return ['success' => true, 'message' => 'Login successful.'];
}

function student_register(string $matric_no, string $email, string $password, string $confirm): array {
    ensure_student_accounts_table();

    $matric_no = sanitize_matric($matric_no);
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

    if (!$matric_no || !$password || !$confirm) {
        return ['success' => false, 'message' => 'Matric number and password are required.'];
    }
    if ($password !== $confirm) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    $student = db_row('SELECT * FROM students WHERE matric_no = ?', [$matric_no]);
    if (!$student) {
        sleep(1);
        return ['success' => false, 'message' => 'Matric number was not found in the student records.'];
    }

    if (!empty($student['email']) && (!$email || strcasecmp($email, $student['email']) !== 0)) {
        return ['success' => false, 'message' => 'Please use the email address on your student record.'];
    }

    $existing = db_row('SELECT id FROM student_accounts WHERE matric_no = ?', [$matric_no]);
    if ($existing) {
        return ['success' => false, 'message' => 'This matric number already has an account. Please log in.'];
    }

    db_run(
        'INSERT INTO student_accounts (matric_no, password, email) VALUES (?, ?, ?)',
        [$matric_no, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $email ?: ($student['email'] ?? null)]
    );

    return ['success' => true, 'message' => 'Registration successful.'];
}

function student_login(string $matric_no, string $password): array {
    ensure_student_accounts_table();

    $matric_no = sanitize_matric($matric_no);
    if (!$matric_no || !$password) {
        return ['success' => false, 'message' => 'Matric number and password are required.'];
    }

    $account = db_row(
        'SELECT a.*, s.full_name, s.department FROM student_accounts a JOIN students s ON s.matric_no = a.matric_no WHERE a.matric_no = ?',
        [$matric_no]
    );

    if (!$account || !password_verify($password, $account['password'])) {
        sleep(1);
        return ['success' => false, 'message' => 'Invalid matric number or password.'];
    }

    student_set_session($account);
    db_run('UPDATE student_accounts SET last_login = NOW() WHERE id = ?', [$account['id']]);

    return ['success' => true, 'message' => 'Login successful.'];
}

function student_set_session(array $student): void {
    session_boot();
    session_regenerate_id(true);
    $_SESSION['student_matric'] = $student['matric_no'];
    $_SESSION['student_name']   = $student['full_name'];
    $_SESSION['student_dept']   = $student['department'] ?? '';
    $_SESSION['last_regen']     = time();
}

function admin_logout(): void {
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function student_logout(): void {
    session_boot();
    unset($_SESSION['student_matric'], $_SESSION['student_name'], $_SESSION['student_dept']);
}

function csrf_token(): string {
    session_boot();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(string $token): bool {
    session_boot();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
