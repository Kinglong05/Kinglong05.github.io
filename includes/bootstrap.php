<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (!is_file(APP_ROOT . '/config/config.php')) {
    http_response_code(500);
    exit('Missing config/config.php - copy config/config.sample.php to config/config.php and fill in your database details.');
}
require APP_ROOT . '/config/config.php';

// URL_PREFIX = relative path from the current script back to the site root ('' or '../')
if (!defined('URL_PREFIX')) {
    define('URL_PREFIX', '');
}

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
date_default_timezone_set(APP_TIMEZONE);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('pf_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/migrations.php';

if (is_installed()) {
    run_migrations();
}
