<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/staff.php';
require __DIR__ . '/../auth/staff_penalty.php';
require __DIR__ . '/../mail/mailer.php';

requireStaff(true);
// This is a real action, so it counts as activity and refreshes the idle timer
$_SESSION['last_active'] = time();

header('Content-Type: application/json');
header('Cache-Control: no-store');

// The same reminder cannot be sent again for 10 minutes, so nobody's inbox gets flooded
const REMIND_COOLDOWN = 600;

function reply($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// The log is only read here; logEvent() is the only thing that writes to it
function recentlySent($db, $type, $details) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM activity_log WHERE event_type = ? AND details = ? AND created_at >= ?');
    $stmt->execute([$type, $details, date('Y-m-d H:i:s', time() - REMIND_COOLDOWN)]);
    return $stmt->fetchColumn() > 0;
}

function plural($n, $word) {
    return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

// 3 days and 5 hours
function unpaidWords($secs) {
    $d = intdiv($secs, 86400);
    $h = intdiv($secs % 86400, 3600);
    $parts = [];
    if ($d > 0) {
        $parts[] = plural($d, 'day');
    }
    if ($h > 0) {
        $parts[] = plural($h, 'hour');
    }
    return $parts ? implode(' and ', $parts) : 'less than 1 hour';
}

// 2 hours and 15 minutes
function missingWords($secs) {
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $parts = [];
    if ($h > 0) {
        $parts[] = plural($h, 'hour');
    }
    $parts[] = plural($m, 'minute');
    return implode(' and ', $parts);
}

function remindBalance($db) {
    $id = $_POST['id'] ?? '';
    if (!preg_match('/^\d{4}-\d{4}$/', $id)) {
        reply(['error' => 'Invalid request.'], 400);
    }

    $stmt = $db->prepare("SELECT username, email, first_name FROM users WHERE id_number = ? AND role = 'user'");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        reply(['error' => 'User not found.'], 404);
    }

    $now = time();
    $info = penaltyInfo($db, $now)[$id] ?? null;
    if (!$info || $info['balance'] <= 0) {
        reply(['error' => 'This user has no balance.']);
    }
    if (recentlySent($db, 'BALANCE_REMINDER_SENT', $id)) {
        reply(['error' => 'A reminder was already sent in the last 10 minutes.']);
    }

    $body = "Campus Lab Equipment Borrowing System\n\n"
        . "Hello {$user['first_name']},\n\n"
        . "This is a reminder for your account (username: {$user['username']}). "
        . 'Your current penalty balance is ₱' . $info['balance'] . ', and it has been unpaid for '
        . unpaidWords($now - $info['since']) . ".\n\n"
        . 'Please pay it at the laboratory as soon as possible.';

    if (!sendMail($user['email'], 'Reminder: unpaid penalty balance', $body)) {
        reply(['error' => 'The email could not be sent. Please try again later.']);
    }
    logEvent('BALANCE_REMINDER_SENT', $_SESSION['username'], $_SESSION['user_id'], $id);
    reply(['ok' => true, 'message' => 'Reminder sent to ' . $user['username'] . '.']);
}

function remindMissing($db) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        reply(['error' => 'Invalid request.'], 400);
    }

    $stmt = $db->prepare(
        "SELECT b.quantity, b.due_at, i.name AS item, u.first_name, u.username, u.email
         FROM borrow_requests b
         JOIN items i ON i.id = b.item_id
         JOIN users u ON u.id_number = b.user_id
         WHERE b.id = ? AND b.status = 'borrowed'"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        reply(['error' => 'This item is no longer borrowed. The list will refresh.']);
    }

    $now = time();
    $secs = $now - strtotime($row['due_at']);
    if ($secs <= 0) {
        reply(['error' => 'This item is not missing yet.']);
    }
    if (recentlySent($db, 'MISSING_REMINDER_SENT', logId($id))) {
        reply(['error' => 'A reminder was already sent in the last 10 minutes.']);
    }

    $penalty = intdiv($secs, 3600) * PENALTY_PER_HOUR;
    $body = "Campus Lab Equipment Borrowing System\n\n"
        . "Hello {$row['first_name']},\n\n"
        . "This is a reminder for your account (username: {$row['username']}). "
        . "The item you borrowed ({$row['quantity']} x {$row['item']}, " . logId($id) . ') has been missing for '
        . missingWords($secs) . ".\n\n"
        . 'Please return it to the laboratory as soon as possible. '
        . 'The penalty so far is ₱' . $penalty . ' (₱' . PENALTY_PER_HOUR . ' for every full hour missing).';

    if (!sendMail($row['email'], 'Reminder: please return the borrowed item', $body)) {
        reply(['error' => 'The email could not be sent. Please try again later.']);
    }
    logEvent('MISSING_REMINDER_SENT', $_SESSION['username'], $_SESSION['user_id'], logId($id));
    reply(['ok' => true, 'message' => 'Reminder sent to ' . $row['username'] . '.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(['error' => 'Invalid request.'], 405);
}
if (!csrf_valid()) {
    logEvent('CSRF_REJECTED', $_SESSION['username'], $_SESSION['user_id'], 'staff reminder');
    reply(['error' => 'Your session expired. Please reload the page.'], 400);
}

try {
    $db = getDb();
    $type = $_POST['type'] ?? '';
    if ($type === 'balance') {
        remindBalance($db);
    } elseif ($type === 'missing') {
        remindMissing($db);
    } else {
        reply(['error' => 'Invalid request.'], 400);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    reply(['error' => 'Something went wrong. Please try again.'], 500);
}