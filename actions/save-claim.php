<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_student();
$mode = field($_POST, 'mode');

if ($mode === 'submit') {
    $itemId = post_int('item_id');
    if (empty($_POST['confirm_accuracy'])) {
        flash('error', 'Please confirm that the information you provided is true.');
        set_old($_POST);
        redirect('student/submit-claim.php?item_id=' . $itemId);
    }
    $result = ClaimService::submit($me, $itemId, $_POST, ImageUploadService::uploadedFiles('evidence_files'));
    if ($result['ok']) {
        flash('success', 'Your claim was submitted. Staff will review it; you will be notified of every update.');
        redirect('student/my-claims.php?id=' . (int) $result['claim_id']);
    }
    flash_many('error', $result['errors']);
    set_old($_POST);
    redirect('student/submit-claim.php?item_id=' . $itemId);
}

$claimId = post_int('claim_id');
if ($mode === 'respond') {
    $result = ClaimService::respond($me, $claimId, $_POST, ImageUploadService::uploadedFiles('evidence_files'));
    if ($result['ok']) {
        flash('success', 'Thank you. Your answer was sent and the claim is back under review.');
    } else {
        flash_many('error', $result['errors']);
        set_old($_POST);
    }
    redirect('student/my-claims.php?id=' . $claimId);
}
if ($mode === 'cancel') {
    $result = ClaimService::cancel($me, $claimId);
    if ($result['ok']) {
        flash('success', 'Your claim was cancelled.');
    } else {
        flash_many('error', $result['errors']);
    }
    redirect('student/my-claims.php?id=' . $claimId);
}
abort_page(400, 'Bad request', 'That form submission was not understood.');
