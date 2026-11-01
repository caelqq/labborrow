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