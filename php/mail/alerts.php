<?php
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/../core/log.php';

// The link in the email points here; change it if your site address changes
define('APP_URL', 'http://localhost/labborrow');

function alertInfo() {
    return 'Date and time: ' . date('F j, Y h:i A') . " (Philippine time)\n"
        . 'IP address: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n"
        . 'Browser or device: ' . substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 200) . "\n\n";
}

function findAlertUser($db, $userId) {
    $stmt = $db->prepare('SELECT username, email FROM users WHERE id_number = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function sendLockoutAlert($db, $userId, $count, $tier) {
    $user = findAlertUser($db, $userId);
    if (!$user) {
        return;
    }

    $body = "Campus Lab Equipment Borrowing System\n\n"
        . "Someone entered a wrong password for your account.\n\n"
        . "Failed attempts: $count\n"
        . alertInfo()
        . "If this was not you, reset your password now:\n"
        . APP_URL . '/forgot.php';

    if (sendMail($user['email'], 'Failed sign-in attempts on your account', $body)) {
        logEvent('ALERT_EMAIL_SENT', $user['username'], $userId, "lockout tier $tier");
    }
}

function sendPasswordChangedAlert($db, $userId) {
    $user = findAlertUser($db, $userId);
    if (!$user) {
        return;
    }

    $body = "Campus Lab Equipment Borrowing System\n\n"
        . "The password of your account was just changed.\n\n"
        . alertInfo()
        . "If this was not you, reset your password now:\n"
        . APP_URL . '/forgot.php';

    if (sendMail($user['email'], 'Your password was changed', $body)) {
        logEvent('ALERT_EMAIL_SENT', $user['username'], $userId, 'password changed');
    }
}