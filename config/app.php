<?php
/**
 * CampusFind application configuration (loaded by every page).
 *
 * BASE_URL
 *   Leave BASE_URL_OVERRIDE as null and CampusFind detects its own URL path:
 *     XAMPP:            http://localhost/CampusFind_Project/  ->  BASE_URL = '/CampusFind_Project'
 *     php -S built-in:  http://localhost:8000/                ->  BASE_URL = ''
 *   To force a value, edit BASE_URL_OVERRIDE below (e.g. '/CampusFind_Project' or '')
 *   or set the environment variable CAMPUSFIND_BASE_URL.
 */
if (!defined('BASE_URL_OVERRIDE')) {
    define('BASE_URL_OVERRIDE', null);
}

$cfRoot = realpath(dirname(__DIR__));
define('APP_ROOT', str_replace('\\', '/', $cfRoot !== false ? $cfRoot : dirname(__DIR__)));
define('APP_NAME', 'CampusFind');            // default display name (can be changed in System Settings)
define('APP_SCHOOL', 'La Verdad Christian College');
define('APP_DEBUG', getenv('CAMPUSFIND_DEBUG') === '1');
define('APP_TIMEZONE', 'Asia/Manila');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_IMAGE_MIMES', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);
define('MAX_EVIDENCE_FILES', 3);
define('SESSION_IDLE_SECONDS', 7200);

// Single source of truth for status values (must match database/schema.sql CHECK constraints).
define('REPORT_STATUSES', ['open', 'claim_pending', 'returned', 'closed', 'archived']);
define('CLAIM_STATUSES', ['pending', 'under_review', 'needs_info', 'approved', 'rejected', 'returned', 'cancelled']);
define('ACTIVE_CLAIM_STATUSES', ['pending', 'under_review', 'needs_info', 'approved']);

date_default_timezone_set(APP_TIMEZONE);

function cf_detect_base_url()
{
    if (BASE_URL_OVERRIDE !== null) {
        return rtrim((string) BASE_URL_OVERRIDE, '/');
    }
    $env = getenv('CAMPUSFIND_BASE_URL');
    if ($env !== false && $env !== '') {
        return rtrim($env, '/');
    }
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
    $file = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_FILENAME']) : '';
    if ($script === '' || $file === '') {
        return '';
    }
    $real = realpath($file);
    if ($real !== false) {
        $file = str_replace('\\', '/', $real);
    }
    if (stripos($file, APP_ROOT . '/') !== 0) {
        return '';
    }
    $relative = substr($file, strlen(APP_ROOT));   // e.g. /student/browse-items.php
    if ($relative !== '' && strtolower(substr($script, -strlen($relative))) === strtolower($relative)) {
        return rtrim(substr($script, 0, strlen($script) - strlen($relative)), '/');
    }
    return '';
}
define('BASE_URL', cf_detect_base_url());

// Errors: never shown to visitors unless CAMPUSFIND_DEBUG=1; always logged.
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(APP_ROOT . '/logs') && is_writable(APP_ROOT . '/logs')) {
    ini_set('error_log', APP_ROOT . '/logs/error.log');
}

if (PHP_SAPI !== 'cli') {
    ob_start();
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $cfHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('CAMPUSFIND_SESSION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (BASE_URL === '' ? '/' : BASE_URL . '/'),
            'secure' => $cfHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
} elseif (!isset($_SESSION)) {
    $_SESSION = array();
}
