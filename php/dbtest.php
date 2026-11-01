<?php
require 'db.php';

try {
    getDb();
    echo "Database connected";
} catch (PDOException $e) {
    echo "Database connection failed";
}