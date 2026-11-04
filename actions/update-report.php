<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_login();
$id = post_int('report_id');
$mode = (isset($_POST['mode']) && $_POST['mode'] === 'status') ? 'status' : 'edit';

if ($mode === 'status') {
    // Staff status change (the service re-checks the role and the allowed transitions).
    $back = safe_return_path(isset($_POST['return_to']) ? $_POST['return_to'] : '', 'staff/manage-items.php?id=' . $id);
    $result = ReportService::changeStatus($me, $id, field($_POST, 'new_status'), field($_POST, 'notes'));
    if ($result['ok']) {
        flash('success', 'The report status was updated.');
    } else {
        flash_many('error', $result['errors']);
    }
    redirect($back);
}

$files = ImageUploadService::uploadedFiles('photo');
$photo = count($files) > 0 ? $files[0] : null;
$result = ReportService::update($me, $id, $_POST, $photo, !empty($_POST['remove_photo']));
if ($result['ok']) {
    if (count($result['changed']) === 0) {
        flash('info', 'No changes were made.');
    } else {
        flash('success', 'Report updated (' . implode(', ', $result['changed']) . ').');
    }
    redirect('student/item-details.php?id=' . $id);
}
flash_many('error', $result['errors']);
set_old($_POST);
redirect('student/edit-report.php?id=' . $id);
