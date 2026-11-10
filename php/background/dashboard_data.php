<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/admin.php';
require __DIR__ . '/../auth/dashboard_data.php';

// Not an admin or not logged in: an empty reply, no data
requireAdmin(true);

header('Content-Type: application/json');
header('Cache-Control: no-store');

$filters = [
    'type' => $_GET['type'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'username' => substr($_GET['username'] ?? '', 0, 100),
    'ip' => substr($_GET['ip'] ?? '', 0, 45),
];

try {
    $db = getDb();
    // SUBSTITUTE keeps odd bytes typed by an attacker from breaking the JSON
    echo json_encode([
        'summary' => summaryCards($db),
        'hours' => failedPerHour($db),
        'top_ips' => topIps($db),
        'flags' => latestFlags($db),
        'events' => recentEvents($db, $filters),
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true]);
}