<?php
// The activity log is append-only: this is the only place that touches the table, and it only inserts
function logEvent($type, $usernameTyped = null, $userId = null, $details = null) {
    try {
        $stmt = getDb()->prepare(
            'INSERT INTO activity_log (event_type, username_typed, user_id, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $type,
            $usernameTyped,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $details,
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}