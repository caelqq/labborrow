<?php
require_once __DIR__ . '/../core/log.php';
require_once __DIR__ . '/staff_logs.php';
require_once __DIR__ . '/staff_penalty.php';

// Seeded by the staff rules: pending is dropped after 8 hours, approved after 2 hours without a pick-up
const PENDING_EXPIRE_SECONDS = 8 * 3600;
const APPROVED_EXPIRE_SECONDS = 2 * 3600;
// Student rules (used by the Student UI): at most 2 different items, at most 4 of one item
const MAX_ITEMS_PER_STUDENT = 2;
const MAX_PER_ITEM = 4;

// Runs whenever staff pages ask for data, so it needs no scheduled task
function expireRequests($db) {
    $rules = [
        ['pending', 'requested_at', PENDING_EXPIRE_SECONDS, 'Request not responded'],
        ['approved', 'reviewed_at', APPROVED_EXPIRE_SECONDS, 'User did not pick-up the item'],
    ];
    foreach ($rules as [$status, $column, $seconds, $reason]) {
        $stmt = $db->prepare("SELECT id FROM borrow_requests WHERE status = ? AND $column <= ?");
        $stmt->execute([$status, date('Y-m-d H:i:s', time() - $seconds)]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            // The status check in the UPDATE stops a request staff just answered from being rejected again
            $update = $db->prepare("UPDATE borrow_requests SET status = 'rejected', reject_reason = ?, reviewed_at = ? WHERE id = ? AND status = ?");
            $update->execute([$reason, date('Y-m-d H:i:s'), $id, $status]);
            if ($update->rowCount() > 0) {
                logEvent('REQUEST_AUTO_REJECTED', null, null, logId($id) . ' ' . $reason);
            }
        }
    }
}

// $limits = how many rows each list asks for, $q = the search text (same rules as the Log search)
// Returns [rows per list, "has more" per list]
function requestLists($db, $limits, $q) {
    $lists = [
        'pending' => ['pending', 'b.requested_at, b.id'],
        'approved' => ['approved', 'b.reviewed_at DESC, b.id DESC'],
        'using' => ['borrowed', 'b.due_at, b.id'],
    ];
    [$searchSql, $searchParams] = logSearch($q);

    $now = time();
    $penalties = penaltyInfo($db, $now);

    $out = [];
    $more = [];
    foreach ($lists as $key => [$status, $order]) {
        $limit = $limits[$key];
        // One row more than the limit is fetched, only to know if a "Show more" button is needed
        $stmt = $db->prepare(LOG_SELECT . " WHERE b.status = ?$searchSql ORDER BY $order LIMIT " . ($limit + 1));
        $stmt->execute(array_merge([$status], $searchParams));
        $rows = array_map(function ($r) use ($penalties, $now) {
            $row = logRow($r);
            $row['standing'] = standingOf($penalties[$row['id_number']] ?? null, $now);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        [$out[$key], $more[$key]] = logPage($rows, $limit);
    }
    return [$out, $more];
}