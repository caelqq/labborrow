<?php
require 'php/bootstrap.php';
require 'php/validate.php';
require 'php/options.php';
require 'php/log.php';
require 'php/captcha.php';
require 'php/otp.php';
require 'php/login_attempts.php';
require 'php/ip_block.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: account.php');
    exit;
}

if (isset($_GET['new'])) {
    unset($_SESSION['forgot']);
    header('Location: forgot.php');
    exit;
}

$pageTitle = 'Forgot Password';
$currentPage = 'forgot';
$mainClass = 'form-page login-page';
$errors = [];
$formError = '';
$wait = 0;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        logEvent('CSRF_REJECTED', null, null, 'forgot');
        $formError = 'Your session expired. Please try again.';
    } elseif ($action === 'start') {
        $username = $_POST['username'] ?? '';
        $captcha = $_POST['captcha'] ?? '';

        if ($username === '') {
            $errors['username'] = 'Username is required.';
        }
        if ($captcha === '') {
            $errors['captcha'] = 'Please enter the characters shown in the image.';
        } elseif (!captchaValid($captcha)) {
            $errors['captcha'] = 'The characters do not match. Please try again.';
        }
        if (!empty($errors['captcha'])) {
            logEvent('CAPTCHA_FAILED', null, null, 'forgot');
        }

        // A name that does not exist goes to step 2 too, so nothing is revealed
        if (!$errors) {
            $_SESSION['forgot'] = ['name' => substr($username, 0, 100)];
            header('Location: forgot.php');
            exit;
        }
    } elseif ($action === 'answer' && !empty($_SESSION['forgot'])) {
        $name = $_SESSION['forgot']['name'];
        $no = (int)($_POST['question'] ?? 0);
        $answer = $_POST['answer'] ?? '';
        $answer2 = $_POST['answer2'] ?? '';

        try {
            $db = getDb();

            // While the IP or the account is locked, nothing else is checked
            if (ipBlockedSeconds($db) === 0 && lockSecondsLeft($db, $name) === 0) {
                if (!isset($securityQuestions[$no])) {
                    $errors['question'] = 'Please choose a question.';
                }
                $errors['answer'] = validateAnswer($answer);
                if ($answer2 === '') {
                    $errors['answer2'] = 'Please re-enter your answer.';
                } elseif ($answer !== $answer2) {
                    $errors['answer2'] = 'Answers do not match.';
                }
                $errors = array_filter($errors);

                if (!$errors) {
                    $row = false;
                    if (validateUsername($name) === '') {
                        $stmt = $db->prepare(
                            'SELECT u.id_number, u.username, u.email, u.is_verified, s.answer_hash
                             FROM users u JOIN security_answers s ON s.user_id = u.id_number
                             WHERE u.username = ? AND s.question_no = ?'
                        );
                        $stmt->execute([$name, $no]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    if ($row) {
                        $answerOk = password_verify(strtolower($answer), $row['answer_hash']);
                    } else {
                        // Same time as a real check, so the speed does not reveal the username
                        password_hash($answer, PASSWORD_BCRYPT, ['cost' => 12]);
                        $answerOk = false;
                    }

                    if ($row && $answerOk && $row['is_verified']) {
                        unset($_SESSION['forgot']);
                        $_SESSION['pending_otp'] = [
                            'id_number' => $row['id_number'],
                            'username' => $row['username'],
                            'email' => $row['email'],
                            'purpose' => 'reset',
                        ];
                        logEvent('RESET_REQUESTED', $row['username'], $row['id_number']);
                        sendOtp($db, $_SESSION['pending_otp'], 'reset');
                        header('Location: verify.php');
                        exit;
                    }

                    // Wrong answer, fake username and unverified account all end up here
                    $userId = $row ? $row['id_number'] : null;
                    logEvent('LOGIN_FAILED', substr($name, 0, 100), $userId, 'reset answer');
                    recordFailure($db, $name, $userId);
                    blockIpIfNeeded($db);
                    $formError = 'The answer is incorrect.';
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $formError = 'Something went wrong. Please try again.';
        }
    }
}

$step = empty($_SESSION['forgot']) ? 1 : 2;

// Both locks are read from the server every time the page is shown
try {
    $db = getDb();
    $wait = ipBlockedSeconds($db);
    if ($step === 2) {
        $wait = max($wait, lockSecondsLeft($db, $_SESSION['forgot']['name']));
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

function err($name) {
    global $errors;
    return e($errors[$name] ?? '');
}

$chosen = (int)($_POST['question'] ?? 0);

require 'php/header.php';
?>
<div class="auth-card">
    <div class="auth-top">
        <div class="auth-badge">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3M16 7l3 3M14 9l2 2"/></svg>
        </div>
        <div>
            <h1>Forgot your password?</h1>
            <p><?= $step === 1 ? 'Enter your username to start.' : 'Answer one of your security questions.' ?></p>
        </div>
    </div>

    <div class="auth-body">
        <?php if ($formError && $wait === 0): ?>
            <div class="form-error"><?= e($formError) ?></div>
        <?php endif; ?>
        <?php if ($wait > 0): ?>
            <div class="lock-box">Too many failed attempts. Try again in <strong id="countdown" data-wait="<?= (int)$wait ?>"><?= (int)$wait ?></strong> seconds.</div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="post" action="forgot.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="start">

                <div class="field">
                    <label for="username">Username <span class="required">*</span></label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        <input type="text" id="username" name="username" maxlength="17" autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="error"><?= err('username') ?></div>
                </div>

                <div class="field">
                    <label for="captcha">Type the characters you see <span class="required">*</span></label>
                    <div class="captcha-row">
                        <img id="captcha-img" class="captcha-img" src="php/captcha_image.php?t=<?= time() ?>" width="210" height="65" alt="CAPTCHA image">
                        <button type="button" id="captcha-refresh" class="refresh-btn">New image</button>
                    </div>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
                        <input type="text" id="captcha" name="captcha" maxlength="6" autocomplete="off">
                    </div>
                    <div class="error"><?= err('captcha') ?></div>
                </div>

                <button type="submit" class="submit-btn" <?= $wait > 0 ? 'disabled' : '' ?>>Continue</button>
            </form>
        <?php else: ?>
            <form method="post" action="forgot.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="answer">

                <div class="field">
                    <label>Choose the following question <span class="required">*</span></label>
                    <div class="q-list">
                        <?php foreach ($securityQuestions as $no => $question): ?>
                            <label class="q-option">
                                <input type="radio" name="question" value="<?= (int)$no ?>" <?= $chosen === $no ? 'checked' : '' ?>>
                                <span><?= e($question) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="error"><?= err('question') ?></div>
                </div>

                <div class="field">
                    <label for="answer">Your Answer <span class="required">*</span></label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <input type="text" id="answer" name="answer" class="answer-input" maxlength="50" autocomplete="off">
                    </div>
                    <div class="error"><?= err('answer') ?></div>
                </div>

                <div class="field">
                    <label for="answer2">Re-enter Answer <span class="required">*</span></label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <input type="text" id="answer2" name="answer2" class="answer-input" maxlength="50" autocomplete="off">
                    </div>
                    <div class="error"><?= err('answer2') ?></div>
                </div>

                <button type="submit" class="submit-btn" <?= $wait > 0 ? 'disabled' : '' ?>>Send me a code</button>
            </form>
            <p class="form-foot"><a href="forgot.php?new=1">Use a different username</a></p>
        <?php endif; ?>

        <p class="form-foot">Remembered it? <a href="login.php">Back to log-in</a></p>
    </div>
</div>
<script src="js/forgot.js"></script>
<?php if ($step === 1): ?>
    <script src="js/captcha.js"></script>
<?php endif; ?>
<?php require 'php/footer.php'; ?>