<?php
require_once __DIR__ . '/includes/bootstrap.php';
session_boot();

if (is_student_logged_in()) {
    redirect(BASE_URL . '/student_result.php');
}

$msg = isset($_GET['msg']) ? clean($_GET['msg']) : '';
$registered = isset($_GET['registered']) && $_GET['registered'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login - SRMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<script>const BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<div class="login-page">
  <div class="login-bg-grid"></div>
  <div class="login-bg-glow"></div>

  <div class="login-box">
    <div class="login-logo">
      <span class="logo-icon">●</span>
      <h1>Student Portal</h1>
      <p>Sign in to view your academic results</p>
    </div>

    <?php if ($msg === 'session_expired'): ?>
    <div class="alert alert-warning">Your student session has expired. Please log in again.</div>
    <?php endif; ?>
    <?php if ($registered): ?>
    <div class="alert alert-success">Registration successful. Please log in with your matric number and password.</div>
    <?php endif; ?>

    <form id="login-form" action="<?= BASE_URL ?>/api/student_login.php" method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label class="form-label" for="matric_no">Matric Number</label>
        <input
          type="text"
          id="matric_no"
          name="matric_no"
          class="form-control"
          placeholder="e.g. CSC/2020/001"
          autocomplete="username"
          required
          style="text-transform:uppercase;font-family:var(--font-mono)"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          placeholder="Enter your password"
          autocomplete="current-password"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;padding:14px">
        Sign In
      </button>
    </form>

    <p style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-muted)">
      No student account yet? <a href="<?= BASE_URL ?>/student_register.php">Register here</a>
    </p>
    <p style="text-align:center;margin-top:8px;font-size:13px;color:var(--text-muted)">
      Staff? <a href="<?= BASE_URL ?>/index.php">Admin login</a>
    </p>
  </div>
</div>
<div id="toast-container"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
