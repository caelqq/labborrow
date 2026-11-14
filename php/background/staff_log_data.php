<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/staff.php';
require __DIR__ . '/../auth/staff_requests.php';

// Not staff or not logged in: an empty reply, no data
requireStaff(true);

header('Content-Type: application/json');
header('Cache-Control: no-store');

$q = substr(trim($_GET['q'] ?? ''), 0, 60);

// How many rows a tab asks for: 40, 80, 120 ... ("Show more" adds 40)
function limitFor($tab) {
    $n = max(LOG_LIMIT, min(1000, (int)($_GET['limit_' . $tab] ?? LOG_LIMIT)));
    return (int)(ceil($n / LOG_LIMIT) * LOG_LIMIT);
}

try {
    $db = getDb();
    expireRequests($db);
    $now = time();
    $nowSql = date('Y-m-d H:i:s', $now);
    // Daily means today only, from midnight; a search looks through every date
    $since = $q === '' ? date('Y-m-d 00:00:00', $now) : null;

    $data = ['now' => $now, 'penalty' => PENALTY_PER_HOUR, 'searching' => $q !== '', 'more' => []];
    foreach (['daily', 'borrowed', 'returned', 'rejected', 'missing'] as $tab) {
        $limit = limitFor($tab);
        $rows = $tab === 'daily'
            ? logDaily($db, $q, $nowSql, $since, $limit)
            : logTab($db, $tab, $q, $nowSql, null, $limit);
        [$data[$tab], $data['more'][$tab]] = logPage($rows, $limit);
    }

    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true]);
}