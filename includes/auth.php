<?php
/** Authentication and session helpers. Pages include this file once; it loads every other helper. */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/components.php';

function home_path($role)
{
    if ($role === 'admin') {
        return 'admin/dashboard.php';
    }
    if ($role === 'staff') {
        return 'staff/dashboard.php';
    }
    return 'student/dashboard.php';
}

/**
 * The signed-in user, re-read from the database on every request so that role changes
 * and deactivation take effect immediately. Returns null for guests.
 */
function current_user($refresh = false)
{
    static $cache = null;
    static $loaded = false;
    if ($loaded && !$refresh) {
        return $cache;
    }
    $loaded = true;
    $cache = null;
    $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($id < 1) {
        return null;
    }
    $last = isset($_SESSION['last_seen']) ? (int) $_SESSION['last_seen'] : time();
    if (time() - $last > SESSION_IDLE_SECONDS) {
        logout_user('You were signed out after a period of inactivity. Please log in again.');
        return null;
    }
    $row = db_one('SELECT id, full_name, email, role, is_active FROM users WHERE id = ?', array($id));
    if (!$row || (int) $row['is_active'] !== 1) {
        logout_user($row ? 'Your account is inactive. Please contact a CampusFind administrator.' : null);
        return null;
    }
    $_SESSION['last_seen'] = time();
    $cache = array('id' => (int) $row['id'], 'full_name' => $row['full_name'], 'email' => $row['email'], 'role' => $row['role']);
    return $cache;
}

/** Credentials check with a small per-session throttle. Returns array($userRow|null, $errorMessage|null). */
function authenticate($email, $password)
{
    $email = strtolower(trim((string) $email));
    $now = time();
    $fail = (isset($_SESSION['login_fail']) && is_array($_SESSION['login_fail'])) ? $_SESSION['login_fail'] : array('count' => 0, 'first' => $now);
    if ($now - (int) $fail['first'] > 600) {
        $fail = array('count' => 0, 'first' => $now);
    }
    if ((int) $fail['count'] >= 5) {
        $wait = max(1, (int) ceil((600 - ($now - (int) $fail['first'])) / 60));
        return array(null, 'Too many failed attempts. Please wait about ' . $wait . ' minute(s) and try again.');
    }
    $row = db_one('SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1', array($email));
    $passwordOk = false;
    if ($row) {
        $passwordOk = password_verify((string) $password, $row['password_hash']);
    } else {
        password_hash('campusfind-timing-pad', PASSWORD_DEFAULT);   // keeps timing similar for unknown emails
    }
    if ($passwordOk && (int) $row['is_active'] === 1) {
        unset($_SESSION['login_fail']);
        return array($row, null);
    }
    $fail['count'] = (int) $fail['count'] + 1;
    $_SESSION['login_fail'] = $fail;
    if ($passwordOk) {
        return array(null, 'This account is inactive. Please contact a CampusFind administrator.');
    }
    return array(null, 'Incorrect email or password.');
}

function login_user($row)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);      // prevents session fixation
    }
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['login_fail']);
    current_user(true);
}

function logout_user($message = null)
{
    $_SESSION = array();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);      // old session id becomes useless
    }
    if ($message !== null) {
        flash('info', $message);
    }
    current_user(true);
}

function require_login()
{
    $user = current_user();
    if ($user) {
        return $user;
    }
    if (request_method() === 'GET') {
        $_SESSION['intended'] = current_relative_url();
    }
    flash('error', 'Please log in to continue.');
    redirect('login.php');
}

function require_role($roles)
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        abort_page(403, 'Access denied', 'Your account does not have permission to open this page.');
    }
    return $user;
}
