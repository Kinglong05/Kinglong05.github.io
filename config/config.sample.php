<?php
/**
 * Copy this file to config.php and fill in your values.
 * config.php is git-ignored so your passwords never reach GitHub.
 */

// --- Database ---
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'portfolio_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- App ---
// Set to false on the live server so errors are not shown to visitors.
define('APP_DEBUG', true);
define('APP_TIMEZONE', 'Asia/Manila');

// Max upload sizes in megabytes
define('MAX_IMAGE_MB', 5);
define('MAX_FILE_MB', 10);
define('MAX_VIDEO_MB', 60); // also limited by upload_max_filesize / post_max_size in php.ini
