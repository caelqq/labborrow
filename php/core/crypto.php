<?php
function encryptField($plain) {
    $key = hex2bin(getConfig()['enc_key']);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

// Only call this to show the value to its owner
function decryptField($stored) {
    $key = hex2bin(getConfig()['enc_key']);
    $raw = base64_decode($stored);
    return openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr($raw, 0, 16));
}