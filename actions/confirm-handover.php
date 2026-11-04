<?php
require_once __DIR__ . '/../includes/auth.php';
require_post();
verify_csrf();
$me = require_staff();
$claimId = post_int('claim_id');
$result = HandoverService::confirm($me, $claimId, post_int('recipient_id'), field($_POST, 'handed_over_at'), field($_POST, 'handover_notes'), !empty($_POST['identity_confirmed']));
if ($result['ok']) {
    flash('success', 'Handover confirmed. The claim and the item are now marked as returned.');
    redirect('staff/handover.php');
}
flash_many('error', $result['errors']);
redirect('staff/handover.php?claim_id=' . $claimId);
