<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_admin();
$password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
$result = UserService::create($me, field($_POST, 'full_name'), field($_POST, 'email'), $password, field($_POST, 'role'));
if ($result['ok']) {
    flash('success', 'Account created. Share the temporary password securely; the user can log in right away.');
} else {
    flash_many('error', $result['errors']);
    set_old($_POST);
}
redirect('admin/manage-users.php');
