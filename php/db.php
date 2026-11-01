<?php
function getDb() {
    // Config lives outside the web root so a browser can never open it
    $config = require 'D:/xamp/private/config.php';

    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";

    return new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // false = real prepared statements, our SQL injection defence (protection 1)
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}