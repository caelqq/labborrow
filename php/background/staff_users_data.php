<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/staff.php';
require __DIR__ . '/../auth/staff_users.php';

// Not staff or not logged in: an empty reply, no data
requireStaff(true);

header('Content-Type: application/json');
header('Cache-Control: no-store');

$q = substr(trim($_GET['q'] ?? ''), 0, 60);

// 40, 80, 120 ... ("Show more" adds 40)
$n = max(LOG_LIMIT, min(1000, (int)($_GET['limit'] ?? LOG_LIMIT)));
$limit = (int)(ceil($n / LOG_LIMIT) * LOG_LIMIT);

try {
    $now = time();
    [$users, $more, $cards] = userList(getDb(), $q, $limit, $now);

    echo json_encode(
        ['now' => $now, 'searching' => $q !== '', 'more' => $more, 'cards' => $cards, 'users' => $users],
        JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true]);
}