<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'D:/xamp/private/error.log');
date_default_timezone_set('Asia/Manila');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

require_once __DIR__ . '/db.php';

// Use e() on everything shown on a page, it blocks XSS (protection 2)
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// One secret token per session; every POST form must send it back (protection 3)
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid() {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}