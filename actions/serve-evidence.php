<?php
/**
 * Permission-checked delivery of PRIVATE claim evidence (the files are never reachable by a direct URL).
 * Allowed: the claimant who owns the claim, the user who uploaded the item, and staff/administrators.
 */
require_once __DIR__ . '/../includes/auth.php';
$me = require_login();

function evidence_not_found()
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

$id = get_int('id');
$ev = $id > 0 ? db_one('SELECT * FROM claim_evidence WHERE id = ?', array($id)) : null;
if (!$ev || empty($ev['evidence_path'])) {
    evidence_not_found();
}
$claim = ClaimService::find($ev['claim_id']);
// Same answer for "does not exist" and "not yours" so ids cannot be probed.
if (!$claim || !evidence_view_allowed($me, $ev, $claim) || strpos($ev['evidence_path'], 'claim-evidence/') !== 0) {
    evidence_not_found();
}
$abs = ImageUploadService::absolutePath($ev['evidence_path']);
$info = ($abs !== null) ? getimagesize($abs) : false;
if ($abs === null || $info === false || !array_key_exists($info['mime'], ALLOWED_IMAGE_MIMES)) {
    evidence_not_found();
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $info['mime']);
header('Content-Length: ' . (string) filesize($abs));
header('Content-Disposition: inline; filename="evidence-' . (int) $ev['id'] . '.' . ALLOWED_IMAGE_MIMES[$info['mime']] . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($abs);
exit;
