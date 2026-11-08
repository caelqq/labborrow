<?php
require_once __DIR__ . '/../mail/alerts.php';

define('FORGOT_AFTER', 2);
const LOCK_SECONDS = [1 => 23, 2 => 46, 3 => 92];

// Counted by the typed name, even if no such account exists, so locks never reveal real usernames
function attemptKey($username) {
    return strtolower(substr($username, 0, 100));
}

function failedCount($db, $username) {
    $stmt = $db->prepare('SELECT failed_count FROM login_attempts WHERE username = ?');
    $stmt->execute([attemptKey($username)]);
    return (int)$stmt->fetchColumn();
}

function lockSecondsLeft($db, $username) {
    $stmt = $db->prepare('SELECT locked_until FROM login_attempts WHERE username = ?');
    $stmt->execute([attemptKey($username)]);
    $until = $stmt->fetchColumn();
    return $until ? max(0, strtotime($until) - time()) : 0;
}

// Every 3rd failure locks: 3 = tier 1, 6 = tier 2, 9 and more = tier 3
function recordFailure($db, $username, $userId) {
    $key = attemptKey($username);
    $db->prepare(
        'INSERT INTO login_attempts (username, failed_count) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE failed_count = failed_count + 1'
    )->execute([$key]);

    $count = failedCount($db, $username);
    if ($count % 3 === 0) {
        $tier = min(3, intdiv($count, 3));
        $until = date('Y-m-d H:i:s', time() + LOCK_SECONDS[$tier]);
        $db->prepare('UPDATE login_attempts SET locked_until = ? WHERE username = ?')->execute([$until, $key]);
        logEvent('LOCKOUT_TIER_' . $tier, substr($username, 0, 100), $userId, "$count failures");

        // Only real accounts get an email, and only once per tier (3, 6 and 9 failures)
        if ($userId !== null && $count <= 9) {
            sendLockoutAlert($db, $userId, $count, $tier);
        }
    }
    return $count;
}

function resetFailures($db, $username) {
    $db->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([attemptKey($username)]);
}