<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_admin();
$userId = post_int('user_id');
$op = field($_POST, 'op');

if ($op === 'set_role') {
    $result = UserService::setRole($me, $userId, field($_POST, 'role'));
    $done = 'The role was updated. It takes effect immediately.';
} elseif ($op === 'set_active') {
    $result = UserService::setActive($me, $userId, isset($_POST['active']) && $_POST['active'] === '1');
    $done = 'The account status was updated.';
} elseif ($op === 'reset_password') {
    $newPassword = (isset($_POST['new_password']) && is_string($_POST['new_password'])) ? $_POST['new_password'] : '';
    $result = UserService::resetPassword($me, $userId, $newPassword);
    $done = 'The password was reset.';
} else {
    abort_page(400, 'Bad request', 'That form submission was not understood.');
}
if ($result['ok']) {
    flash('success', $done);
} else {
    flash_many('error', $result['errors']);
}
redirect('admin/manage-users.php');
