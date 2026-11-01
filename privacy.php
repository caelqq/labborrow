<?php
require 'php/bootstrap.php';
$pageTitle = 'Privacy Notice';
$currentPage = 'privacy';
require 'php/header.php';
?>
<div class="card">
    <h1>Privacy Notice</h1>
    <p>This system follows the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

    <h2>What we collect and why</h2>
    <p>Identity and account details (ID number, name, birthdate, address, email, username) so we can identify you and create your account. Your password and security answers are stored only as one-way hashes. Course and year level identify your borrower group. Your contact number reaches you about borrowed equipment and is stored encrypted. Guardian name is optional and is used only if we cannot reach you.</p>

    <h2>How it is protected</h2>
    <p>Passwords, security answers and one-time codes are hashed. The contact number is encrypted. Every login needs an email code, and attacks on accounts are logged and monitored.</p>

    <h2>How long we keep it</h2>
    <p>Your data is kept while your account is active and removed when it is deleted.</p>

    <h2>Contact</h2>
    <p>For questions about your data, contact the system administrator of this laboratory.</p>
</div>
<?php require 'php/footer.php'; ?>