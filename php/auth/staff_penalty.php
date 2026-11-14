<?php
require_once __DIR__ . '/staff_logs.php';

// Good = balance of 30 or less and unpaid for under 14 days. Above that = Bad
const GOOD_MAX_BALANCE = 30;
const BAD_AFTER_DAYS = 14;

// Per student: balance = penalties earned from borrow records minus payments. Only payments are stored.
// since = when the unpaid time started counting: the first penalty, or the last payment (it restarts after any payment)
function penaltyInfo($db, $now) {
    $stmt = $db->prepare(
        "SELECT user_id, due_at,
            FLOOR(GREATEST(0, TIMESTAMPDIFF(SECOND, due_at, IF(status = 'borrowed', CAST(? AS DATETIME), returned_at))) / 3600) AS hours
         FROM borrow_requests WHERE status IN ('borrowed', 'returned') AND due_at IS NOT NULL"
    );
    $stmt->execute([date('Y-m-d H:i:s', $now)]);

    $earned = [];
    $accruals = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ((int)$r['hours'] >= 1) {
            $earned[$r['user_id']] = ($earned[$r['user_id']] ?? 0) + (int)$r['hours'] * PENALTY_PER_HOUR;
            // The first full hour late is when the first peso penalty appears
            $accruals[$r['user_id']][] = strtotime($r['due_at']) + 3600;
        }
    }

    $paid = [];
    $lastPay = [];
    $clearedAt = [];
    foreach ($db->query('SELECT user_id, amount, balance_after, paid_at FROM penalty_payments')->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $time = strtotime($p['paid_at']);
        $paid[$p['user_id']] = ($paid[$p['user_id']] ?? 0) + (int)$p['amount'];
        $lastPay[$p['user_id']] = max($lastPay[$p['user_id']] ?? 0, $time);
        if ((int)$p['balance_after'] === 0) {
            $clearedAt[$p['user_id']] = max($clearedAt[$p['user_id']] ?? 0, $time);
        }
    }

    $info = [];
    foreach ($earned as $userId => $total) {
        $balance = max(0, $total - ($paid[$userId] ?? 0));
        $since = null;
        if ($balance > 0) {
            // Penalties from before the last full payment do not count
            $first = $now;
            foreach ($accruals[$userId] as $t) {
                if ($t > ($clearedAt[$userId] ?? 0) && $t < $first) {
                    $first = $t;
                }
            }
            $since = max($first, $lastPay[$userId] ?? 0);
        }
        $info[$userId] = ['balance' => $balance, 'since' => $since];
    }
    return $info;
}

// Returns very_good, good or bad
function standingOf($info, $now) {
    if (!$info || $info['balance'] <= 0) {
        return 'very_good';
    }
    $days = intdiv($now - $info['since'], 86400);
    return ($info['balance'] > GOOD_MAX_BALANCE || $days >= BAD_AFTER_DAYS) ? 'bad' : 'good';
}