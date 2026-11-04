<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_admin();
$result = CategoryService::update($me['id'], post_int('category_id'), field($_POST, 'name'), field($_POST, 'description'));
if ($result['ok']) {
    flash('success', 'Category updated.');
} else {
    flash_many('error', $result['errors']);
}
redirect('admin/manage-categories.php');
