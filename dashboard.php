<?php
require 'php/core/bootstrap.php';
require 'php/auth/admin.php';
require 'php/auth/dashboard_data.php';
requireAdmin();

// Opening the dashboard is logged too: watching the logs is itself recorded
logEvent('ADMIN_VIEWED_DASHBOARD', $_SESSION['username'], $_SESSION['user_id']);

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$mainClass = 'wide dashboard-page';
require 'php/core/header.php';
?>
<div class="dash-head">
    <h1>Security Dashboard</h1>
    <span class="dash-status" id="status">Loading...</span>
</div>

<section class="stats">
    <div class="stat"><span class="stat-num" data-key="logins_ok">0</span><span class="stat-label">Successful log-ins today</span></div>
    <div class="stat"><span class="stat-num" data-key="logins_failed">0</span><span class="stat-label">Failed log-ins today</span></div>
    <div class="stat"><span class="stat-num" data-key="locked_now">0</span><span class="stat-label">Accounts locked now</span></div>
    <div class="stat"><span class="stat-num" data-key="ips_blocked">0</span><span class="stat-label">IPs blocked now</span></div>
    <div class="stat"><span class="stat-num" data-key="otp_sent">0</span><span class="stat-label">OTPs sent today</span></div>
    <div class="stat"><span class="stat-num" data-key="otp_failed">0</span><span class="stat-label">OTPs failed today</span></div>
    <div class="stat"><span class="stat-num" data-key="alerts_sent">0</span><span class="stat-label">Alert emails sent today</span></div>
</section>

<section class="panel">
    <h2>Failed log-ins, last 24 hours</h2>
    <div class="chart-box"><canvas id="hour-chart"></canvas></div>
</section>

<div class="panel-grid">
    <section class="panel">
        <h2>Top IP addresses (24 hours)</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>IP address</th><th>Failures</th><th>Status</th></tr></thead>
                <tbody id="ips-body"></tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Flags from the monitoring script</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Level</th><th>Pattern</th><th>Target</th><th>Details</th><th>Date</th></tr></thead>
                <tbody id="flags-body"></tbody>
            </table>
        </div>
    </section>
</div>

<section class="panel">
    <h2>Recent events</h2>
    <form id="filters" novalidate>
        <div class="filter-grid">
            <div class="field">
                <label for="from">From date</label>
                <input type="date" id="from" name="from">
            </div>
            <div class="field">
                <label for="to">To date</label>
                <input type="date" id="to" name="to">
            </div>
            <div class="field">
                <label for="type">Event type</label>
                <select id="type" name="type">
                    <option value="">All events</option>
                    <?php foreach (EVENT_TYPES as $type): ?>
                        <option value="<?= e($type) ?>"><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="100">
            </div>
            <div class="field">
                <label for="ip">IP address</label>
                <input type="text" id="ip" name="ip" maxlength="45">
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit">Apply filters</button>
            <button type="button" class="refresh-btn" id="filter-reset">Clear</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Time</th><th>Event</th><th>Username typed</th><th>IP address</th><th>Browser</th><th>Details</th></tr>
            </thead>
            <tbody id="events-body"></tbody>
        </table>
    </div>
</section>
<script src="js/chart.umd.js"></script>
<script src="js/dashboard.js"></script>
<?php require 'php/core/footer.php'; ?>