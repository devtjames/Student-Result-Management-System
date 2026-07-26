# SRMS - Student Result Management System

SRMS is a PHP and MySQL Student Result Management System with admin result upload, student/course management, GPA and CGPA calculation, result viewing, ranking, carry-over detection, and an SQL injection detection and prevention layer.

The project was built without a PHP framework and is suitable for academic demonstration, final-year projects, and institutional result management prototypes.

## Tech Stack

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.4+
- PDO
- HTML, CSS and JavaScript
- PhpSpreadsheet for Excel upload
- Apache with `.htaccess` rules, or Nginx

## Main Features

- Admin authentication
- Student registration and student login
- Student management
- Course management
- Excel result upload using `.xlsx`
- Public/student result checking
- GPA and CGPA calculation
- Degree classification
- Carry-over detection
- Student ranking by CGPA
- Admin dashboard statistics and charts
- SQL injection detection, logging and temporary IP blocking
- Admin security dashboard
- Controlled SQL injection test page for demonstration

## Folder Structure

```text
srms/
|-- index.php
|-- student_login.php
|-- student_register.php
|-- student_result.php
|-- config.php
|-- schema.sql
|-- SECURITY_TEST_GUIDE.md
|-- database/
|   `-- security_upgrade.sql
|-- includes/
|   |-- bootstrap.php
|   |-- db.php
|   |-- auth.php
|   |-- grades.php
|   |-- helpers.php
|   |-- layout.php
|   |-- security.php
|   |-- security_logger.php
|   `-- security_patterns.php
|-- api/
|   |-- login.php
|   |-- logout.php
|   |-- get_result.php
|   |-- upload.php
|   |-- dashboard_stats.php
|   |-- students_list.php
|   |-- get_student.php
|   |-- save_student.php
|   |-- get_course.php
|   |-- save_course.php
|   |-- unblock_ip.php
|   `-- run_security_test.php
|-- admin/
|   |-- dashboard.php
|   |-- upload.php
|   |-- students.php
|   |-- view_student.php
|   |-- courses.php
|   |-- results.php
|   |-- top_students.php
|   |-- security_dashboard.php
|   |-- security_logs.php
|   |-- blocked_ips.php
|   `-- security_test.php
|-- assets/
|   |-- css/main.css
|   `-- js/
|       |-- main.js
|       `-- admin.js
`-- uploads/
    `-- temp/
```

## Quick Setup

### 1. Requirements

Install:

- PHP 8.0 or higher
- MySQL 5.7 or MariaDB 10.4 or higher
- Composer
- Apache with `mod_rewrite`, XAMPP, Laragon, WAMP, or Nginx

### 2. Clone or Copy the Project

Place the project inside your web server directory.

Example for XAMPP:

```bash
C:/xampp/htdocs/srms
```

Example for Linux Apache:

```bash
/var/www/html/srms
```

### 3. Install PHP Dependencies

From the project root:

```bash
composer install
```

This installs PhpSpreadsheet for Excel result upload.

### 4. Create the Main Database

Run:

```bash
mysql -u root -p < schema.sql
```

Or open phpMyAdmin, create/import the database using `schema.sql`.

### 5. Import the Security Tables

After importing `schema.sql`, run:

```bash
mysql -u root -p srms_db < database/security_upgrade.sql
```

This adds:

- `security_logs`
- `blocked_ips`

It does not delete or modify the academic tables.

### 6. Configure `config.php`

Edit the database and app URL values:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'srms_db');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('BASE_URL', 'http://localhost/srms');
```

Do not add a trailing slash to `BASE_URL`.

### 7. Admin Login

Default admin credentials:

```text
Username: admin
Password: Admin@1234
```

If the password hash in `schema.sql` does not work in your environment, generate a new hash:

```php
<?php
echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]);
```

Then update the admin password:

```sql
UPDATE admins
SET password = '$2y$12$YOUR_GENERATED_HASH_HERE'
WHERE username = 'admin';
```

Change the default admin password before deployment.

## Excel Upload Format

Upload files must be `.xlsx` and must use these exact headers in row 1:

| matric_no | course_code | level | semester | score |
| --- | --- | --- | --- | --- |
| CSC/2020/001 | CSC101 | 100 | 1 | 72.5 |
| CSC/2020/001 | MTH101 | 100 | 1 | 65.0 |
| CSC/2020/002 | CSC101 | 100 | 1 | 85.0 |

Rules:

- `matric_no` must already exist in the `students` table.
- `course_code` must already exist in the `courses` table.
- `level` must be `100`, `200`, `300`, `400`, or `500`.
- `semester` must be `1` or `2`.
- `score` must be between `0` and `100`.
- Academic session is entered in the upload form, for example `2023/2024`.

## Grading System

| Score | Grade | Grade Point | Remark |
| --- | --- | --- | --- |
| 70-100 | A | 5 | Excellent |
| 60-69 | B | 4 | Good |
| 50-59 | C | 3 | Average |
| 45-49 | D | 2 | Below Average |
| 40-44 | E | 1 | Poor |
| 0-39 | F | 0 | Fail |

GPA is calculated per semester:

```text
GPA = total quality points / total course units
```

CGPA is cumulative:

```text
CGPA = all quality points / all course units
```

## Degree Classification

| CGPA | Class |
| --- | --- |
| 4.50-5.00 | First Class Honours |
| 3.50-4.49 | Second Class Upper |
| 2.40-3.49 | Second Class Lower |
| 1.50-2.39 | Third Class |
| 1.00-1.49 | Pass |
| 0.00-0.99 | Fail |

## Carry-Over Detection

A result is marked as carry-over when:

- The same student attempts the same course code again.
- The attempt is in a higher level, or
- The attempt is in a later academic session.

The system inserts a new result row marked `is_carryover = 1` so the previous academic record remains available.

## Security Upgrade Overview

Prepared statements are the primary SQL injection prevention method. Regex detection is used as an extra layer for identifying, blocking and recording suspicious input patterns.

Security features include:

- PDO prepared statements for database queries
- Regex-based SQL injection detection
- Risk scoring and severity classification
- Structured validation for academic fields
- Safe error responses for blocked requests
- Security event logging
- Repeated attack tracking
- Temporary IP blocking
- Admin security dashboard
- Security logs page
- Blocked IP management page
- Controlled SQL injection test page
- Central security configuration
- Security log cleanup helper

The regex detector is not a replacement for prepared statements. It cannot detect every possible SQL injection payload.

## Security Configuration

Security settings are in `config.php`:

```php
define('SQLI_DETECTION_ENABLED', true);
define('SQLI_LOW_SCORE', 20);
define('SQLI_MEDIUM_SCORE', 40);
define('SQLI_HIGH_SCORE', 70);
define('SQLI_BLOCK_THRESHOLD', 5);
define('SQLI_ATTEMPT_WINDOW_MINUTES', 10);
define('SQLI_BLOCK_DURATION_MINUTES', 30);
define('SECURITY_TEST_PAGE_ENABLED', false);
define('SECURITY_LOG_RETENTION_DAYS', 90);
```

Default behavior:

- Low-risk payloads may be logged.
- Medium and high-risk payloads are blocked.
- Five medium or high-risk detections from one IP within ten minutes creates a temporary thirty-minute block.
- Localhost bypass works only when `DEV_MODE` is true.

Disabling `SQLI_DETECTION_ENABLED` disables only regex detection. It does not disable prepared statements or normal field validation.

## Security Dashboard

After admin login, open:

```text
/admin/security_dashboard.php
```

The dashboard shows:

- Total detected attempts
- Attempts today
- Blocked requests
- High-risk events
- Blocked IP addresses
- Most targeted endpoints
- Charts for severity, dates, endpoints and detection categories

Additional pages:

```text
/admin/security_logs.php
/admin/blocked_ips.php
```

The logs page supports filtering and pagination. It displays safe input previews and detection categories without exposing full malicious payloads or internal regex source.

The blocked IP page includes a CSRF-protected unblock action.

## Controlled SQL Injection Test Page

For project presentation, open:

```text
/admin/security_test.php
```

The page is available when `DEV_MODE` is true. Outside development mode, it remains disabled unless:

```php
define('SECURITY_TEST_PAGE_ENABLED', true);
```

Use it to demonstrate payloads such as:

```text
' OR 1=1 --
admin' --
1 UNION SELECT username, password FROM admins
1; DROP TABLE students
1 AND SLEEP(5)
information_schema.tables
```

The test page does not execute malicious SQL. It runs only the detection engine and shows escaped educational text comparing unsafe concatenation with prepared statement binding.

For a complete presentation checklist, see:

```text
SECURITY_TEST_GUIDE.md
```

## Security Log Cleanup

Expired security logs can be cleaned up using:

```php
securityCleanupExpiredLogs();
```

This cleanup is not run on every request. Run it from a trusted maintenance script, CLI task, or scheduled job.

## Important Routes

Admin:

```text
/index.php
/admin/dashboard.php
/admin/students.php
/admin/courses.php
/admin/results.php
/admin/top_students.php
/admin/security_dashboard.php
/admin/security_logs.php
/admin/blocked_ips.php
/admin/security_test.php
```

Student:

```text
/student_login.php
/student_register.php
/student_result.php
```

API:

```text
/api/login.php
/api/upload.php
/api/get_result.php
/api/students_list.php
/api/save_student.php
/api/get_course.php
/api/save_course.php
/api/unblock_ip.php
/api/run_security_test.php
```

## Testing

Useful checks:

```bash
php -l includes/security.php
php -l includes/security_logger.php
php -l includes/security_patterns.php
php -l api/run_security_test.php
php -l admin/security_test.php
```

The final regression pass covered:

- Admin login
- Student management
- Course management
- Excel upload
- Result viewing
- GPA and CGPA calculation
- Degree classification
- Carry-over detection
- Student ranking
- Dashboard statistics
- Public/student result checking
- SQL injection blocking and logging
- False-positive checks for normal academic values

## Deployment Checklist

Before pushing live:

- Set `DEV_MODE` to `false`.
- Keep `SECURITY_TEST_PAGE_ENABLED` as `false`.
- Change the default admin password.
- Use a strong database password.
- Use a database user with only the permissions the app needs.
- Confirm `BASE_URL` matches the live URL.
- Serve the site over HTTPS.
- Ensure `/includes`, `/vendor`, and `/uploads/temp` are not directly browsable.
- Run the security migration on the production database.
- Review security logs regularly.
- Schedule security log cleanup.
- Do not commit real production database credentials.

## Known Limitations

- Regex detection cannot catch every SQL injection technique.
- Prepared statements remain the main SQL injection defense.
- The detector may need tuning if new form fields or routes are added.
- Security logs store shortened safe previews, not complete malicious payloads.
- Temporary IP blocking is not a replacement for firewall or web server rate limiting.
- Upload processing depends on PHP upload limits and PhpSpreadsheet availability.
- This project is a no-framework academic system and should be reviewed before production use.

## Troubleshooting

| Problem | Fix |
| --- | --- |
| Database connection failed | Check DB credentials in `config.php`. |
| PhpSpreadsheet not installed | Run `composer install`. |
| Upload returns class not found | Confirm `vendor/autoload.php` exists. |
| Blank page on admin | Temporarily set `DEV_MODE` to `true` and check the PHP error. |
| Session not persisting | Check `session.save_path` and browser cookie settings. |
| Fetch/CORS errors | Confirm `BASE_URL` exactly matches the browser URL. |
| Security dashboard has no data | Run attack tests or check that `security_upgrade.sql` was imported. |
| Test page disabled | Use `DEV_MODE=true` locally or explicitly enable `SECURITY_TEST_PAGE_ENABLED`. |

## License

MIT License. Free to use for educational and institutional purposes.
