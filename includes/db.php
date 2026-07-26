<?php
/**
 * Database Connection — Singleton PDO helper
 */

function get_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Never expose DB credentials in error message
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Database connection failed. Please contact the administrator.']));
    }

    return $pdo;
}

/**
 * Execute a prepared statement and return the statement object.
 * Usage: $stmt = db_run("SELECT * FROM students WHERE matric_no = ?", [$matric]);
 */
function db_run(string $sql, array $params = []): PDOStatement {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single row.
 */
function db_row(string $sql, array $params = []): ?array {
    $row = db_run($sql, $params)->fetch();
    return $row ?: null;
}

/**
 * Fetch all rows.
 */
function db_all(string $sql, array $params = []): array {
    return db_run($sql, $params)->fetchAll();
}

/**
 * Return the last inserted ID.
 */
function db_last_id(): string {
    return get_db()->lastInsertId();
}
