<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/staff.php';
require __DIR__ . '/../auth/staff_requests.php';

// Not staff or not logged in: an empty reply, no data
requireStaff(true);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $db = getDb();
    expireRequests($db);
    echo json_encode([
        'summary' => staffSummary($db),
        'items' => staffItems($db),
        'low_at' => LOW_STOCK_AT,
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true]);
}