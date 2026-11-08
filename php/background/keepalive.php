<?php
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../auth/auth.php';

// requireLogin() refreshes the idle timer
requireLogin();
header('Content-Type: application/json');
echo json_encode(['ok' => true]);