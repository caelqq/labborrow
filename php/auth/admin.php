<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/log.php';

// Put at the top of the dashboard page and its data endpoint. $json = true gives an empty reply instead of a page
function requireAdmin($json = false) {
    if ($json) {
        // Does not refresh the idle timer, so the 60-second auto-refresh cannot keep a session alive forever
        if (empty($_SESSION['user_id']) || time() - ($_SESSION['last_active'] ?? 0) > IDLE_SECONDS) {
            http_response_code(403);
            exit;
        }
    } else {
        requireLogin();
    }

    if (($_SESSION['role'] ?? '') === 'admin') {
        return;
    }

    logEvent('ADMIN_ACCESS_DENIED', $_SESSION['username'], $_SESSION['user_id'], $json ? 'data endpoint' : 'dashboard');
    http_response_code(403);
    if ($json) {
        exit;
    }

    $pageTitle = 'Access denied';
    $currentPage = 'denied';
    require __DIR__ . '/../core/header.php';
    echo '<div class="card"><h1>Access denied</h1><p>Only administrators can open this page.</p></div>';
    require __DIR__ . '/../core/footer.php';
    exit;
}