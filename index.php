<?php
require 'php/bootstrap.php';
$pageTitle = 'Home';
$currentPage = 'home';
$mainClass = 'wide';
$bodyClass = 'has-sidebar';
$showSidebar = true;
require 'php/header.php';
?>
<section class="hero">
    <h1>Borrow lab equipment, the easy way</h1>
    <p>Create your account, verify your email, and manage your laboratory equipment borrowing in one secure place.</p>
</section>

<section class="steps">
    <div class="step">
        <span class="step-no">1</span>
        <h3>Register</h3>
        <p>Fill in your details and agree to the Privacy Notice.</p>
    </div>
    <div class="step">
        <span class="step-no">2</span>
        <h3>Verify</h3>
        <p>Enter the one-time code we send to your email.</p>
    </div>
    <div class="step">
        <span class="step-no">3</span>
        <h3>Log in</h3>
        <p>Sign in with your password and a fresh email code.</p>
    </div>
</section>

<div class="about">
    <strong>What this system does.</strong>
    The Campus Lab Equipment Borrowing System helps students reserve and borrow laboratory equipment, and helps the laboratory keep track of who has borrowed what.
</div>

<p class="reminder">Your account is protected. Passwords are hashed, every log-in needs an email code, and repeated wrong attempts lock the account and alert you by email.</p>
<?php require 'php/footer.php'; ?>