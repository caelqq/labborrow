<?php
define('NAME_MIN', 2);
define('NAME_MAX', 21);

function hasEdgeSpace($value) {
    return $value !== trim($value);
}

function validateIdNumber($value) {
    if ($value === '') {
        return 'ID Number is required.';
    }
    if (!preg_match('/^\d{4}-\d{4}$/', $value)) {
        return 'ID Number must follow the format xxxx-xxxx.';
    }
    return '';
}

// The order of the checks matters: JUAN must hit the all-capitals message, not the title-case one
function validateName($value, $label, $required) {
    if ($value === '') {
        return $required ? "$label is required." : '';
    }
    if (strpos($value, '  ') !== false) {
        return 'Double space is not allowed.';
    }
    if (hasEdgeSpace($value)) {
        return 'Spaces at the start or end are not allowed.';
    }
    if (preg_match('/\d/', $value)) {
        return "Numbers are not allowed in $label.";
    }
    if (preg_match('/[^A-Za-z ]/', $value)) {
        return "Special characters are not allowed in $label.";
    }
    if (strlen($value) < NAME_MIN || strlen($value) > NAME_MAX) {
        return "$label must be " . NAME_MIN . ' to ' . NAME_MAX . ' characters.';
    }
    if ($value === strtoupper($value)) {
        return 'Do not use all capital letters.';
    }
    // (.)\1\1 means any character repeated 3 times; the i flag ignores case
    if (preg_match('/(.)\1\1/i', $value)) {
        return 'Three consecutive identical letters are not allowed.';
    }
    if (!preg_match('/^[A-Z][a-z]*( [A-Z][a-z]*)*$/', $value)) {
        if (preg_match('/(^| )[a-z]/', $value)) {
            return 'First letter of your name must be capital.';
        }
        return 'Only the first letter of each word may be capital.';
    }
    return '';
}

function calculateAge($birthdate) {
    $born = DateTime::createFromFormat('Y-m-d', $birthdate);
    return $born->diff(new DateTime('today'))->y;
}

function validateBirthdate($value) {
    if ($value === '') {
        return 'Birthdate is required.';
    }
    $born = DateTime::createFromFormat('Y-m-d', $value);
    // The format check catches fake dates like 2005-02-31
    if (!$born || $born->format('Y-m-d') !== $value) {
        return 'Please enter a valid birthdate.';
    }
    if ($born > new DateTime('today')) {
        return 'Birthdate cannot be in the future.';
    }
    if (calculateAge($value) < 18) {
        return 'You must be at least 18 years old to register.';
    }
    return '';
}

// The dropdown can be edited with browser tools, so the server checks the list itself
function validateNameExtension($choice, $other, $allowed) {
    if ($choice === '') {
        return '';
    }
    if ($choice === 'Other') {
        if ($other === '') {
            return 'Please type your name extension.';
        }
        if (!in_array($other, ['I', 'II', 'III', 'IV', 'IX', 'X'], true)) {
            return 'Name extension must be I, II, III, IV, IX or X.';
        }
        return '';
    }
    if (!in_array($choice, $allowed, true)) {
        return 'Please select a valid name extension.';
    }
    return '';
}

function validatePurok($value) {
    if ($value === '') {
        return 'Purok / Street is required.';
    }
    if (strpos($value, '  ') !== false) {
        return 'Double space is not allowed.';
    }
    if (hasEdgeSpace($value)) {
        return 'Spaces at the start or end are not allowed.';
    }
    if (preg_match('/[^A-Za-z0-9 ]/', $value)) {
        return 'Special characters are not allowed in Purok / Street.';
    }
    if (strlen($value) < 3 || strlen($value) > 50) {
        return 'Purok / Street must be 3 to 50 characters.';
    }
    return '';
}

// Used for Barangay, Municipality / City, Province and Country
function validateTitleCaseText($value, $label) {
    if ($value === '') {
        return "$label is required.";
    }
    if (strpos($value, '  ') !== false) {
        return 'Double space is not allowed.';
    }
    if (hasEdgeSpace($value)) {
        return 'Spaces at the start or end are not allowed.';
    }
    if (preg_match('/\d/', $value)) {
        return "Numbers are not allowed in $label.";
    }
    if (preg_match('/[^A-Za-z ]/', $value)) {
        return "Special characters are not allowed in $label.";
    }
    if (strlen($value) < 2 || strlen($value) > 50) {
        return "$label must be 2 to 50 characters.";
    }
    if (!preg_match('/^[A-Z][a-z]*( [A-Z][a-z]*)*$/', $value)) {
        if (preg_match('/(^| )[a-z]/', $value)) {
            return "First letter of each word in $label must be capital.";
        }
        return "Only the first letter of each word in $label may be capital.";
    }
    return '';
}

function validateZip($value) {
    if ($value === '') {
        return 'ZIP Code is required.';
    }
    if (!preg_match('/^\d{4}$/', $value)) {
        return 'ZIP Code must be exactly 4 digits.';
    }
    return '';
}

function validateEmail($value) {
    if ($value === '') {
        return 'Email is required.';
    }
    if (preg_match('/\s/', $value)) {
        return 'Email must not contain spaces.';
    }
    if (strlen($value) > 100 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    return '';
}

function validateUsername($value) {
    if ($value === '') {
        return 'Username is required.';
    }
    if (preg_match('/[^A-Za-z0-9]/', $value)) {
        return 'Username may contain letters and digits only.';
    }
    if (!preg_match('/^[A-Za-z]/', $value)) {
        return 'Username must start with a letter.';
    }
    if (strlen($value) < 8 || strlen($value) > 17) {
        return 'Username must be 8 to 17 characters.';
    }
    return '';
}

// Counts the kinds of characters used: lowercase, uppercase, digit, symbol
function passwordStrength($value) {
    $kinds = 0;
    foreach (['/[a-z]/', '/[A-Z]/', '/[0-9]/', '/[^A-Za-z0-9]/'] as $pattern) {
        if (preg_match($pattern, $value)) {
            $kinds++;
        }
    }
    if ($kinds <= 1) {
        return 'Weak';
    }
    return $kinds == 4 ? 'Strong' : 'Medium';
}

function validatePassword($value) {
    if ($value === '') {
        return 'Password is required.';
    }
    if (strpos($value, ' ') !== false) {
        return 'Password must not contain spaces.';
    }
    if (strlen($value) < 9 || strlen($value) > 64) {
        return 'Password must be 9 to 64 characters.';
    }
    if (passwordStrength($value) === 'Weak') {
        return 'Password is too weak. Mix uppercase, lowercase, numbers or symbols.';
    }
    return '';
}

function validatePassword2($password, $password2) {
    if ($password2 === '') {
        return 'Please re-enter your password.';
    }
    if ($password !== $password2) {
        return 'Passwords do not match.';
    }
    return '';
}