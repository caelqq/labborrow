<?php
require 'php/bootstrap.php';
require 'php/otp.php';

$pageTitle = 'Verify Email';
$currentPage = 'verify';
$mainClass = 'form-page';

// Without this session flag the user did not come from the form, so send them back
if (empty($_SESSION['pending_otp'])) {
    header('Location: index.php');
    exit;
}

$pending = $_SESSION['pending_otp'];
$purpose = $pending['purpose'];
$codeError = '';
$formError = '';
$notice = '';
$done = false;
$wait = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        logEvent('CSRF_REJECTED', null, null, 'verify');
        $formError = 'Your session expired. Please try again.';
    } else {
        try {
            $db = getDb();
            if (($_POST['action'] ?? '') === 'resend') {
                $formError = sendOtp($db, $pending, $purpose);
                if ($formError === '') {
                    $notice = 'A new code was sent to your email.';
                }
            } else {
                $code = $_POST['code'] ?? '';
                if (!preg_match('/^\d{6}$/', $code)) {
                    $codeError = 'Enter the 6-digit code from your email.';
                } else {
                    $codeError = verifyOtp($db, $pending, $purpose, $code);
                    if ($codeError === '') {
                        // Only the register purpose exists so far; log-in and reset are added later
                        $db->prepare('UPDATE users SET is_verified = 1 WHERE id_number = ?')->execute([$pending['id_number']]);
                        logEvent('ACCOUNT_VERIFIED', $pending['username'], $pending['id_number']);
                        unset($_SESSION['pending_otp']);
                        $done = true;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $formError = 'Something went wrong. Please try again.';
        }
    }
}

if (!$done) {
    try {
        $wait = otpWaitSeconds(getDb(), $pending['id_number'], $purpose);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

require 'php/header.php';
?>
<?php if ($done): ?>
    <section class="form-hero">
        <h1>Account verified</h1>
        <p>Your email is confirmed. You can log in once the log-in page is ready.</p>
    </section>
    <a class="btn" href="index.php">Go to Home</a>
<?php else: ?>
    <section class="form-hero">
        <h1>Check your email</h1>
        <p>We sent a 6-digit code to <?= e($pending['email']) ?>. It expires in <?= OTP_MINUTES ?> minutes.</p>
    </section>

    <?php if ($formError): ?>
        <div class="form-error"><?= e($formError) ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="form-success"><?= e($notice) ?></div>
    <?php endif; ?>

    <section class="form-section">
        <form method="post" action="verify.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="field">
                <label for="code">Verification code <span class="required">*</span></label>
                <input type="text" id="code" name="code" class="otp-input" maxlength="6" inputmode="numeric" autocomplete="off">
                <div class="error"><?= e($codeError) ?></div>
            </div>
            <button type="submit" class="submit-btn">Verify</button>
        </form>

        <form method="post" action="verify.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="secondary-btn" id="resend-btn" data-wait="<?= (int)$wait ?>">Resend code</button>
        </form>
    </section>
<?php endif; ?>
<script src="js/verify.js"></script>
<?php require 'php/footer.php'; ?>