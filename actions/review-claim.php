<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_staff();
$claimId = post_int('claim_id');
$op = field($_POST, 'op');

if ($op === 'start_review') {
    $result = ClaimService::startReview($me, $claimId);
    $done = 'The claim is now under review and the claimant was notified.';
} elseif ($op === 'request_info') {
    $result = ClaimService::requestInfo($me, $claimId, field($_POST, 'message'));
    $done = 'Your request was sent to the claimant.';
} elseif ($op === 'save_notes') {
    $result = ClaimService::saveNotes($me, $claimId, field($_POST, 'internal_notes'));
    $done = 'Internal notes saved.';
} else {
    abort_page(400, 'Bad request', 'That form submission was not understood.');
}
if ($result['ok']) {
    flash('success', $done);
} else {
    flash_many('error', $result['errors']);
    set_old($_POST);
}
redirect('staff/verify-claims.php?id=' . $claimId);
