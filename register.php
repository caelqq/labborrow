<?php
require 'php/bootstrap.php';
require 'php/options.php';
require 'php/validate.php';
require 'php/log.php';
require 'php/users.php';
require 'php/otp.php';
require 'php/captcha.php';

$pageTitle = 'Register';
$currentPage = 'register';
$mainClass = 'form-page';
$old = $_POST;
$errors = [];
$formError = '';

function post($name) {
    return $_POST[$name] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Real users never see the hidden field, so a filled one means a bot (protection 8)
    if (!empty($_POST['website'])) {
        header('Location: register.php');
        exit;
    }

    if (!csrf_valid()) {
        logEvent('CSRF_REJECTED', null, null, 'register');
        $formError = 'Your session expired. Please try again.';
    } else {
        // No trim(): a stray space must show as an error, not be quietly removed
        $errors['id_number'] = validateIdNumber(post('id_number'));
        $errors['first_name'] = validateName(post('first_name'), 'First Name', true);
        $errors['middle_name'] = validateName(post('middle_name'), 'Middle Name', false);
        $errors['family_name'] = validateName(post('family_name'), 'Family Name', true);
        $errors['name_extension'] = validateNameExtension(
            post('name_extension'),
            post('name_extension_other'),
            $extensions
        );
        $errors['birthdate'] = validateBirthdate(post('birthdate'));
        $errors['purok_street'] = validatePurok(post('purok_street'));
        $errors['barangay'] = validateTitleCaseText(post('barangay'), 'Barangay');
        $errors['municipality'] = validateTitleCaseText(post('municipality'), 'Municipality / City');
        $errors['province'] = validateTitleCaseText(post('province'), 'Province');
        $errors['country'] = validateTitleCaseText(post('country'), 'Country');
        $errors['zip_code'] = validateZip(post('zip_code'));
        $errors['course'] = validateChoice(post('course'), $courses, 'Course');
        $errors['year_level'] = validateChoice(post('year_level'), $yearLevels, 'Year level');
        $errors['contact_number'] = validateContact(post('contact_number'));
        $errors['guardian_name'] = validateGuardian(post('guardian_name'));
        $errors['email'] = validateEmail(post('email'));
        $errors['username'] = validateUsername(post('username'));
        $errors['password'] = validatePassword(post('password'));
        $errors['password2'] = validatePassword2(post('password'), post('password2'));
        foreach ($securityQuestions as $no => $question) {
            $errors['answer_' . $no] = validateAnswer(post('answer_' . $no));
        }
        $errors['consent'] = validateConsent(post('consent'));

        // A missing or empty CAPTCHA field fails too, so deleting it in the browser does not help
        if (post('captcha') === '') {
            $errors['captcha'] = 'Please enter the characters shown in the image.';
        } elseif (!captchaValid(post('captcha'))) {
            $errors['captcha'] = 'The characters do not match. Please try again.';
        }
        if (!empty($errors['captcha'])) {
            logEvent('CAPTCHA_FAILED', null, null, 'register');
        }

        $errors = array_filter($errors);

        if (!$errors) {
            try {
                $db = getDb();
                $errors = findDuplicates($db, post('id_number'), post('email'), post('username'));

                if (!$errors) {
                    $extension = post('name_extension') === 'Other' ? post('name_extension_other') : post('name_extension');
                    $data = [
                        'id_number' => post('id_number'),
                        'first_name' => post('first_name'),
                        'middle_name' => post('middle_name') ?: null,
                        'family_name' => post('family_name'),
                        'name_extension' => $extension ?: null,
                        'birthdate' => post('birthdate'),
                        'purok_street' => post('purok_street'),
                        'barangay' => post('barangay'),
                        'municipality' => post('municipality'),
                        'province' => post('province'),
                        'country' => post('country'),
                        'zip_code' => post('zip_code'),
                        'email' => strtolower(post('email')),
                        'username' => post('username'),
                        'password' => post('password'),
                        'course' => post('course'),
                        'year_level' => post('year_level'),
                        'contact_number' => post('contact_number'),
                        'guardian_name' => post('guardian_name') ?: null,
                    ];
                    $answers = [];
                    foreach ($securityQuestions as $no => $question) {
                        $answers[$no] = post('answer_' . $no);
                    }

                    createUser($db, $data, $answers);
                    logEvent('REGISTERED', $data['username'], $data['id_number']);

                    // verify.php only opens when this session flag exists
                    $_SESSION['pending_otp'] = [
                        'id_number' => $data['id_number'],
                        'username' => $data['username'],
                        'email' => $data['email'],
                        'purpose' => 'register',
                    ];
                    // If the email fails, the user can still press Resend on the next page
                    sendOtp($db, $_SESSION['pending_otp'], 'register');

                    header('Location: verify.php');
                    exit;
                }
            } catch (PDOException $e) {
                // The real error goes to the log file, the user only sees a general message (protection 10)
                error_log($e->getMessage());
                $formError = 'Something went wrong. Please try again.';
            }
        }
    }
}

function old($name) {
    global $old;
    return e($old[$name] ?? '');
}

function err($name) {
    global $errors;
    return e($errors[$name] ?? '');
}

function selected($name, $value) {
    global $old;
    return (($old[$name] ?? '') === $value) ? 'selected' : '';
}

require 'php/header.php';
?>
<section class="form-hero">
    <h1>Create your account</h1>
    <p>Fill in your details to start borrowing laboratory equipment. Fields marked with <span class="hero-star">*</span> are required.</p>
</section>

<?php if ($formError): ?>
    <div class="form-error"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="register.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="hp">
        <label for="website">Website</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <section class="form-section">
        <div class="section-head">
            <span class="section-no">1</span>
            <div>
                <h2 class="section-title">Personal information</h2>
                <p class="section-sub">Your name and birthdate as they appear on your school ID.</p>
            </div>
        </div>
        <div class="form-grid">
            <div class="field full">
                <label for="id_number">ID Number <span class="required">*</span></label>
                <input type="text" id="id_number" name="id_number" placeholder="xxxx-xxxx" maxlength="9" value="<?= old('id_number') ?>">
                <div class="error"><?= err('id_number') ?></div>
            </div>

            <div class="field">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" maxlength="21" value="<?= old('first_name') ?>">
                <div class="error"><?= err('first_name') ?></div>
            </div>

            <div class="field">
                <label for="middle_name">Middle Name <span class="optional">optional</span></label>
                <input type="text" id="middle_name" name="middle_name" maxlength="21" value="<?= old('middle_name') ?>">
                <div class="error"><?= err('middle_name') ?></div>
            </div>

            <div class="field">
                <label for="family_name">Family Name <span class="required">*</span></label>
                <input type="text" id="family_name" name="family_name" maxlength="21" value="<?= old('family_name') ?>">
                <div class="error"><?= err('family_name') ?></div>
            </div>

            <div class="field">
                <label for="name_extension">Name Extension <span class="optional">optional</span></label>
                <select id="name_extension" name="name_extension">
                    <option value="">None</option>
                    <?php foreach ($extensions as $ext): ?>
                        <option value="<?= e($ext) ?>" <?= selected('name_extension', $ext) ?>><?= e($ext) ?></option>
                    <?php endforeach; ?>
                    <option value="Other" <?= selected('name_extension', 'Other') ?>>Other</option>
                </select>
                <input type="text" id="name_extension_other" name="name_extension_other" maxlength="4" placeholder="I, II, III, IV, IX, X" value="<?= old('name_extension_other') ?>" hidden>
                <div class="error"><?= err('name_extension') ?></div>
            </div>

            <div class="field">
                <label for="birthdate">Birthdate <span class="required">*</span></label>
                <input type="date" id="birthdate" name="birthdate" max="<?= date('Y-m-d') ?>" value="<?= old('birthdate') ?>">
                <div class="error"><?= err('birthdate') ?></div>
            </div>

            <div class="field">
                <label for="age">Age</label>
                <input type="text" id="age" name="age" readonly>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-head">
            <span class="section-no">2</span>
            <div>
                <h2 class="section-title">Address</h2>
                <p class="section-sub">Where you currently live.</p>
            </div>
        </div>
        <div class="form-grid">
            <div class="field full">
                <label for="purok_street">Purok / Street <span class="required">*</span></label>
                <input type="text" id="purok_street" name="purok_street" maxlength="50" value="<?= old('purok_street') ?>">
                <div class="error"><?= err('purok_street') ?></div>
            </div>

            <div class="field">
                <label for="barangay">Barangay <span class="required">*</span></label>
                <input type="text" id="barangay" name="barangay" maxlength="50" value="<?= old('barangay') ?>">
                <div class="error"><?= err('barangay') ?></div>
            </div>

            <div class="field">
                <label for="municipality">Municipality / City <span class="required">*</span></label>
                <input type="text" id="municipality" name="municipality" maxlength="50" value="<?= old('municipality') ?>">
                <div class="error"><?= err('municipality') ?></div>
            </div>

            <div class="field">
                <label for="province">Province <span class="required">*</span></label>
                <input type="text" id="province" name="province" maxlength="50" value="<?= old('province') ?>">
                <div class="error"><?= err('province') ?></div>
            </div>

            <div class="field">
                <label for="country">Country <span class="required">*</span></label>
                <input type="text" id="country" name="country" maxlength="50" value="<?= old('country') ?>">
                <div class="error"><?= err('country') ?></div>
            </div>

            <div class="field">
                <label for="zip_code">ZIP Code <span class="required">*</span></label>
                <input type="text" id="zip_code" name="zip_code" maxlength="4" value="<?= old('zip_code') ?>">
                <div class="error"><?= err('zip_code') ?></div>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-head">
            <span class="section-no">3</span>
            <div>
                <h2 class="section-title">Borrower details</h2>
                <p class="section-sub">Used to identify you when you borrow equipment.</p>
            </div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="course">Course <span class="required">*</span></label>
                <select id="course" name="course">
                    <option value="">Select course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= e($c) ?>" <?= selected('course', $c) ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="error"><?= err('course') ?></div>
            </div>

            <div class="field">
                <label for="year_level">Year Level <span class="required">*</span></label>
                <select id="year_level" name="year_level">
                    <option value="">Select year level</option>
                    <?php foreach ($yearLevels as $y): ?>
                        <option value="<?= e($y) ?>" <?= selected('year_level', $y) ?>><?= e($y) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="error"><?= err('year_level') ?></div>
            </div>

            <div class="field">
                <label for="contact_number">Contact Number <span class="required">*</span></label>
                <input type="text" id="contact_number" name="contact_number" placeholder="09xxxxxxxxx" maxlength="11" value="<?= old('contact_number') ?>">
                <div class="error"><?= err('contact_number') ?></div>
            </div>

            <div class="field">
                <label for="guardian_name">Guardian Name <span class="optional">optional</span></label>
                <input type="text" id="guardian_name" name="guardian_name" maxlength="50" value="<?= old('guardian_name') ?>">
                <div class="error"><?= err('guardian_name') ?></div>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-head">
            <span class="section-no">4</span>
            <div>
                <h2 class="section-title">Account</h2>
                <p class="section-sub">Your verification codes and alerts are sent to this email.</p>
            </div>
        </div>
        <div class="form-grid">
            <div class="field full">
                <label for="email">Email <span class="required">*</span></label>
                <input type="text" id="email" name="email" maxlength="100" value="<?= old('email') ?>">
                <div class="error"><?= err('email') ?></div>
            </div>

            <div class="field full">
                <label for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" maxlength="17" value="<?= old('username') ?>">
                <div class="error"><?= err('username') ?></div>
            </div>

            <div class="field">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" maxlength="64">
                <div class="meter" id="password-meter">
                    <div class="meter-track"><div class="meter-fill"></div></div>
                    <span class="meter-text"></span>
                </div>
                <div class="error"><?= err('password') ?></div>
            </div>

            <div class="field">
                <label for="password2">Re-enter Password <span class="required">*</span></label>
                <input type="password" id="password2" name="password2" maxlength="64">
                <div class="error"><?= err('password2') ?></div>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-head">
            <span class="section-no">5</span>
            <div>
                <h2 class="section-title">Authentication questions</h2>
                <p class="section-sub">You will answer these if you ever forget your password.</p>
            </div>
        </div>
        <div class="form-grid">
            <?php foreach ($securityQuestions as $no => $question): ?>
                <div class="field full">
                    <label for="answer_<?= $no ?>"><?= e($question) ?> <span class="required">*</span></label>
                    <input type="text" id="answer_<?= $no ?>" name="answer_<?= $no ?>" maxlength="50" value="<?= old('answer_' . $no) ?>">
                    <div class="error"><?= err('answer_' . $no) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="form-section consent-box">
        <div class="consent">
            <input type="checkbox" id="consent" name="consent" value="1" <?= !empty($old['consent']) ? 'checked' : '' ?>>
            <label for="consent">I have read the <a href="privacy.php" target="_blank" rel="noopener">Privacy Notice</a> and agree to the collection and processing of my personal data in accordance with the Data Privacy Act of 2012 (RA 10173). <span class="required">*</span></label>
        </div>
        <div class="error"><?= err('consent') ?></div>

        <div class="field">
            <label for="captcha">Type the characters you see <span class="required">*</span></label>
            <div class="captcha-row">
                <img id="captcha-img" class="captcha-img" src="php/captcha_image.php?t=<?= time() ?>" width="210" height="65" alt="CAPTCHA image">
                <button type="button" id="captcha-refresh" class="refresh-btn">New image</button>
            </div>
            <input type="text" id="captcha" name="captcha" maxlength="6" autocomplete="off">
            <div class="error"><?= err('captcha') ?></div>
        </div>

        <button type="submit" class="submit-btn">Create account</button>
        <p class="form-foot">Already have an account? <a href="login.php">Log in here</a></p>
    </section>
</form>
<script src="js/register.js"></script>
<script src="js/validate.js"></script>
<script src="js/captcha.js"></script>
<?php require 'php/footer.php'; ?>