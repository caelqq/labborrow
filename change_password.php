<?php
require 'php/core/bootstrap.php';
require 'php/core/validate.php';
require 'php/core/log.php';
require 'php/auth/login_attempts.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

// Only reachable after the question and the reset code; verify.php sets this flag
if (empty($_SESSION['reset_ok'])) {
    header('Location: index.php');
    exit;
}

$reset = $_SESSION['reset_ok'];
$pageTitle = 'Change Password';
$currentPage = 'change';
$mainClass = 'form-page login-page';
$errors = [];
$formError = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        logEvent('CSRF_REJECTED', null, null, 'change password');
        $formError = 'Your session expired. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        $errors['password'] = validatePassword($password);
        if ($password2 === '') {
            $errors['password2'] = 'Please re-enter your password.';
        } elseif ($password !== $password2) {
            $errors['password2'] = 'Mismatch Password';
        }

        try {
            $db = getDb();

            if (!$errors['password'] && !$errors['password2']) {
                // The last 3 saved passwords, the current one included
                $stmt = $db->prepare('SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY id DESC LIMIT 3');
                $stmt->execute([$reset['id_number']]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $oldHash) {
                    if (password_verify($password, $oldHash)) {
                        $errors['password'] = 'You cannot reuse a recent password.';
                        break;
                    }
                }
            }

            $errors = array_filter($errors);

            if (!$errors) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->beginTransaction();
                try {
                    $db->prepare('UPDATE users SET password_hash = ? WHERE id_number = ?')->execute([$hash, $reset['id_number']]);
                    $db->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)')->execute([$reset['id_number'], $hash]);
                    $db->commit();
                } catch (PDOException $e) {
                    $db->rollBack();
                    throw $e;
                }

                // The reset is used up: reloading or going back cannot open this page again
                unset($_SESSION['reset_ok']);
                resetFailures($db, $reset['username']);
                logEvent('PASSWORD_CHANGED', $reset['username'], $reset['id_number']);
                require_once 'php/mail/alerts.php';
                sendPasswordChangedAlert($db, $reset['id_number']);
                $done = true;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $formError = 'Something went wrong. Please try again.';
        }
    }
}

function err($name) {
    global $errors;
    return e($errors[$name] ?? '');
}

require 'php/core/header.php';
?>
<div class="auth-card">
    <div class="auth-top">
        <div class="auth-badge">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1.2" fill="currentColor"/></svg>
        </div>
        <div>
            <h1>Change Password</h1>
            <p>Choose a new password for your account.</p>
        </div>
    </div>

    <div class="auth-body">
        <?php if ($done): ?>
            <div class="form-success">Successfully Change Password</div>
            <a class="btn" href="login.php">Go to Log-in</a>
        <?php else: ?>
            <?php if ($formError): ?>
                <div class="form-error"><?= e($formError) ?></div>
            <?php endif; ?>

            <form method="post" action="change_password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="field">
                    <label for="password">Enter Password <span class="required">*</span></label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <input type="password" id="password" name="password" maxlength="64" autocomplete="new-password">
                    </div>
                    <div class="meter" id="password-meter">
                        <div class="meter-track"><div class="meter-fill"></div></div>
                        <span class="meter-text"></span>
                    </div>
                    <div class="error"><?= err('password') ?></div>
                </div>

                <div class="field">
                    <label for="password2">Re-enter Password <span class="required">*</span></label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <input type="password" id="password2" name="password2" maxlength="64" autocomplete="new-password">
                    </div>
                    <div class="error"><?= err('password2') ?></div>
                </div>

                <button type="submit" class="submit-btn">Change Password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php if (!$done): ?>
    <script src="js/change_password.js"></script>
<?php endif; ?>
<?php require 'php/core/footer.php'; ?>