<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../core/validate.php';

header('Content-Type: application/json');

$username = $_GET['username'] ?? '';

// Only look up names that already pass the format rules
if (validateUsername($username) !== '') {
    echo json_encode(['taken' => false]);
    exit;
}

try {
    $stmt = getDb()->prepare('SELECT 1 FROM users WHERE username = ?');
    $stmt->execute([$username]);
    echo json_encode(['taken' => (bool)$stmt->fetch()]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true]);
}