<?php
require 'php/bootstrap.php';

$_SESSION = [];
// Also delete the cookie in the browser
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
session_destroy();

header('Location: login.php');
exit;