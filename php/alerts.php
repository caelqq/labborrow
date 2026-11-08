<?php
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/log.php';

// The link in the email points here; change it if your site address changes
define('APP_URL', 'http://localhost/labborrow');

function sendLockoutAlert($db, $userId, $count, $tier) {
    $stmt = $db->prepare('SELECT username, email FROM users WHERE id_number = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return;
    }

    $subject = 'Failed sign-in attempts on your account';
    $body = "Campus Lab Equipment Borrowing System\n\n"
        . "Someone entered a wrong password for your account.\n\n"
        . "Failed attempts: $count\n"
        . 'Date and time: ' . date('F j, Y h:i A') . " (Philippine time)\n"
        . 'IP address: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n"
        . 'Browser or device: ' . substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 200) . "\n\n"
        . "If this was not you, reset your password now:\n"
        . APP_URL . "/forgot.php";

    if (sendMail($user['email'], $subject, $body)) {
        logEvent('ALERT_EMAIL_SENT', $user['username'], $userId, "lockout tier $tier");
    }
}