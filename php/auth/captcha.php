<?php
function captchaNewCode() {
    // No 0/O/1/I, they look the same in a picture
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 5; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha_code'] = $code;
    return $code;
}

// The code is used up after one check, so the same answer cannot be replayed
function captchaValid($input) {
    $real = $_SESSION['captcha_code'] ?? '';
    unset($_SESSION['captcha_code']);
    return $real !== '' && hash_equals(strtolower($real), strtolower($input));
}