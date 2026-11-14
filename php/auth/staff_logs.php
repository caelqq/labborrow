<?php
const LOG_LIMIT = 40;
// Display only: every full hour a borrowed item is missing adds this many pesos
const PENALTY_PER_HOUR = 5;

const LOG_SELECT = "SELECT b.id, b.quantity, b.purpose, b.status, b.duration_minutes, b.reject_reason,
        b.requested_at, b.reviewed_at, b.picked_up_at, b.due_at, b.returned_at,
        i.name AS item, u.first_name, u.family_name, u.username, u.id_number
    FROM borrow_requests b
    JOIN items i ON i.id = b.item_id
    JOIN users u ON u.id_number = b.user_id";

function logId($id) {
    return 'LOG-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

function logTime($value) {
    return $value ? strtotime($value) : null;
}

function logRow($r) {
    return [
        'id' => logId($r['id']),
        'num' => (int)$r['id'],
        'name' => $r['first_name'] . ' ' . $r['family_name'],
        'username' => $r['username'],
        'id_number' => $r['id_number'],
        'item' => $r['item'],
        'quantity' => (int)$r['quantity'],
        'purpose' => $r['purpose'],
        'status' => $r['status'],
        'minutes' => $r['duration_minutes'] === null ? null : (int)$r['duration_minutes'],
        'reason' => $r['reject_reason'],
        'requested' => logTime($r['requested_at']),
        'reviewed' => logTime($r['reviewed_at']),
        'picked_up' => logTime($r['picked_up_at']),
        'due' => logTime($r['due_at']),
        'returned' => logTime($r['returned_at']),
    ];
}

// Starts-with search: the Log ID, ID number and username must start with the text;
// the item name and the user's full name may also match at the start of any word
function logSearch($q) {
    if ($q === '') {
        return ['', []];
    }
    $start = addcslashes($q, '%_\\') . '%';
    $word = '% ' . addcslashes($q, '%_\\') . '%';
    $fullName = "CONCAT(u.first_name, ' ', u.family_name)";

    $sql = " AND (CONCAT('LOG-', LPAD(b.id, 4, '0')) LIKE ?
        OR u.id_number LIKE ?
        OR u.username LIKE ?
        OR i.name LIKE ? OR i.name LIKE ?
        OR $fullName LIKE ? OR $fullName LIKE ?)";
    return [$sql, [$start, $start, $start, $start, $word, $start, $word]];
}

// Borrowed = every item still out (overdue ones too, so it matches the Borrowed now card)
// Missing = still borrowed past the due time, or returned after the due time
// $since limits the list to recent events (the Daily tab); search passes null to look at every date
// One row more than $limit is fetched, only to know if a "Show more" button is needed
function logTab($db, $tab, $q, $now, $since, $limit) {
    $tabs = [
        'borrowed' => ["b.status = 'borrowed'", [], 'b.picked_up_at', 'picked_up'],
        'picked' => ['b.picked_up_at IS NOT NULL', [], 'b.picked_up_at', 'picked_up'],
        'returned' => ["b.status = 'returned'", [], 'b.returned_at', 'returned'],
        'rejected' => ["b.status = 'rejected'", [], 'b.reviewed_at', 'reviewed'],
        'missing' => ["((b.status = 'borrowed' AND b.due_at < ?) OR (b.status = 'returned' AND b.returned_at > b.due_at))", [$now], 'b.due_at', 'due'],
    ];
    [$where, $params, $column, $key] = $tabs[$tab];
    if ($since !== null) {
        $where .= " AND $column >= ?";
        $params[] = $since;
    }
    [$searchSql, $searchParams] = logSearch($q);

    // b.id breaks ties, so rows with the same time always come back in the same order
    $stmt = $db->prepare(LOG_SELECT . " WHERE $where$searchSql ORDER BY $column DESC, b.id DESC LIMIT " . ($limit + 1));
    $stmt->execute(array_merge($params, $searchParams));

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $row = logRow($r);
        $row['type'] = $tab === 'picked' ? 'borrowed' : $tab;
        $row['time'] = $row[$key];
        $rows[] = $row;
    }
    return $rows;
}

// Every kind of event mixed together, newest first
function logDaily($db, $q, $now, $since, $limit) {
    $all = [];
    foreach (['picked', 'returned', 'rejected', 'missing'] as $tab) {
        $all = array_merge($all, logTab($db, $tab, $q, $now, $since, $limit));
    }
    // Same time: higher log number first, then the type name, so the order never changes between refreshes
    usort($all, fn($a, $b) => [$b['time'], $b['num'], $a['type']] <=> [$a['time'], $a['num'], $b['type']]);
    return $all;
}

// Returns [rows to show, true if there are older ones]
function logPage($rows, $limit) {
    return [array_slice($rows, 0, $limit), count($rows) > $limit];
}