<?php
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/log.php';

define('OTP_MINUTES', 4);
define('OTP_MAX_TRIES', 3);
define('OTP_COOLDOWN', 60);
// The first code plus 3 resends in 15 minutes
define('OTP_MAX_PER_15MIN', 4);

function otpTitle($purpose) {
    $titles = [
        'register' => 'Verify your account',
        'login' => 'Your log-in code',
        'reset' => 'Password reset code',
    ];
    return $titles[$purpose];
}

// Seconds left before a new code may be requested; the server enforces this, not the page
function otpWaitSeconds($db, $userId, $purpose) {
    $stmt = $db->prepare('SELECT created_at FROM otps WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $purpose]);
    $last = $stmt->fetchColumn();
    if (!$last) {
        return 0;
    }
    return max(0, OTP_COOLDOWN - (time() - strtotime($last)));
}

function otpLimitReached($db, $userId, $purpose) {
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $stmt = $db->prepare('SELECT COUNT(*) FROM otps WHERE user_id = ? AND purpose = ? AND created_at >= ?');
    $stmt->execute([$userId, $purpose, $since]);
    return $stmt->fetchColumn() >= OTP_MAX_PER_15MIN;
}

// $user needs id_number, username and email. Returns '' on success, or an error message
function sendOtp($db, $user, $purpose) {
    if (otpWaitSeconds($db, $user['id_number'], $purpose) > 0) {
        return 'Please wait before requesting a new code.';
    }
    if (otpLimitReached($db, $user['id_number'], $purpose)) {
        return 'Too many codes requested. Please try again in a few minutes.';
    }

    // random_int, never rand(): rand() is predictable
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = time() + OTP_MINUTES * 60;

    // A new code cancels the old one
    $stmt = $db->prepare('UPDATE otps SET is_used = 1 WHERE user_id = ? AND purpose = ? AND is_used = 0');
    $stmt->execute([$user['id_number'], $purpose]);

    $stmt = $db->prepare(
        'INSERT INTO otps (user_id, purpose, otp_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id_number'],
        $purpose,
        password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]),
        date('Y-m-d H:i:s', $expires),
        date('Y-m-d H:i:s'),
    ]);

    $body = "Campus Lab Equipment Borrowing System\n"
        . otpTitle($purpose) . "\n\n"
        . "Your code is: $code\n\n"
        . 'This code expires at ' . date('h:i A', $expires) . ' (Philippine time), in ' . OTP_MINUTES . " minutes.\n\n"
        . 'If you did not request this code, ignore this email and consider changing your password.';

    if (!sendMail($user['email'], otpTitle($purpose), $body)) {
        return 'We could not send the email. Please try again.';
    }
    logEvent('OTP_SENT', $user['username'], $user['id_number'], $purpose);
    return '';
}

// Returns '' if the code is right, or an error message
function verifyOtp($db, $user, $purpose, $code) {
    $stmt = $db->prepare(
        'SELECT id, otp_hash, expires_at, attempts FROM otps
         WHERE user_id = ? AND purpose = ? AND is_used = 0 ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$user['id_number'], $purpose]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return 'This code is no longer valid. Please request a new one.';
    }

    if (strtotime($row['expires_at']) < time()) {
        $db->prepare('UPDATE otps SET is_used = 1 WHERE id = ?')->execute([$row['id']]);
        logEvent('OTP_FAILED', $user['username'], $user['id_number'], 'expired');
        return 'This code has expired. Please request a new one.';
    }

    if (!password_verify($code, $row['otp_hash'])) {
        $attempts = $row['attempts'] + 1;
        // The third wrong try cancels the code, even if the right one is typed later
        $cancel = $attempts >= OTP_MAX_TRIES ? 1 : 0;
        $db->prepare('UPDATE otps SET attempts = ?, is_used = ? WHERE id = ?')->execute([$attempts, $cancel, $row['id']]);
        logEvent('OTP_FAILED', $user['username'], $user['id_number'], 'wrong code');
        if ($cancel) {
            return 'Too many wrong tries. This code is cancelled. Please request a new one.';
        }
        return 'Wrong code. ' . (OTP_MAX_TRIES - $attempts) . ' tries left.';
    }

    // Single use
    $db->prepare('UPDATE otps SET is_used = 1 WHERE id = ?')->execute([$row['id']]);
    return '';
}