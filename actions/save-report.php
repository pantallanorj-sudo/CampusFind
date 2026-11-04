<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_login();

$files = ImageUploadService::uploadedFiles('photo');
$photo = count($files) > 0 ? $files[0] : null;
$result = ReportService::create($me, $_POST, $photo, !empty($_POST['ack_duplicates']));

if ($result['ok']) {
    flash('success', 'Your report was submitted. You can follow it under My reports.');
    redirect('student/item-details.php?id=' . (int) $result['id']);
}
set_old($_POST);
if (isset($result['duplicates']) && count($result['duplicates']) > 0) {
    $_SESSION['duplicate_matches'] = $result['duplicates'];   // the form page lists them; the user decides
} else {
    flash_many('error', $result['errors']);
}
redirect('student/report-item.php');
