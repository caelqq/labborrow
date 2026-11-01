<?php
require 'php/bootstrap.php';
require 'php/options.php';
require 'php/validate.php';

$pageTitle = 'Register';
$currentPage = 'register';
$mainClass = 'form-page';
$old = $_POST;
$errors = [];
$formError = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Real users never see the hidden field, so a filled one means a bot (protection 8)
    if (!empty($_POST['website'])) {
        header('Location: register.php');
        exit;
    }

    if (!csrf_valid()) {
        $formError = 'Your session expired. Please try again.';
    } else {
        $errors['id_number'] = validateIdNumber(trim($_POST['id_number'] ?? ''));
        $errors['first_name'] = validateName(trim($_POST['first_name'] ?? ''), 'First Name', true);
        $errors['middle_name'] = validateName(trim($_POST['middle_name'] ?? ''), 'Middle Name', false);
        $errors['family_name'] = validateName(trim($_POST['family_name'] ?? ''), 'Family Name', true);
        $errors['name_extension'] = validateNameExtension(
            $_POST['name_extension'] ?? '',
            trim($_POST['name_extension_other'] ?? ''),
            $extensions
        );
        $errors['birthdate'] = validateBirthdate(trim($_POST['birthdate'] ?? ''));
        $errors['purok_street'] = validatePurok(trim($_POST['purok_street'] ?? ''));
        $errors['barangay'] = validateTitleCaseText(trim($_POST['barangay'] ?? ''), 'Barangay');
        $errors['municipality'] = validateTitleCaseText(trim($_POST['municipality'] ?? ''), 'Municipality / City');
        $errors['province'] = validateTitleCaseText(trim($_POST['province'] ?? ''), 'Province');
        $errors['country'] = validateTitleCaseText(trim($_POST['country'] ?? ''), 'Country');
        $errors['zip_code'] = validateZip(trim($_POST['zip_code'] ?? ''));

        $errors = array_filter($errors);
        if (!$errors) {
            $success = true;
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
<?php if ($success): ?>
    <div class="form-success">All checked fields are valid. Saving to the database comes in a later step.</div>
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
            <input type="checkbox" id="consent" name="consent" value="1">
            <label for="consent">I have read the <a href="privacy.php" target="_blank" rel="noopener">Privacy Notice</a> and agree to the collection and processing of my personal data in accordance with the Data Privacy Act of 2012 (RA 10173). <span class="required">*</span></label>
        </div>
        <div class="error"><?= err('consent') ?></div>

        <button type="submit" class="submit-btn">Create account</button>
        <p class="form-foot">Already have an account? <a href="login.php">Log in here</a></p>
    </section>
</form>
<script src="js/register.js"></script>
<?php require 'php/footer.php'; ?>