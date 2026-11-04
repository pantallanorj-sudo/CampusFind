<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_staff();
$claimId = post_int('claim_id');
$op = field($_POST, 'op');

if ($op === 'approve') {
    $result = ClaimService::approve($me, $claimId, field($_POST, 'message'));
    $done = 'Claim approved. The claimant was told the item is ready for pickup. It is not "returned" until the handover is confirmed.';
} elseif ($op === 'reject') {
    $result = ClaimService::reject($me, $claimId, field($_POST, 'reason'));
    $done = 'Claim rejected and the claimant was notified.';
} elseif ($op === 'evidence') {
    $result = ClaimService::reviewEvidence($me, post_int('evidence_id'), field($_POST, 'decision'));
    $done = 'Evidence review saved.';
    if (isset($result['claim_id'])) {
        $claimId = (int) $result['claim_id'];
    }
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
