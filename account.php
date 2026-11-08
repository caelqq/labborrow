<?php
require 'php/core/bootstrap.php';
require 'php/auth/auth.php';
requireLogin();

$pageTitle = 'My Account';
$currentPage = 'account';
require 'php/core/header.php';
?>
<div class="card">
    <h1>Welcome, <?= e($_SESSION['username']) ?></h1>
    <p>You are logged in. The equipment borrowing pages will be added here later.</p>
</div>
<?php require 'php/core/footer.php'; ?>