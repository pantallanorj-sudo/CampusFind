<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_staff();
$claimId = get_int('id');
if ($claimId < 1) {
    redirect('staff/manage-claims.php');
}
$claim = ClaimService::find($claimId);
if (!$claim) {
    abort_page(404, 'Claim not found', 'That claim does not exist.');
}
$evidence = ClaimService::evidence($claimId);
$history = ClaimService::history($claimId);
$others = db_all('SELECT q.id, q.status, u.full_name AS claimant_name FROM claim_requests q JOIN users u ON u.id = q.claimant_id WHERE q.item_report_id = ? AND q.id <> ? ORDER BY q.created_at', array((int) $claim['item_report_id'], (int) $claimId));
$handover = db_one('SELECT h.handed_over_at, h.handover_notes, s.full_name AS staff_name FROM handover_records h JOIN users s ON s.id = h.confirmed_by WHERE h.claim_id = ?', array((int) $claimId));
$old = take_old();
$status = $claim['status'];
$acceptedCount = 0;
foreach ($evidence as $ev) {
    if ($ev['review_status'] === 'accepted') {
        $acceptedCount++;
    }
}
$photo = upload_url($claim['image_path']);
$queuePath = ($me['role'] === 'admin') ? 'admin/manage-claims.php' : 'staff/manage-claims.php';
$pageTitle = 'Review claim #' . (int) $claim['id'];
$breadcrumbs = array(array('Claims', $queuePath), array('Claim #' . (int) $claim['id'], null));
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">CLAIM #<?= (int) $claim['id'] ?></p><h1><?= e($claim['item_name']) ?></h1></div><div><?= badge($status) ?> <?= badge($claim['report_status']) ?></div></div>

<div class="grid-2">
  <section class="panel"><h2>Item</h2>
    <?php if ($photo !== ''): ?><img class="thumb" src="<?= e($photo) ?>" alt="Photo of <?= e($claim['item_name']) ?>"><?php endif; ?>
    <dl class="kv">
      <div><dt>Category</dt><dd><?= e($claim['category_name']) ?></dd></div>
      <div><dt>Found at</dt><dd><?= e($claim['location']) ?></dd></div>
      <div><dt>Date found</dt><dd><?= e(fmt_date($claim['date_occurred'])) ?></dd></div>
      <div><dt>Report</dt><dd><a href="<?= e(url('student/item-details.php?id=' . (int) $claim['item_report_id'])) ?>">Open item #<?= (int) $claim['item_report_id'] ?></a></dd></div>
    </dl>
    <h3>Finder’s description</h3><p><?= nl2br_e($claim['item_description']) ?></p>
  </section>
  <section class="panel"><h2>Claimant</h2>
    <dl class="kv">
      <div><dt>Name</dt><dd><?= e($claim['claimant_name']) ?></dd></div>
      <div><dt>Email</dt><dd><?= e($claim['claimant_email']) ?></dd></div>
      <div><dt>Submitted</dt><dd><?= e(fmt_dt($claim['created_at'])) ?></dd></div>
      <div><dt>Reviewer</dt><dd><?= e($claim['reviewer_name'] !== null ? $claim['reviewer_name'] : '—') ?></dd></div>
    </dl>
    <h3>What the claimant says</h3><p><?= nl2br_e($claim['claim_details']) ?></p>
  </section>
</div>

<section class="panel"><h2>Evidence <small class="muted">(private — visible to staff and the claimant only)</small></h2>
  <?php if (count($evidence) === 0): ?><p class="muted">No evidence recorded.</p><?php else: ?>
  <ul class="evidence-review">
    <?php foreach ($evidence as $ev): ?>
    <li>
      <div>
        <strong><?= e($ev['evidence_type'] === 'photo' ? 'Photo' : ($ev['evidence_type'] === 'additional_info' ? 'Additional information' : 'Proof description')) ?></strong>
        <span class="muted"><?= e(fmt_dt($ev['created_at'])) ?></span>
        <?= badge($ev['review_status'] === 'accepted' ? 'approved' : ($ev['review_status'] === 'rejected' ? 'rejected' : 'pending')) ?>
        <?php if (!empty($ev['evidence_path'])): ?><br><a href="<?= e(evidence_url($ev['id'])) ?>" target="_blank" rel="noopener"><img class="thumb" src="<?= e(evidence_url($ev['id'])) ?>" alt="Evidence photo submitted by the claimant"></a><?php endif; ?>
        <?php if (!empty($ev['evidence_description']) && $ev['evidence_type'] !== 'photo'): ?><p><?= nl2br_e($ev['evidence_description']) ?></p><?php endif; ?>
        <?php if (!empty($ev['reviewer_name'])): ?><small class="muted">Reviewed by <?= e($ev['reviewer_name']) ?></small><?php endif; ?>
      </div>
      <?php if (in_array($status, ACTIVE_CLAIM_STATUSES, true)): ?>
      <form method="post" action="<?= e(url('actions/verify-claim.php')) ?>" class="inline-form button-row">
        <?= csrf_field() ?><?= hidden_field('op', 'evidence') ?><?= hidden_field('claim_id', $claim['id']) ?><?= hidden_field('evidence_id', $ev['id']) ?>
        <button class="btn small secondary" name="decision" value="accepted" type="submit">Accept</button>
        <button class="btn small danger" name="decision" value="rejected" type="submit">Reject</button>
        <button class="btn small ghost" name="decision" value="pending" type="submit">Reset</button>
      </form>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul><?php endif; ?>
</section>

<?php if ($status === 'pending' || $status === 'needs_info'): ?>
<section class="panel"><h2><?= $status === 'pending' ? 'Start the review' : 'Resume the review' ?></h2>
  <p><?= $status === 'pending' ? 'Taking the claim into review tells the claimant that staff are checking it.' : 'The claimant has not answered yet. You can resume the review at any time.' ?></p>
  <form method="post" action="<?= e(url('actions/review-claim.php')) ?>"><?= csrf_field() ?><?= hidden_field('op', 'start_review') ?><?= hidden_field('claim_id', $claim['id']) ?><button class="btn primary" type="submit"><?= $status === 'pending' ? 'Start review' : 'Resume review' ?></button></form>
</section>
<?php endif; ?>

<?php if ($status === 'under_review'): ?>
<div class="grid-2">
  <section class="panel"><h2>Request more information</h2>
    <form method="post" action="<?= e(url('actions/review-claim.php')) ?>" data-submit-guard><?= csrf_field() ?><?= hidden_field('op', 'request_info') ?><?= hidden_field('claim_id', $claim['id']) ?>
      <label>Message to the claimant<textarea name="message" rows="3" maxlength="1000" required placeholder="What should they describe or show?"><?= e(ov($old, 'message')) ?></textarea></label>
      <button class="btn secondary" type="submit">Ask for information</button></form>
  </section>
  <section class="panel"><h2>Approve claim</h2>
    <?php if ($acceptedCount === 0): ?><p class="notice">No evidence item has been accepted yet. Approve only after you are satisfied the claimant owns the item.</p><?php endif; ?>
    <form method="post" action="<?= e(url('actions/verify-claim.php')) ?>" data-confirm="Approve this claim? The claimant will be told to collect the item." data-submit-guard><?= csrf_field() ?><?= hidden_field('op', 'approve') ?><?= hidden_field('claim_id', $claim['id']) ?>
      <label>Message to the claimant (optional)<textarea name="message" rows="2" maxlength="1000" placeholder="e.g. Collect at the Student Affairs desk, 8am–5pm"></textarea></label>
      <button class="btn primary" type="submit">Approve claim</button></form>
  </section>
</div>
<?php endif; ?>

<?php if ($status === 'approved'): ?>
<section class="panel"><h2>Approved — waiting for pickup</h2><p>The claim is approved but the item has <strong>not</strong> been returned yet. Confirm the handover when the claimant collects it.</p>
  <a class="btn primary" href="<?= e(url('staff/handover.php?claim_id=' . (int) $claim['id'])) ?>">Go to handover</a></section>
<?php endif; ?>

<?php if (in_array($status, array('pending', 'under_review', 'needs_info', 'approved'), true)): ?>
<section class="panel"><h2><?= $status === 'approved' ? 'Revoke approval' : 'Reject claim' ?></h2>
  <form method="post" action="<?= e(url('actions/verify-claim.php')) ?>" data-confirm="Reject this claim? This cannot be undone." data-submit-guard><?= csrf_field() ?><?= hidden_field('op', 'reject') ?><?= hidden_field('claim_id', $claim['id']) ?>
    <label>Reason (shown to the claimant)<textarea name="reason" rows="2" maxlength="1000" required><?= e(ov($old, 'reason')) ?></textarea></label>
    <button class="btn danger" type="submit"><?= $status === 'approved' ? 'Revoke and reject' : 'Reject claim' ?></button></form>
</section>
<?php endif; ?>

<?php if ($status === 'returned' && $handover): ?><div class="alert success">Handed over on <?= e(fmt_dt($handover['handed_over_at'])) ?> (confirmed by <?= e($handover['staff_name']) ?>).<?= !empty($handover['handover_notes']) ? ' Notes: ' . e($handover['handover_notes']) : '' ?></div><?php endif; ?>

<section class="panel"><h2>Internal staff notes <small class="muted">(never shown to the claimant)</small></h2>
  <form method="post" action="<?= e(url('actions/review-claim.php')) ?>"><?= csrf_field() ?><?= hidden_field('op', 'save_notes') ?><?= hidden_field('claim_id', $claim['id']) ?>
    <label class="sr-only" for="internal_notes">Internal notes</label><textarea id="internal_notes" name="internal_notes" rows="3" maxlength="2000"><?= e(ov($old, 'internal_notes', (string) $claim['staff_notes'])) ?></textarea>
    <button class="btn secondary" type="submit">Save notes</button></form>
</section>

<?php if (count($others) > 0): ?><section class="panel"><h2>Other claims on this item</h2><ul class="plain-list"><?php foreach ($others as $o): ?>
  <li><a href="<?= e(url('staff/verify-claims.php?id=' . (int) $o['id'])) ?>">Claim #<?= (int) $o['id'] ?></a> by <?= e($o['claimant_name']) ?> <?= badge($o['status']) ?></li><?php endforeach; ?></ul></section><?php endif; ?>

<section class="panel"><h2>Claim history</h2><?= timeline_html($history) ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
