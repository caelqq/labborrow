<?php
// Command line only: php scripts/monitor.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Page Not Found');
}

require __DIR__ . '/../php/core/db.php';
require __DIR__ . '/../php/auth/ip_block.php';
date_default_timezone_set('Asia/Manila');

$db = getDb();
$since = date('Y-m-d H:i:s', time() - 24 * 3600);

function fetchAll($db, $sql, $params) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Names come from attackers, so odd characters are replaced before they reach the console
function plain($text) {
    return preg_replace('/[^\x20-\x7E]/', '?', (string)$text);
}

$scanned = (int)fetchAll($db, 'SELECT COUNT(*) AS c FROM activity_log WHERE created_at >= ?', [$since])[0]['c'];
$findings = [];

// 1. One IP with failures at or above the seeded limit, across any usernames
$rows = fetchAll($db,
    "SELECT ip_address, COUNT(*) AS failures, COUNT(DISTINCT username_typed) AS names FROM activity_log
     WHERE event_type = 'LOGIN_FAILED' AND created_at >= ?
     GROUP BY ip_address HAVING failures >= ?",
    [$since, IP_FAIL_LIMIT]);
foreach ($rows as $r) {
    $findings[] = ['HIGH', 'IP over threshold', $r['ip_address'], "{$r['failures']} failures, {$r['names']} usernames"];
}

// 2. One username with failures from 3 or more different IPs
$rows = fetchAll($db,
    "SELECT username_typed, COUNT(DISTINCT ip_address) AS ips FROM activity_log
     WHERE event_type = 'LOGIN_FAILED' AND username_typed IS NOT NULL AND created_at >= ?
     GROUP BY username_typed HAVING ips >= 3",
    [$since]);
foreach ($rows as $r) {
    $findings[] = ['MED', 'Account targeted', $r['username_typed'], "failures from {$r['ips']} IPs"];
}

// 3. Five wrong OTPs for one account inside 10 minutes: any 5 in a row that fit in 600 seconds
$times = [];
$rows = fetchAll($db,
    "SELECT username_typed, created_at FROM activity_log
     WHERE event_type = 'OTP_FAILED' AND username_typed IS NOT NULL AND created_at >= ?
     ORDER BY created_at",
    [$since]);
foreach ($rows as $r) {
    $times[$r['username_typed']][] = strtotime($r['created_at']);
}
foreach ($times as $name => $list) {
    for ($i = 0; $i + 4 < count($list); $i++) {
        if ($list[$i + 4] - $list[$i] <= 600) {
            $findings[] = ['MED', 'OTP guessing', $name, '5 wrong OTPs in 10 min'];
            break;
        }
    }
}

// 4. A successful log-in between 12:00 AM and 5:00 AM (HOUR below 5)
$rows = fetchAll($db,
    "SELECT username_typed, MIN(created_at) AS first_time FROM activity_log
     WHERE event_type = 'LOGIN_SUCCESS' AND HOUR(created_at) < 5 AND created_at >= ?
     GROUP BY username_typed",
    [$since]);
foreach ($rows as $r) {
    $findings[] = ['MED', 'Off-hours log-in', $r['username_typed'], 'log-in at ' . date('h:i A', strtotime($r['first_time']))];
}

// The table has a unique key on pattern + target + day, so INSERT IGNORE skips repeats
$insert = $db->prepare('INSERT IGNORE INTO security_flags (pattern, target, severity, details, flag_date) VALUES (?, ?, ?, ?, ?)');
$new = 0;
$old = 0;
foreach ($findings as [$level, $pattern, $target, $details]) {
    $insert->execute([$pattern, $target, $level, $details, date('Y-m-d')]);
    if ($insert->rowCount() > 0) {
        $new++;
    } else {
        $old++;
    }
}

echo 'IT 107 Monitoring Report  -  ' . date('Y-m-d H:i') . " PHT\n";
echo 'Scanned: ' . number_format($scanned) . " events (last 24 hours)\n\n";
foreach ($findings as [$level, $pattern, $target, $details]) {
    printf("[%-4s] %-18s %-15s %s\n", $level, $pattern, plain($target), plain($details));
}
if (!$findings) {
    echo "No suspicious patterns found.\n";
}
echo "\nNew flags written: $new      Already flagged today: $old\n";