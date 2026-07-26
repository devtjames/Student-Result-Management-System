<?php
require_once __DIR__ . '/includes/bootstrap.php';
session_boot();

if (is_logged_in()) {
    redirect(BASE_URL . '/admin/dashboard.php');
}

$msg = isset($_GET['msg']) ? clean($_GET['msg']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - SRMS</title>
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
      <h1>SRMS Portal</h1>
      <p>Student Result Management System</p>
    </div>

    <?php if ($msg === 'session_expired'): ?>
    <div class="alert alert-warning">Your session has expired. Please log in again.</div>
    <?php endif; ?>

    <form id="login-form" action="<?= BASE_URL ?>/api/login.php" method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input
          type="text"
          id="username"
          name="username"
          class="form-control"
          placeholder="Enter your username"
          autocomplete="username"
          required
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div style="position:relative">
          <input
            type="password"
            id="password"
            name="password"
            class="form-control"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
            style="padding-right:48px"
          >
          <button type="button"
            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px"
            onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'">
            Show
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;padding:14px">
        Sign In
      </button>
    </form>

    <p style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-muted)">
      Student? <a href="<?= BASE_URL ?>/student_login.php">Log in to check your results</a>
    </p>
  </div>
</div>
<div id="toast-container"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
