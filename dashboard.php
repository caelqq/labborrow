<?php
require 'php/core/bootstrap.php';
require 'php/auth/admin.php';
requireAdmin();

// Opening the dashboard is logged too: watching the logs is itself recorded
logEvent('ADMIN_VIEWED_DASHBOARD', $_SESSION['username'], $_SESSION['user_id']);

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$mainClass = 'wide';
require 'php/core/header.php';
?>
<div class="card">
    <h1>Security Dashboard</h1>
    <p>Admin only. The summary, chart and logs will be added here.</p>
</div>
<?php require 'php/core/footer.php'; ?>