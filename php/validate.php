<?php
define('NAME_MIN', 2);
define('NAME_MAX', 21);

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