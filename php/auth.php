<?php
// Seeded idle timeout: 8 minutes, warning at 7
define('IDLE_SECONDS', 480);
define('IDLE_WARN_SECONDS', 420);

// Put requireLogin() at the top of every protected page
function requireLogin() {
    // no-store stops the browser keeping the page, so the back button cannot show it after log-out
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // The server decides, so a page left open past the limit cannot be used
    if (time() - ($_SESSION['last_active'] ?? 0) > IDLE_SECONDS) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['last_active'] = time();
}