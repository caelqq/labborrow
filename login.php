<?php
require 'php/core/bootstrap.php';
require 'php/core/validate.php';
require 'php/core/log.php';
require 'php/auth/captcha.php';
require 'php/auth/otp.php';
require 'php/auth/login_attempts.php';
require 'php/auth/ip_block.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: account.php');
    exit;
}

$pageTitle = 'Log-in';
$currentPage = 'login';
$mainClass = 'form-page login-page';
$formError = '';
$failed = 0;
$wait = 0;

// Same length and character rules as registration, without the Weak check
function loginFormatOk($username, $password) {
    return validateUsername($username) === ''
        && strpos($password, ' ') === false
        && strlen($password) >= 9
        && strlen($password) <= 64;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        logEvent('CSRF_REJECTED', null, null, 'login');
        $formError = 'Your session expired. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $captcha = $_POST['captcha'] ?? '';
        $_SESSION['login_name'] = substr($username, 0, 100);

        try {
            $db = getDb();

            // While the IP or the account is locked, nothing else is checked
            if (ipBlockedSeconds($db) === 0 && lockSecondsLeft($db, $username) === 0) {
                // Every problem gets the same message, so nothing is revealed
                $formError = 'Invalid username or password.';

                if ($captcha === '' || !captchaValid($captcha)) {
                    logEvent('CAPTCHA_FAILED', null, null, 'login');
                } elseif ($username !== '' && $password !== '') {
                    $user = false;
                    if (loginFormatOk($username, $password)) {
                        $stmt = $db->prepare('SELECT id_number, username, email, password_hash, is_verified FROM users WHERE username = ?');
                        $stmt->execute([$username]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    if ($user) {
                        $passwordOk = password_verify($password, $user['password_hash']);
                    } else {
                        // Takes the same time as a real check, so the speed does not reveal the username
                        password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $passwordOk = false;
                    }

                    if ($user && $passwordOk && $user['is_verified']) {
                        // No session yet: the log-in OTP comes first
                        $_SESSION['pending_otp'] = [
                            'id_number' => $user['id_number'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'purpose' => 'login',
                        ];
                        sendOtp($db, $_SESSION['pending_otp'], 'login');
                        header('Location: verify.php');
                        exit;
                    }

                    // Wrong name, wrong password, bad format and unverified account all end up here
                    $userId = $user ? $user['id_number'] : null;
                    logEvent('LOGIN_FAILED', substr($username, 0, 100), $userId);
                    recordFailure($db, $username, $userId);
                    blockIpIfNeeded($db);
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $formError = 'Something went wrong. Please try again.';
        }
    }
}

// Both locks are read from the server every time the page is shown
$name = $_SESSION['login_name'] ?? '';
try {
    $db = getDb();
    $wait = ipBlockedSeconds($db);
    if ($name !== '') {
        $wait = max($wait, lockSecondsLeft($db, $name));
        $failed = failedCount($db, $name);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

require 'php/core/header.php';
?>
<div class="auth-card">
    <div class="auth-top">
        <div class="auth-badge">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1.2" fill="currentColor"/></svg>
        </div>
        <div>
            <h1>Log in to your account</h1>
            <p>Use your username and password to continue.</p>
        </div>
    </div>

    <div class="auth-body">
        <?php if ($formError && $wait === 0): ?>
            <div class="form-error"><?= e($formError) ?></div>
        <?php endif; ?>
        <?php if ($wait > 0): ?>
            <div class="lock-box">Too many failed attempts. Try again in <strong id="countdown" data-wait="<?= (int)$wait ?>"><?= (int)$wait ?></strong> seconds.</div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="field">
                <label for="username">Username <span class="required">*</span></label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <input type="text" id="username" name="username" maxlength="17" autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label for="password">Password <span class="required">*</span></label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <input type="password" id="password" name="password" class="has-eye" maxlength="64" autocomplete="current-password">
                    <button type="button" class="eye-btn" id="eye-btn" title="Show Password" aria-label="Show Password">
                        <svg class="icon-eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M3 3l18 18"/></svg>
                        <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <?php if ($failed >= FORGOT_AFTER): ?>
                <p class="forgot"><a href="forgot.php">Forgot Password? Reset Here</a></p>
            <?php endif; ?>

            <div class="field">
                <label for="captcha">Type the characters you see <span class="required">*</span></label>
                <div class="captcha-row">
                    <img id="captcha-img" class="captcha-img" src="php/background/captcha_image.php?t=<?= time() ?>" width="210" height="65" alt="CAPTCHA image">
                    <button type="button" id="captcha-refresh" class="refresh-btn">New image</button>
                </div>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
                    <input type="text" id="captcha" name="captcha" maxlength="6" autocomplete="off">
                </div>
            </div>

            <button type="submit" class="submit-btn" id="login-btn" <?= $wait > 0 ? 'disabled' : '' ?>>Log in</button>
        </form>

        <p class="form-foot">Don't have an account?
            <?php if ($wait > 0): ?>
                <span class="link-disabled">Register here</span>
            <?php else: ?>
                <a href="register.php">Register here</a>
            <?php endif; ?>
        </p>
    </div>
</div>
<script src="js/login.js"></script>
<script src="js/captcha.js"></script>
<?php require 'php/core/footer.php'; ?>