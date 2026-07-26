<?php
/**
 * Layout Header Partial
 * @param string $title Page title
 * @param bool $admin Whether to render admin nav
 */
function render_header(string $title = 'SRMS Portal', bool $admin = false): void {
    $base = BASE_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#f4f7fb">
<title><?= htmlspecialchars($title) ?> - SRMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/main.css">
<?php if ($admin): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php endif; ?>
</head>
<body>
<?php if ($admin): ?>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="brand-icon">●</span>
      <span class="brand-text">SRMS</span>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= $base ?>/admin/dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
        <span class="nav-icon">□</span> Dashboard
      </a>
      <a href="<?= $base ?>/admin/upload.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'upload.php' ? 'active' : '' ?>">
        <span class="nav-icon">↑</span> Upload Results
      </a>
      <a href="<?= $base ?>/admin/students.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'students.php' ? 'active' : '' ?>">
        <span class="nav-icon">✦</span> Students
      </a>
      <a href="<?= $base ?>/admin/courses.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'courses.php' ? 'active' : '' ?>">
        <span class="nav-icon">⊞</span> Courses
      </a>
      <a href="<?= $base ?>/admin/results.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'results.php' ? 'active' : '' ?>">
        <span class="nav-icon">≡</span> All Results
      </a>
      <a href="<?= $base ?>/admin/top_students.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'top_students.php' ? 'active' : '' ?>">
        <span class="nav-icon">★</span> Top Students
      </a>
      <a href="<?= $base ?>/admin/security_dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'security_dashboard.php' ? 'active' : '' ?>">
        <span class="nav-icon">#</span> Security Dashboard
      </a>
      <a href="<?= $base ?>/admin/security_logs.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'security_logs.php' ? 'active' : '' ?>">
        <span class="nav-icon">!</span> Security Logs
      </a>
      <a href="<?= $base ?>/admin/blocked_ips.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'blocked_ips.php' ? 'active' : '' ?>">
        <span class="nav-icon">x</span> Blocked IPs
      </a>
      <a href="<?= $base ?>/admin/security_test.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'security_test.php' ? 'active' : '' ?>">
        <span class="nav-icon">?</span> Security Test
      </a>
    </nav>
    <div class="sidebar-footer">
      <span class="admin-name"><?= clean($_SESSION['admin_name'] ?? 'Admin') ?></span>
      <a href="<?= $base ?>/api/logout.php" class="logout-btn">Log Out</a>
    </div>
  </aside>

  <div class="main-wrap">
    <header class="topbar">
      <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
      <div class="topbar-right"></div>
    </header>
    <main class="main-content">
<?php else: ?>
<main class="public-main">
<?php endif; ?>
<?php }

function render_footer(bool $admin = false): void {
    $base = BASE_URL;
?>
<?php if ($admin): ?>
    </main>
  </div>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="<?= $base ?>/assets/js/main.js?v=<?= filemtime(BASE_PATH . '/assets/js/main.js') ?>"></script>
<?php if ($admin): ?>
<script src="<?= $base ?>/assets/js/admin.js"></script>
<?php endif; ?>
</body>
</html>
<?php } ?>
