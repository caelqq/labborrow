<?php
const EVENT_TYPES = [
    'REGISTERED', 'ACCOUNT_VERIFIED', 'LOGIN_SUCCESS', 'LOGIN_FAILED', 'OTP_SENT', 'OTP_FAILED',
    'LOCKOUT_TIER_1', 'LOCKOUT_TIER_2', 'LOCKOUT_TIER_3', 'IP_BLOCKED', 'ALERT_EMAIL_SENT',
    'RESET_REQUESTED', 'PASSWORD_CHANGED', 'CAPTCHA_FAILED', 'CSRF_REJECTED',
    'ADMIN_VIEWED_DASHBOARD', 'ADMIN_ACCESS_DENIED',
];

// Every query goes through here, so all of them are prepared statements
function rows($db, $sql, $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function countToday($db, $type) {
    return (int)rows($db, 'SELECT COUNT(*) FROM activity_log WHERE event_type = ? AND created_at >= ?',
        [$type, date('Y-m-d 00:00:00')])->fetchColumn();
}

function summaryCards($db) {
    $now = date('Y-m-d H:i:s');
    return [
        'logins_ok' => countToday($db, 'LOGIN_SUCCESS'),
        'logins_failed' => countToday($db, 'LOGIN_FAILED'),
        'locked_now' => (int)rows($db, 'SELECT COUNT(*) FROM login_attempts WHERE locked_until > ?', [$now])->fetchColumn(),
        'ips_blocked' => (int)rows($db, 'SELECT COUNT(DISTINCT ip_address) FROM ip_blocks WHERE blocked_until > ?', [$now])->fetchColumn(),
        'otp_sent' => countToday($db, 'OTP_SENT'),
        'otp_failed' => countToday($db, 'OTP_FAILED'),
        'alerts_sent' => countToday($db, 'ALERT_EMAIL_SENT'),
    ];
}

// 24 bars, oldest first; hours with no failures still show as 0
function failedPerHour($db) {
    $start = strtotime(date('Y-m-d H:00:00')) - 23 * 3600;
    $counts = rows($db,
        "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H') AS h, COUNT(*) FROM activity_log
         WHERE event_type = 'LOGIN_FAILED' AND created_at >= ? GROUP BY h",
        [date('Y-m-d H:i:s', $start)])->fetchAll(PDO::FETCH_KEY_PAIR);

    $hours = [];
    for ($i = 0; $i < 24; $i++) {
        $t = $start + $i * 3600;
        $hours[] = ['label' => date('ga', $t), 'count' => (int)($counts[date('Y-m-d H', $t)] ?? 0)];
    }
    return $hours;
}

function topIps($db) {
    $top = rows($db,
        "SELECT ip_address, COUNT(*) AS failures FROM activity_log
         WHERE event_type = 'LOGIN_FAILED' AND created_at >= ?
         GROUP BY ip_address ORDER BY failures DESC LIMIT 5",
        [date('Y-m-d H:i:s', time() - 24 * 3600)])->fetchAll(PDO::FETCH_ASSOC);

    $blocked = rows($db, 'SELECT DISTINCT ip_address FROM ip_blocks WHERE blocked_until > ?',
        [date('Y-m-d H:i:s')])->fetchAll(PDO::FETCH_COLUMN);

    return array_map(fn($row) => [
        'ip' => $row['ip_address'],
        'failures' => (int)$row['failures'],
        'blocked' => in_array($row['ip_address'], $blocked, true),
    ], $top);
}

function latestFlags($db) {
    return rows($db, 'SELECT pattern, target, severity, details, flag_date FROM security_flags ORDER BY id DESC LIMIT 20')
        ->fetchAll(PDO::FETCH_ASSOC);
}

function validDate($value) {
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

// The filters come from the browser, so each one is checked and passed as a parameter
function recentEvents($db, $f) {
    $where = [];
    $params = [];

    if (in_array($f['type'], EVENT_TYPES, true)) {
        $where[] = 'event_type = ?';
        $params[] = $f['type'];
    }
    if (validDate($f['from'])) {
        $where[] = 'created_at >= ?';
        $params[] = $f['from'] . ' 00:00:00';
    }
    if (validDate($f['to'])) {
        $where[] = 'created_at <= ?';
        $params[] = $f['to'] . ' 23:59:59';
    }
    if ($f['username'] !== '') {
        $where[] = 'username_typed LIKE ?';
        $params[] = '%' . addcslashes($f['username'], '%_\\') . '%';
    }
    if ($f['ip'] !== '') {
        $where[] = 'ip_address LIKE ?';
        $params[] = '%' . addcslashes($f['ip'], '%_\\') . '%';
    }

    $sql = 'SELECT id, event_type, username_typed, user_id, ip_address, user_agent, details, created_at FROM activity_log'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY id DESC LIMIT 50';
    return rows($db, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);
}