<?php

declare(strict_types=1);

namespace AutoPostAI;

class Encryption
{
    public function encrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $method = 'aes-256-cbc';
        $key = wp_salt('auth');
        $iv = substr(wp_salt('secure_auth'), 0, 16);
        $encrypted = openssl_encrypt($value, $method, $key, 0, $iv);

        if ($encrypted === false) {
            return '';
        }

        return base64_encode($encrypted);
    }

    public function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $method = 'aes-256-cbc';
        $key = wp_salt('auth');
        $iv = substr(wp_salt('secure_auth'), 0, 16);
        $decrypted = openssl_decrypt(base64_decode($value), $method, $key, 0, $iv);

        if ($decrypted === false) {
            return '';
        }

        return $decrypted;
    }
}
