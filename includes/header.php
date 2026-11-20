<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Global Header Component
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/report_helper.php';

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?><?= APP_NAME ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Flatpickr Dark Theme -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

  <!-- Custom Glassmorphism Stylesheet -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/glass.css">

  <script>
    window.BASE_URL = '<?= BASE_URL ?>';
    window.APP_USER_ROLE = '<?= $currentUser['role'] ?? '' ?>';
    window.APP_USER_ID = <?= $currentUser['user_id'] ?? 0 ?>;
    window.CSRF_TOKEN = '<?= generateCsrfToken() ?>';
  </script>
</head>
<body>

<?php if ($currentUser): ?>
<!-- Glassmorphism Navbar -->
<nav class="glass-navbar">
  <div class="nav-brand">
    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#6366f1;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>
    <span>KISMEC</span>
    <span class="brand-badge">INTERN-OPS</span>
  </div>

  <!-- Mobile Hamburger Toggle Button -->
  <button type="button" class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle navigation menu" aria-expanded="false">
    <svg class="icon-open" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    <svg class="icon-close" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
  </button>

  <ul class="nav-links" id="mainNavLinks">
    <li>
      <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Dashboard
      </a>
    </li>
    <li>
      <a href="<?= BASE_URL ?>/tasks.php" class="nav-link <?= $currentPage === 'tasks.php' ? 'active' : '' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        Tasks & Projects
      </a>
    </li>
    <li>
      <a href="<?= BASE_URL ?>/budgets.php" class="nav-link <?= $currentPage === 'budgets.php' ? 'active' : '' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Budgets
      </a>
    </li>
    <?php if (isAdmin()): ?>
    <li>
      <a href="<?= BASE_URL ?>/users.php" class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Users & Interns
      </a>
    </li>
    <?php endif; ?>
    <li>
      <a href="<?= BASE_URL ?>/reports.php" class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Reports & SLA
      </a>
    </li>

    <!-- Mobile Drawer Profile & Logout Footer -->
    <li class="nav-mobile-user">
      <div class="user-info-pill" style="width:100%;margin-bottom:0.25rem;">
        <div class="user-avatar">
          <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-size:0.875rem;font-weight:700;line-height:1.2;color:#fff;"><?= htmlspecialchars($currentUser['full_name']) ?></div>
          <div style="font-size:0.725rem;color:var(--text-muted);text-transform:uppercase;">
            <?= htmlspecialchars($currentUser['role']) ?> &bull; <?= htmlspecialchars($currentUser['department'] ?: 'KISMEC') ?>
          </div>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="glass-btn glass-btn-danger" style="width:100%;justify-content:center;padding:0.75rem;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Sign Out / Exit Portal
      </a>
    </li>
  </ul>

  <div class="nav-user">
    <div class="user-info-pill">
      <div class="user-avatar">
        <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
      </div>
      <div>
        <div style="font-size:0.825rem;font-weight:700;line-height:1.2;"><?= htmlspecialchars($currentUser['full_name']) ?></div>
        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;">
          <?= htmlspecialchars($currentUser['role']) ?> &bull; <?= htmlspecialchars($currentUser['department'] ?: 'KISMEC') ?>
        </div>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/logout.php" class="glass-btn glass-btn-sm" title="Log out of system">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Exit
    </a>
  </div>
</nav>
<?php endif; ?>
