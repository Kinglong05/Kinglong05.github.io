<?php
declare(strict_types=1);

/**
 * Two-step login: 6-digit codes from an authenticator app
 * (Google Authenticator, Microsoft Authenticator, Authy...). Standard TOTP (RFC 6238).
 */

const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function totp_new_secret(): string
{
    $bytes = random_bytes(20);
    $bits = '';
    foreach (str_split($bytes) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= B32[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function totp_b32_decode(string $s): string
{
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s));
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos(B32, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_code(string $secret, ?int $time = null): string
{
    $counter = intdiv($time ?? time(), 30);
    $hash = hash_hmac('sha1', pack('N*', 0, $counter), totp_b32_decode($secret), true);
    $o = ord($hash[19]) & 0x0f;
    $num = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Accepts the current code and one step before/after (phone clocks drift a little). */
function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    foreach ([-1, 0, 1] as $w) {
        if (hash_equals(totp_code($secret, time() + $w * 30), $code)) return true;
    }
    return false;
}

function totp_uri(string $secret, string $account, string $issuer = 'Portfolio Admin'): string
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}
