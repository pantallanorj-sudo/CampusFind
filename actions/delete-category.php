<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_admin();
$result = CategoryService::delete($me['id'], post_int('category_id'));
if ($result['ok']) {
    flash('success', 'Category deleted.');
} else {
    flash_many('error', $result['errors']);
}
redirect('admin/manage-categories.php');
