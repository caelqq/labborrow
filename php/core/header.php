<?php $loggedIn = isset($_SESSION['user_id']); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Campus Lab Equipment Borrowing System</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/theme.js"></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if (!empty($showSidebar)) { require __DIR__ . '/sidebar.php'; } ?>
<div class="system-name">
    Campus Lab Equipment Borrowing System
    <button type="button" class="theme-switch" id="theme-toggle" aria-label="Switch between day mode and night mode">
        <span class="sw-text sw-day">DAYMODE</span>
        <span class="sw-text sw-night">NIGHTMODE</span>
        <span class="sw-knob">
            <svg class="icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <svg class="icon-moon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        </span>
    </button>
</div>
<header class="site-header">
    <?php if (!empty($showStaffMenu)): ?>
        <button type="button" class="menu-btn" id="menu-btn" aria-label="Open menu">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    <?php endif; ?>
    <?php if ($loggedIn): ?>
        <span class="header-user">Logged in as <strong><?= e($_SESSION['username']) ?></strong></span>
    <?php endif; ?>
    <nav>
        <a href="index.php">Home</a>
        <?php if ($loggedIn): ?>
            <a href="logout.php">Log-out</a>
        <?php else: ?>
            <?php if ($currentPage !== 'register'): ?>
                <a href="register.php">Register</a>
            <?php endif; ?>
            <?php if ($currentPage !== 'login'): ?>
                <a href="login.php">Log-in</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
</header>
<?php if (!empty($showStaffMenu)) { require __DIR__ . '/staff_menu.php'; } ?>
<main class="<?= e($mainClass ?? '') ?>">