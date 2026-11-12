<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/log.php';

// Items with this many available or fewer show an amber pill
const LOW_STOCK_AT = 2;

// Put at the top of every staff page. $json = true gives an empty reply instead of a page
function requireStaff($json = false) {
    if ($json) {
        // Does not refresh the idle timer, so the automatic refresh cannot keep a session alive
        if (empty($_SESSION['user_id']) || time() - ($_SESSION['last_active'] ?? 0) > IDLE_SECONDS) {
            http_response_code(403);
            exit;
        }
    } else {
        requireLogin();
    }

    if (($_SESSION['role'] ?? '') === 'staff') {
        return;
    }

    logEvent('STAFF_ACCESS_DENIED', $_SESSION['username'], $_SESSION['user_id']);
    http_response_code(403);
    if ($json) {
        exit;
    }

    $pageTitle = 'Access denied';
    $currentPage = 'denied';
    require __DIR__ . '/../core/header.php';
    echo '<div class="card"><h1>Access denied</h1><p>Only laboratory staff can open this page.</p></div>';
    require __DIR__ . '/../core/footer.php';
    exit;
}

function staffCount($db, $sql, $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

// Missing = borrowed and past the due time (nothing is stored, it is worked out each time)
function staffSummary($db) {
    return [
        'items' => staffCount($db, 'SELECT COUNT(*) FROM items WHERE is_deleted = 0 AND is_active = 1'),
        // No stock = every copy is approved or borrowed, so none is available
        'no_stock' => staffCount($db,
            "SELECT COUNT(*) FROM items i WHERE i.is_deleted = 0 AND i.is_active = 1 AND i.quantity - (
                SELECT COALESCE(SUM(b.quantity), 0) FROM borrow_requests b
                WHERE b.item_id = i.id AND b.status IN ('approved', 'borrowed')
             ) <= 0"),
        'pending' => staffCount($db, "SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'"),
        'borrowed' => staffCount($db, "SELECT COUNT(*) FROM borrow_requests WHERE status = 'borrowed'"),
        'missing' => staffCount($db, "SELECT COUNT(*) FROM borrow_requests WHERE status = 'borrowed' AND due_at < ?", [date('Y-m-d H:i:s')]),
    ];
}

// available = quantity minus what is approved or borrowed; open_requests also counts pending ones
function staffItems($db) {
    $rows = $db->query(
        "SELECT i.id, i.name, i.quantity, i.photo, i.is_active,
            COALESCE((SELECT SUM(b.quantity) FROM borrow_requests b
                WHERE b.item_id = i.id AND b.status IN ('approved', 'borrowed')), 0) AS taken,
            (SELECT COUNT(*) FROM borrow_requests b
                WHERE b.item_id = i.id AND b.status IN ('pending', 'approved', 'borrowed')) AS open_requests
         FROM items i WHERE i.is_deleted = 0 ORDER BY i.name"
    )->fetchAll(PDO::FETCH_ASSOC);

    return array_map(fn($r) => [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'quantity' => (int)$r['quantity'],
        'photo' => $r['photo'],
        'available' => (int)$r['quantity'] - (int)$r['taken'],
        'open_requests' => (int)$r['open_requests'],
        'hidden' => !$r['is_active'],
    ], $rows);
}