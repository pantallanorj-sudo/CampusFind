<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_login();
$default = ($me['role'] === 'student') ? 'student/notifications.php' : 'staff/notifications.php';
$back = safe_return_path(isset($_POST['return_to']) ? $_POST['return_to'] : '', $default);

if (!empty($_POST['all'])) {
    $n = NotificationService::markAllRead($me['id']);          // only this user's rows are touched
    flash('success', $n > 0 ? plural($n, 'notification') . ' marked as read.' : 'You have no unread notifications.');
} else {
    if (!NotificationService::markRead($me['id'], post_int('notification_id'))) {
        flash('info', 'That notification was not found or is already read.');
    }
}
redirect($back);
