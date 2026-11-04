<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_admin();
$result = CategoryService::create($me['id'], field($_POST, 'name'), field($_POST, 'description'));
if ($result['ok']) {
    flash('success', 'Category added.');
} else {
    flash_many('error', $result['errors']);
    set_old($_POST);
}
redirect('admin/manage-categories.php');
