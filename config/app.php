<?php
/**
 * ASU-REMS application and session configuration.
 * This file must be loaded BEFORE any HTML output and BEFORE session_start().
 */

define('APP_NAME', 'ASU-REMS');
define('BASE_URL', '/asu-rems');

date_default_timezone_set('Asia/Manila');

// Twenty minutes: unchanged from the existing application.
define('SESSION_IDLE_TIMEOUT', 20 * 60);

if (session_status() === PHP_SESSION_NONE) {
    // Use an application-specific cookie so other localhost PHP projects
    // cannot reuse or overwrite ASU-REMS's PHPSESSID cookie.
    session_name('ASUREMSSESSID');

    // Defensive defaults for login sessions.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)(SESSION_IDLE_TIMEOUT + 300));

    // Secure cookies on HTTPS; localhost XAMPP on plain HTTP must use false.
    $isHttps = !empty($_SERVER['HTTPS'])
        && strtolower((string)$_SERVER['HTTPS']) !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        error_log('ASU-REMS: Failed to start the PHP session.');
        http_response_code(500);
        exit('Unable to start a secure session. Please contact the administrator.');
    }
}
