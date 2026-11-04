<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_admin();
$result = SettingsService::save($me['id'], $_POST);
if ($result['ok']) {
    flash('success', count($result['changed']) > 0 ? 'Settings saved (' . implode(', ', $result['changed']) . ').' : 'No settings were changed.');
} else {
    flash_many('error', $result['errors']);
    set_old($_POST);
}
redirect('admin/system-settings.php');
