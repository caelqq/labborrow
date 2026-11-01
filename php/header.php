<?php $loggedIn = isset($_SESSION['user_id']); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Campus Lab Equipment Borrowing System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="system-name">Campus Lab Equipment Borrowing System</div>
<header class="site-header">
    <nav>
        <a href="index.php">Home</a>
        <?php if ($loggedIn): ?>
            <a href="logout.php">Log-out</a>
        <?php else: ?>
            <?php if ($currentPage !== 'register'): ?>
                <a href="register.php">Register</a>
            <?php endif; ?>
            <?php if ($currentPage !== 'login'): ?>
                <a href="login.php">Log-in</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
</header>
<main>