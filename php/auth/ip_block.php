<?php
// Seeded: 8 + D2 = 9 failures in 10 minutes blocks the IP for 10 minutes
define('IP_FAIL_LIMIT', 9);
define('IP_WINDOW_SECONDS', 600);
define('IP_BLOCK_SECONDS', 600);

function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function ipBlockedSeconds($db) {
    $stmt = $db->prepare('SELECT MAX(blocked_until) FROM ip_blocks WHERE ip_address = ?');
    $stmt->execute([clientIp()]);
    $until = $stmt->fetchColumn();
    return $until ? max(0, strtotime($until) - time()) : 0;
}

// Call this after every failed log-in; it counts failures from this IP across all usernames
function blockIpIfNeeded($db) {
    if (ipBlockedSeconds($db) > 0) {
        return;
    }

    // Failures from before the last block do not count again
    $stmt = $db->prepare('SELECT MAX(created_at) FROM ip_blocks WHERE ip_address = ?');
    $stmt->execute([clientIp()]);
    $lastBlock = $stmt->fetchColumn();

    $since = date('Y-m-d H:i:s', time() - IP_WINDOW_SECONDS);
    if ($lastBlock && $lastBlock > $since) {
        $since = $lastBlock;
    }

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM activity_log WHERE event_type = 'LOGIN_FAILED' AND ip_address = ? AND created_at >= ?"
    );
    $stmt->execute([clientIp(), $since]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= IP_FAIL_LIMIT) {
        $db->prepare('INSERT INTO ip_blocks (ip_address, blocked_until, created_at) VALUES (?, ?, ?)')
            ->execute([
                clientIp(),
                date('Y-m-d H:i:s', time() + IP_BLOCK_SECONDS),
                date('Y-m-d H:i:s'),
            ]);
        logEvent('IP_BLOCKED', null, null, "$count failures in 10 minutes");
    }
}