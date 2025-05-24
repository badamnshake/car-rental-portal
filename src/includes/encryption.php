<?php

function generateAESKey($length = 32) {
    return openssl_random_pseudo_bytes($length);
}

function generateIV() {
    return openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
}

function encryptImage($imageData, $key, $iv) {
    return openssl_encrypt($imageData, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

function encryptAESKeyWithRSA($aesKey) {
    $publicKeyPath = __DIR__ . '/../secure/public.pem';
    $publicKey = file_get_contents($publicKeyPath);
    openssl_public_encrypt($aesKey, $encryptedKey, $publicKey);
    return base64_encode($encryptedKey);
}

function decryptAESKeyWithRSA($encryptedKeyBase64) {
    $privateKeyPath = __DIR__ . '/../secure/private.pem';
    $privateKey = file_get_contents($privateKeyPath);
    openssl_private_decrypt(base64_decode($encryptedKeyBase64), $aesKey, $privateKey);
    return $aesKey;
}

function decryptImage($encryptedBase64Image, $aesKey, $ivBase64) {
    return openssl_decrypt(
        base64_decode($encryptedBase64Image),
        'AES-256-CBC',
        $aesKey,
        OPENSSL_RAW_DATA,
        base64_decode($ivBase64)
    );
}
