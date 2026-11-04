<?php
/** Core helpers (escaping, URLs, flash messages, dates, pagination, errors). No HTML builders here - see components.php. */
require_once __DIR__ . '/../config/database.php';

/** Thrown by services when a business rule is broken (message is safe to show to the user). */
class BusinessRuleException extends Exception
{
}

function cf_autoload($class)
{
    if (!is_string($class) || preg_match('/^[A-Za-z0-9_]+$/', $class) !== 1) {
        return;
    }
    $file = APP_ROOT . '/services/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
}
spl_autoload_register('cf_autoload');
set_exception_handler('cf_exception_handler');

/* ---------- output, URLs, requests ---------- */

function e($value)
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_array($value) || is_object($value)) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url($path = '')
{
    return rtrim(BASE_URL, '/') . '/' . ltrim((string) $path, '/');
}

function asset($path)
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $version;
}

function request_method()
{
    return isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
}

function is_post()
{
    return request_method() === 'POST';
}

/** Path of the running script relative to the app root, e.g. "student/my-claims.php". */
function current_script()
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
    $base = rtrim(BASE_URL, '/');
    if ($base !== '' && stripos($script, $base . '/') === 0) {
        $script = substr($script, strlen($base));
    }
    return ltrim($script, '/');
}

function current_relative_url()
{
    $query = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
    return current_script() . ($query !== '' ? '?' . $query : '');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

/** Accepts only simple relative "folder/page.php?x=y" paths (blocks open redirects). */
function safe_return_path($raw, $default)
{
    if (!is_string($raw) || $raw === '' || strpos($raw, '..') !== false) {
        return $default;
    }
    // A clean relative path only: no leading slash, no "//", no scheme, no "..".
    if (preg_match('/^[A-Za-z0-9_][A-Za-z0-9_\-]*(\/[A-Za-z0-9_\-]+)*\.php(\?[A-Za-z0-9_=&%.\-\[\]]*)?$/', $raw) !== 1) {
        return $default;
    }
    return $raw;
}

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
}

function get_str($key, $max = 100)
{
    if (!isset($_GET[$key]) || !is_string($_GET[$key])) {
        return '';
    }
    $v = trim($_GET[$key]);
    return text_len($v) > $max ? text_cut($v, $max, '') : $v;
}

function get_int($key, $default = 0)
{
    if (isset($_GET[$key]) && is_string($_GET[$key]) && preg_match('/^\d{1,9}$/', $_GET[$key]) === 1) {
        return (int) $_GET[$key];
    }
    return $default;
}

function get_enum($key, $allowed, $default = '')
{
    $v = get_str($key, 40);
    return in_array($v, $allowed, true) ? $v : $default;
}

function get_date($key)
{
    $v = get_str($key, 10);
    return valid_date($v) ? $v : '';
}

function build_query($params)
{
    $clean = array();
    foreach ($params as $k => $v) {
        if ($v !== '' && $v !== null && $v !== false) {
            $clean[$k] = $v;
        }
    }
    return http_build_query($clean, '', '&');
}

function page_url($path, $params = array())
{
    $qs = build_query($params);
    return url($path) . ($qs !== '' ? '?' . $qs : '');
}

/* ---------- flash messages and old input ---------- */

function flash($type, $message)
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = array();
    }
    $_SESSION['flash'][] = array('type' => (string) $type, 'message' => (string) $message);
}

function flash_many($type, $messages)
{
    foreach ($messages as $m) {
        flash($type, $m);
    }
}

function get_flashes()
{
    $all = (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) ? $_SESSION['flash'] : array();
    unset($_SESSION['flash']);
    return $all;
}

function set_old($data)
{
    $keep = array();
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            if (is_string($v) && !in_array($k, array('password', 'password_confirm', 'csrf_token', 'new_password'), true)) {
                $keep[$k] = $v;
            }
        }
    }
    $_SESSION['old'] = $keep;
}

function take_old()
{
    $old = (isset($_SESSION['old']) && is_array($_SESSION['old'])) ? $_SESSION['old'] : array();
    unset($_SESSION['old']);
    return $old;
}

/* ---------- text ---------- */

function text_len($s)
{
    $s = (string) $s;
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function text_cut($s, $max, $suffix = '…')
{
    $s = (string) $s;
    if (text_len($s) <= $max) {
        return $s;
    }
    $cut = function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
    return rtrim($cut) . $suffix;
}

function text_lower($s)
{
    $s = (string) $s;
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function nl2br_e($s)
{
    return nl2br(e($s));
}

function plural($n, $one, $many = null)
{
    $many = $many === null ? $one . 's' : $many;
    return (int) $n . ' ' . ((int) $n === 1 ? $one : $many);
}

function initials($name)
{
    $out = '';
    foreach (preg_split('/\s+/', trim((string) $name)) as $part) {
        if ($part !== '' && text_len($out) < 2) {
            $out .= function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($part, 0, 1));
        }
    }
    return $out === '' ? '?' : $out;
}

/** LIKE pattern with wildcards escaped (use with: LIKE ? ESCAPE '!'). */
function like_pattern($term)
{
    return '%' . str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), (string) $term) . '%';
}

/* ---------- dates (database stores UTC; screens show Asia/Manila) ---------- */

function now_utc()
{
    return gmdate('Y-m-d H:i:s');
}

function valid_date($s)
{
    if (!is_string($s) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) {
        return false;
    }
    return checkdate((int) substr($s, 5, 2), (int) substr($s, 8, 2), (int) substr($s, 0, 4));
}

function today_local()
{
    return date('Y-m-d');
}

/** Unix timestamp for a UTC "YYYY-MM-DD HH:MM[:SS]" string, or null. */
function utc_ts($utc)
{
    if (!is_string($utc) || preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $utc) !== 1) {
        return null;
    }
    $sec = strlen($utc) >= 19 ? (int) substr($utc, 17, 2) : 0;
    return gmmktime((int) substr($utc, 11, 2), (int) substr($utc, 14, 2), $sec, (int) substr($utc, 5, 2), (int) substr($utc, 8, 2), (int) substr($utc, 0, 4));
}

function fmt_dt($utc, $withTime = true)
{
    $ts = utc_ts($utc);
    if ($ts === null) {
        return '—';
    }
    return date($withTime ? 'M j, Y g:i A' : 'M j, Y', $ts);
}

function fmt_date($ymd)
{
    if (!valid_date($ymd)) {
        return '—';
    }
    return date('M j, Y', mktime(0, 0, 0, (int) substr($ymd, 5, 2), (int) substr($ymd, 8, 2), (int) substr($ymd, 0, 4)));
}

/** "2026-10-30T15:45" typed by the user in Manila time -> UTC "Y-m-d H:i:s", or null. */
function local_input_to_utc($value)
{
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $value) !== 1) {
        return null;
    }
    if (!valid_date(substr($value, 0, 10))) {
        return null;
    }
    $h = (int) substr($value, 11, 2);
    $i = (int) substr($value, 14, 2);
    if ($h > 23 || $i > 59) {
        return null;
    }
    return gmdate('Y-m-d H:i:s', mktime($h, $i, 0, (int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)));
}

/** Inclusive local dates -> array(startUtc|null, endUtcExclusive|null) for filtering timestamp columns. */
function local_date_bounds($from, $to)
{
    $start = null;
    $end = null;
    if (valid_date($from)) {
        $start = gmdate('Y-m-d H:i:s', mktime(0, 0, 0, (int) substr($from, 5, 2), (int) substr($from, 8, 2), (int) substr($from, 0, 4)));
    }
    if (valid_date($to)) {
        $end = gmdate('Y-m-d H:i:s', mktime(0, 0, 0, (int) substr($to, 5, 2), (int) substr($to, 8, 2) + 1, (int) substr($to, 0, 4)));
    }
    return array($start, $end);
}

function days_since($utc)
{
    $ts = utc_ts($utc);
    return $ts === null ? 0 : (int) floor((time() - $ts) / 86400);
}

/* ---------- pagination ---------- */

function paginate($total, $perPage, $page)
{
    $perPage = max(1, (int) $perPage);
    $total = max(0, (int) $total);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, (int) $page), $pages);
    return array('total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage);
}

/* ---------- logging ---------- */

function log_line($file, $line)
{
    if (getenv('CAMPUSFIND_NO_LOG') === '1') {      // set by tests/run-tests.php so tests leave no log lines
        return;
    }
    $dir = APP_ROOT . '/logs';
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    file_put_contents($dir . '/' . $file, date('c') . ' ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/** Security-relevant admin actions (role changes, activation, settings...). */
function audit_log($actorId, $action, $detail = '')
{
    log_line('audit.log', 'actor=' . (int) $actorId . ' action=' . $action . ' ' . str_replace(array("\r", "\n"), ' ', (string) $detail));
}

function app_name()
{
    try {
        return SettingsService::get('app_name');
    } catch (Throwable $e) {
        return APP_NAME;
    }
}

/* ---------- error pages ---------- */

/** Show a friendly error page inside the normal layout and stop. */
function abort_page($code, $title, $message)
{
    if (!headers_sent()) {
        http_response_code((int) $code);
    }
    $pageTitle = $title;
    require __DIR__ . '/header.php';
    echo '<section class="panel narrow"><h1>' . e($title) . '</h1><p>' . e($message) . '</p>';
    echo '<p><a class="btn secondary" href="' . e(url('index.php')) . '">Back to home</a></p></section>';
    require __DIR__ . '/footer.php';
    exit;
}

function cf_exception_handler($e)
{
    error_log('CampusFind ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    $notReady = ($e instanceof DatabaseNotReadyException);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($notReady ? 503 : 500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $title = $notReady ? 'CampusFind is not set up yet' : 'Something went wrong';
    $message = $notReady
        ? 'The database has not been initialised. Run setup-database.php (see README.md, step 4) and reload this page.'
        : 'This page could not be displayed. The problem was logged. Please try again in a moment.';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . '</title><link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head><body class="public">';
    echo '<main class="content"><section class="panel narrow"><h1>' . e($title) . '</h1><p>' . e($message) . '</p>';
    if (APP_DEBUG) {
        echo '<pre class="debug">' . e(get_class($e) . ': ' . $e->getMessage()) . '</pre>';
    }
    echo '<p><a class="btn secondary" href="' . e(url('index.php')) . '">Back to home</a></p></section></main></body></html>';
}
