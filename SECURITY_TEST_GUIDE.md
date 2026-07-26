# SRMS SQL Injection Security Test Guide

Use this guide during installation, project demonstration and student presentation.

## Before Testing

1. Import the main database if it has not already been imported:

```bash
mysql -u root -p < schema.sql
```

2. Import the security upgrade tables:

```bash
mysql -u root -p srms_db < database/security_upgrade.sql
```

3. Confirm these values in `config.php` for demonstration:

```php
define('DEV_MODE', true);
define('SQLI_DETECTION_ENABLED', true);
define('SECURITY_TEST_PAGE_ENABLED', false);
```

4. Log in as admin:

```text
http://localhost/srms/index.php
```

Default credentials:

```text
Username: admin
Password: Admin@1234
```

## Admin Security Pages

Use these pages to monitor and explain the security system:

```text
/admin/security_dashboard.php
/admin/security_logs.php
/admin/blocked_ips.php
/admin/security_test.php
```

What each page demonstrates:

- `security_dashboard.php`: total attempts, attempts today, blocked requests, high-risk events, blocked IPs and targeted endpoints.
- `security_logs.php`: logged attack details, affected field, endpoint, category, score and severity.
- `blocked_ips.php`: temporary IP blocks and CSRF-protected unblock action.
- `security_test.php`: safe detector-only SQL injection demonstration.

## Controlled SQL Injection Test Page

Open:

```text
http://localhost/srms/admin/security_test.php
```

Use this page first because it does not execute SQL against the real database.

### Payloads

```text
' OR 1=1 --
admin' --
1 UNION SELECT username, password FROM admins
1; DROP TABLE students
1 AND SLEEP(5)
information_schema.tables
```

Expected result:

- Detection status is shown.
- Risk score is shown.
- Severity is shown.
- Detection category is shown.
- The page shows whether the request would be blocked.
- The page confirms that no SQL was executed.
- The page shows prepared statement binding as the safe method.

## Live Endpoint Attack Tests

After each test, open:

```text
http://localhost/srms/admin/security_logs.php
```

Then open:

```text
http://localhost/srms/admin/security_dashboard.php
```

This lets the student see the blocked request and dashboard counts update.

## 1. Admin Login Attack

Page:

```text
http://localhost/srms/index.php
```

Endpoint:

```text
POST /api/login.php
```

Payload to enter in username:

```text
' OR 1=1 --
```

Expected result:

```text
Blocked with HTTP 403.
Logged as tautology/comment attack.
No database error is shown.
```

## 2. Student Result Matric Search Attack

Endpoint:

```text
GET /api/get_result.php?matric_no=
```

Payload:

```text
' OR 1=1 --
```

URL example:

```text
http://localhost/srms/api/get_result.php?matric_no=%27%20OR%201%3D1%20--
```

Expected result:

```text
Blocked with HTTP 403.
Logged against matric_no.
```

## 3. Student Search UNION Attack

Endpoint:

```text
GET /api/students_list.php?q=
```

Payload:

```text
1 UNION SELECT username, password FROM admins
```

URL example:

```text
http://localhost/srms/api/students_list.php?q=1%20UNION%20SELECT%20username,password%20FROM%20admins
```

Expected result:

```text
Blocked with HTTP 403.
Logged as UNION attack.
```

## 4. Course Search Stacked Query Attack

Endpoint:

```text
GET /api/get_course.php?code=
```

Payload:

```text
1; DROP TABLE courses
```

URL example:

```text
http://localhost/srms/api/get_course.php?code=1%3B%20DROP%20TABLE%20courses
```

Expected result:

```text
Blocked with HTTP 403.
Logged as stacked query or field validation failure.
```

## 5. Result Filter Attack

Page:

```text
http://localhost/srms/admin/results.php
```

Payload for the `session` filter:

```text
2023/2024' OR 1=1 --
```

URL example:

```text
http://localhost/srms/admin/results.php?session=2023%2F2024%27%20OR%201%3D1%20--&semester=1&level=100
```

Expected result:

```text
Blocked with a safe web error page.
Logged as suspicious input or validation failure.
```

## 6. Student Save Form Attack

Endpoint:

```text
POST /api/save_student.php
```

Payload for `full_name`:

```text
1; DROP TABLE students
```

Expected result:

```text
Blocked with HTTP 403.
Logged as stacked query.
No student record is created.
```

## 7. Course Save Form Attack

Endpoint:

```text
POST /api/save_course.php
```

Payload for `course_title`:

```text
1 AND SLEEP(5)
```

Expected result:

```text
Blocked with HTTP 403.
Logged as time-based payload.
No course record is created.
```

## 8. URL Parameter Database Discovery Attack

Page:

```text
GET /admin/dashboard.php
```

Payload:

```text
information_schema.tables
```

URL example:

```text
http://localhost/srms/admin/dashboard.php?probe=information_schema.tables
```

Expected result:

```text
Blocked with a safe web error page.
Logged as database discovery.
```

## 9. JSON Request Attack

Endpoint:

```text
POST /api/save_student.php
```

Header:

```text
Content-Type: application/json
```

Body:

```json
{
  "full_name": "1 UNION SELECT username,password FROM admins"
}
```

Expected result:

```text
Blocked with HTTP 403.
Logged as UNION attack.
```

## False Positive Tests

Use these values to prove normal academic input is not blocked.

Open each URL while logged in:

```text
http://localhost/srms/api/students_list.php?q=O%27Connor
http://localhost/srms/api/students_list.php?q=Union%20Secondary%20School
http://localhost/srms/api/students_list.php?q=Drop%20Zone%20Department
http://localhost/srms/api/students_list.php?q=CSC%2F2020%2F001
http://localhost/srms/api/students_list.php?q=2023%2F2024
http://localhost/srms/api/students_list.php?q=Aminu-Sule
http://localhost/srms/api/students_list.php?q=SELECTED%20STUDENT
```

Expected result:

```text
Normal JSON response.
No security block.
No SQL error.
```

## Temporary IP Blocking Demo

Use a medium or high-risk payload repeatedly from the same browser or IP:

```text
http://localhost/srms/api/students_list.php?q=1%20UNION%20SELECT%20username,password%20FROM%20admins
```

Repeat it five times within ten minutes.

Expected result:

```text
The IP address is temporarily blocked for thirty minutes.
Further requests return a safe temporary block response.
```

Then open:

```text
http://localhost/srms/admin/blocked_ips.php
```

Demonstrate:

- The blocked IP appears.
- The reason and attempt count are visible.
- The admin can unblock the IP using the unblock button.

## Suggested Presentation Flow

1. Log in as admin.
2. Open the Security Dashboard.
3. Explain total attempts, high-risk events and blocked requests.
4. Open the controlled Security Test page.
5. Run the prepared sample payloads.
6. Show that no SQL is executed on the test page.
7. Try one live endpoint attack.
8. Show the safe blocked response.
9. Open Security Logs and show the logged event.
10. Repeat the UNION attack five times to trigger temporary blocking.
11. Open Blocked IPs and show the active block.
12. Use the unblock button.
13. Run the false-positive tests to show normal academic values still work.

## Key Explanation for Students

Prepared statements are the primary SQL injection prevention method. Regex detection is used as an extra layer for identifying, blocking and recording suspicious input patterns.

Regex detection cannot catch every possible attack, so the system must still use prepared statements, strict validation, safe error messages and good access control.
