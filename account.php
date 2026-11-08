<?php
require 'php/bootstrap.php';
require 'php/auth.php';
requireLogin();

$pageTitle = 'My Account';
$currentPage = 'account';
require 'php/header.php';
?>
<div class="card">
    <h1>Welcome, <?= e($_SESSION['username']) ?></h1>
    <p>You are logged in. The equipment borrowing pages will be added here later.</p>
</div>
<?php require 'php/footer.php'; ?>