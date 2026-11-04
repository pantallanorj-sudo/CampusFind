<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_staff();
$back = safe_return_path(isset($_POST['return_to']) ? $_POST['return_to'] : '', 'staff/unclaimed-items.php');

$ids = array();
if (!empty($_POST['archive_all'])) {
    $ids = ReportArchiveService::eligibleIds(500);             // re-evaluated now, with the saved retention setting
} elseif (isset($_POST['report_ids']) && is_array($_POST['report_ids'])) {
    foreach ($_POST['report_ids'] as $v) {
        if (is_string($v) && preg_match('/^\d{1,9}$/', $v) === 1 && count($ids) < 200) {
            $ids[] = (int) $v;
        }
    }
} elseif (post_int('report_id') > 0) {
    $ids[] = post_int('report_id');
}
if (count($ids) === 0) {
    flash('error', 'Select at least one report to archive.');
    redirect($back);
}
$result = ReportArchiveService::archiveMany($me, $ids, field($_POST, 'notes'));
if ($result['done'] > 0) {
    flash('success', 'Archived ' . plural($result['done'], 'report') . '. History and claims were kept.');
}
flash_many('error', $result['errors']);
redirect($back);
