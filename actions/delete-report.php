<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_login();
$back = safe_return_path(isset($_POST['return_to']) ? $_POST['return_to'] : '', 'student/my-reports.php');
$result = ReportService::withdraw($me, post_int('report_id'));
if ($result['ok']) {
    flash('success', 'The report was deleted from the listings. A closed record is kept for the audit history.');
} else {
    flash_many('error', $result['errors']);
}
redirect($back);
