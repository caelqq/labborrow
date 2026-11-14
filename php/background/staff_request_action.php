<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/staff.php';
require __DIR__ . '/../auth/staff_requests.php';

requireStaff(true);
// This is a real action, so it counts as activity and refreshes the idle timer
$_SESSION['last_active'] = time();

header('Content-Type: application/json');
header('Cache-Control: no-store');

const STALE = 'This request was already updated. The list will refresh.';

function reply($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function validateReason($reason) {
    if ($reason === '') {
        return 'Reason is required.';
    }
    if ($reason !== trim($reason)) {
        return 'Spaces at the start or end are not allowed.';
    }
    if (strpos($reason, '  ') !== false) {
        return 'Double space is not allowed.';
    }
    if (preg_match('/[^A-Za-z0-9 .,\'\-!?]/', $reason)) {
        return 'Reason may contain letters, digits, spaces and . , \' - ! ? only.';
    }
    if (strlen($reason) < 3 || strlen($reason) > 100) {
        return 'Reason must be 3 to 100 characters.';
    }
    return '';
}

// The longest a user can borrow is 24 hours, so 0 to 23 hours and 0 to 59 minutes. Returns [error, total minutes]
function validateDuration($hours, $minutes) {
    if (!preg_match('/^\d{1,2}$/', $hours) || !preg_match('/^\d{1,2}$/', $minutes)) {
        return ['Hours and minutes must be whole numbers.', 0];
    }
    if ((int)$hours > 23 || (int)$minutes > 59) {
        return ['Use 0 to 23 hours and 0 to 59 minutes.', 0];
    }
    $total = (int)$hours * 60 + (int)$minutes;
    return $total < 1 ? ['Enter at least 1 minute.', 0] : ['', $total];
}

function logStaff($type, $id) {
    logEvent($type, $_SESSION['username'], $_SESSION['user_id'], logId($id));
}

function approve($db, $id) {
    [$error, $minutes] = validateDuration($_POST['hours'] ?? '', $_POST['minutes'] ?? '');
    if ($error !== '') {
        reply(['error' => $error]);
    }

    // The rows are locked, so two staff approving at the same moment cannot take the same stock
    $db->beginTransaction();
    $stmt = $db->prepare(
        "SELECT b.item_id, b.quantity, i.quantity AS stock FROM borrow_requests b
         JOIN items i ON i.id = b.item_id WHERE b.id = ? AND b.status = 'pending' FOR UPDATE"
    );
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $db->rollBack();
        reply(['error' => STALE]);
    }

    $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM borrow_requests WHERE item_id = ? AND status IN ('approved', 'borrowed')");
    $stmt->execute([$request['item_id']]);
    if ((int)$request['quantity'] > (int)$request['stock'] - (int)$stmt->fetchColumn()) {
        $db->rollBack();
        reply(['error' => 'Not enough stock is available for this request.']);
    }

    $db->prepare("UPDATE borrow_requests SET status = 'approved', duration_minutes = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?")
        ->execute([$minutes, $_SESSION['user_id'], date('Y-m-d H:i:s'), $id]);
    $db->commit();
    logStaff('REQUEST_APPROVED', $id);
    reply(['ok' => true]);
}

function reject($db, $id) {
    $reason = $_POST['reason'] ?? '';
    $error = validateReason($reason);
    if ($error !== '') {
        reply(['error' => $error]);
    }

    $stmt = $db->prepare("UPDATE borrow_requests SET status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ? AND status = 'pending'");
    $stmt->execute([$reason, $_SESSION['user_id'], date('Y-m-d H:i:s'), $id]);
    if ($stmt->rowCount() === 0) {
        reply(['error' => STALE]);
    }
    logStaff('REQUEST_REJECTED', $id);
    reply(['ok' => true]);
}

// The borrow time starts now, at pick-up: due = now + the time staff gave
function pickup($db, $id) {
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("UPDATE borrow_requests SET status = 'borrowed', picked_up_at = ?, due_at = DATE_ADD(?, INTERVAL duration_minutes MINUTE) WHERE id = ? AND status = 'approved'");
    $stmt->execute([$now, $now, $id]);
    if ($stmt->rowCount() === 0) {
        reply(['error' => STALE]);
    }
    logStaff('ITEM_PICKED_UP', $id);
    reply(['ok' => true]);
}

function returned($db, $id) {
    $stmt = $db->prepare("UPDATE borrow_requests SET status = 'returned', returned_at = ? WHERE id = ? AND status = 'borrowed'");
    $stmt->execute([date('Y-m-d H:i:s'), $id]);
    if ($stmt->rowCount() === 0) {
        reply(['error' => STALE]);
    }
    logStaff('ITEM_RETURNED', $id);
    reply(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(['error' => 'Invalid request.'], 405);
}
if (!csrf_valid()) {
    logEvent('CSRF_REJECTED', $_SESSION['username'], $_SESSION['user_id'], 'staff requests');
    reply(['error' => 'Your session expired. Please reload the page.'], 400);
}

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$actions = ['approve' => 'approve', 'reject' => 'reject', 'pickup' => 'pickup', 'return' => 'returned'];
if ($id < 1 || !isset($actions[$action])) {
    reply(['error' => 'Invalid request.'], 400);
}

try {
    $db = getDb();
    expireRequests($db);
    $actions[$action]($db, $id);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log($e->getMessage());
    reply(['error' => 'Something went wrong. Please try again.'], 500);
}