<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/staff.php';
require __DIR__ . '/../auth/staff_penalty.php';

requireStaff(true);
// This is a real action, so it counts as activity and refreshes the idle timer
$_SESSION['last_active'] = time();

header('Content-Type: application/json');
header('Cache-Control: no-store');

function reply($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Whole pesos, at least 5, in steps of 5
function validateAmount($amount) {
    if (!preg_match('/^\d{1,4}$/', $amount)) {
        return 'Amount must be a whole number.';
    }
    if ((int)$amount < 5) {
        return 'The smallest payment is ₱5.';
    }
    if ((int)$amount % 5 !== 0) {
        return 'Payments must be in 5s (5, 10, 15 ...).';
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(['error' => 'Invalid request.'], 405);
}
if (!csrf_valid()) {
    logEvent('CSRF_REJECTED', $_SESSION['username'], $_SESSION['user_id'], 'staff pay');
    reply(['error' => 'Your session expired. Please reload the page.'], 400);
}

$idNumber = $_POST['id'] ?? '';
$amountText = $_POST['amount'] ?? '';

if (!preg_match('/^\d{4}-\d{4}$/', $idNumber)) {
    reply(['error' => 'Invalid request.'], 400);
}
$error = validateAmount($amountText);
if ($error !== '') {
    reply(['error' => $error]);
}
$amount = (int)$amountText;

try {
    $db = getDb();

    // Locking the user's row makes two payments for the same user wait for each other
    $db->beginTransaction();
    $stmt = $db->prepare("SELECT id_number FROM users WHERE id_number = ? AND role = 'user' FOR UPDATE");
    $stmt->execute([$idNumber]);
    if (!$stmt->fetch()) {
        $db->rollBack();
        reply(['error' => 'User not found.'], 404);
    }

    // The balance is worked out again here; the number in the browser is never trusted
    $info = penaltyInfo($db, time())[$idNumber] ?? null;
    $balance = $info ? $info['balance'] : 0;
    if ($amount > $balance) {
        $db->rollBack();
        reply(['error' => $balance > 0 ? 'The most you can pay is ₱' . $balance . '.' : 'This user has no balance.']);
    }

    $after = $balance - $amount;
    $db->prepare('INSERT INTO penalty_payments (user_id, amount, balance_after, paid_by) VALUES (?, ?, ?, ?)')
        ->execute([$idNumber, $amount, $after, $_SESSION['user_id']]);
    $db->commit();

    logEvent('PENALTY_PAID', $_SESSION['username'], $_SESSION['user_id'], "$idNumber paid $amount, left $after");
    reply(['ok' => true, 'balance' => $after]);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log($e->getMessage());
    reply(['error' => 'Something went wrong. Please try again.'], 500);
}