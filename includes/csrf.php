<?php
/** CSRF protection and POST-only guards for every state-changing request. */
require_once __DIR__ . '/functions.php';

function csrf_token()
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf()
{
    $sent = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        abort_page(419, 'Form expired', 'Your form session expired or the request was not valid. Go back, reload the page, and try again.');
    }
}

function require_post()
{
    if (request_method() !== 'POST') {
        header('Allow: POST');
        abort_page(405, 'Method not allowed', 'This address only accepts form submissions from CampusFind.');
    }
}
