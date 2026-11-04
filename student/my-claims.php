<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_student();
$claimId = get_int('id');
$claim = null;
if ($claimId > 0) {
    $claim = ClaimService::find($claimId);
    // Object-level check: students only ever see their own claims (same answer for "missing" and "not yours").
    if (!$claim || !claim_view_allowed($me, $claim) || (int) $claim['claimant_id'] !== (int) $me['id']) {
        abort_page(404, 'Claim not found', 'That claim does not exist or you do not have access to it.');
    }
}
$old = take_old();
$pageTitle = $claim ? 'Claim #' . (int) $claim['id'] : 'My claims';
$breadcrumbs = $claim ? array(array('My claims', 'student/my-claims.php'), array('Claim #' . (int) $claim['id'], null)) : array(array('Dashboard', 'student/dashboard.php'), array('My claims', null));
require __DIR__ . '/../includes/header.php';

if (!$claim):
    $claims = ClaimService::listForClaimant($me['id']);
?>
<div class="page-head"><div><h1>My claims</h1><p>Track every claim you submitted. A claim is only approved after staff verify your proof of ownership.</p></div>
  <a class="btn secondary" href="<?= e(url('student/browse-items.php?type=found')) ?>">Find an item</a></div>
<?php if (count($claims) === 0): ?>
  <?= empty_state('No claims yet', 'When you find your item in the listings, open it and press “submit a claim”.', '<a class="btn primary small" href="' . e(url('student/browse-items.php?type=found')) . '">Browse found items</a>') ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Item</th><th>Submitted</th><th>Claim status</th><th>Item status</th><th>Last update</th><th></th></tr></thead>
  <tbody><?php foreach ($claims as $c): ?>
    <tr>
      <td><strong><?= e($c['item_name']) ?></strong> <?= type_pill($c['report_type']) ?><br><small class="muted"><?= e($c['category_name']) ?></small></td>
      <td><?= e(fmt_dt($c['created_at'])) ?></td>
      <td><?= badge($c['status']) ?><?php if ($c['status'] === 'needs_info'): ?><br><small class="warn-text">Action needed</small><?php endif; ?></td>
      <td><?= badge($c['report_status']) ?></td>
      <td><?= e(fmt_dt($c['updated_at'])) ?></td>
      <td><a class="btn small secondary" href="<?= e(url('student/my-claims.php?id=' . (int) $c['id'])) ?>">View</a></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>

<?php else:
    $evidence = ClaimService::evidence($claim['id']);
    $history = ClaimService::history($claim['id']);
    $feedback = ClaimService::latestFeedback($claim['id']);
    $handover = db_one('SELECT handed_over_at, handover_notes FROM handover_records WHERE claim_id = ?', array((int) $claim['id']));
    $cancellable = in_array($claim['status'], array('pending', 'under_review', 'needs_info', 'approved'), true);
?>
<div class="page-head"><div><p class="eyebrow">CLAIM #<?= (int) $claim['id'] ?></p><h1><?= e($claim['item_name']) ?></h1></div><div><?= badge($claim['status']) ?></div></div>

<?php if ($claim['status'] === 'needs_info'): ?><div class="alert warning">Staff needs more information. Use the form below to respond.</div><?php endif; ?>
<?php if ($claim['status'] === 'approved'): ?><div class="alert success"><strong>Approved.</strong> Bring your school ID to the lost-and-found desk to collect the item. The claim only becomes “Returned” after staff confirm the handover.</div><?php endif; ?>
<?php if ($claim['status'] === 'returned' && $handover): ?><div class="alert success"><strong>Returned.</strong> Handover confirmed on <?= e(fmt_dt($handover['handed_over_at'])) ?>.</div><?php endif; ?>
<?php if ($claim['status'] === 'rejected'): ?><div class="alert error">This claim was not approved.</div><?php endif; ?>
<?php if ($feedback): ?><section class="panel feedback"><h2>Message from staff</h2><p><?= nl2br_e($feedback['notes']) ?></p><small class="muted"><?= e(status_label($feedback['new_status'])) ?> · <?= e(fmt_dt($feedback['changed_at'])) ?></small></section><?php endif; ?>

<div class="grid-2">
  <section class="panel"><h2>Item</h2>
    <dl class="kv"><div><dt>Item</dt><dd><a href="<?= e(url('student/item-details.php?id=' . (int) $claim['item_report_id'])) ?>"><?= e($claim['item_name']) ?></a></dd></div>
      <div><dt>Category</dt><dd><?= e($claim['category_name']) ?></dd></div>
      <div><dt>Item status</dt><dd><?= badge($claim['report_status']) ?></dd></div>
      <div><dt>Submitted</dt><dd><?= e(fmt_dt($claim['created_at'])) ?></dd></div></dl>
    <h2>What you told us</h2><p><?= nl2br_e($claim['claim_details']) ?></p>
  </section>
  <section class="panel"><h2>Your evidence</h2>
    <?php if (count($evidence) === 0): ?><p class="muted">No evidence recorded.</p><?php else: ?><ul class="plain-list evidence-list">
      <?php foreach ($evidence as $ev): ?><li>
        <strong><?= e($ev['evidence_type'] === 'photo' ? 'Photo' : ($ev['evidence_type'] === 'additional_info' ? 'Additional information' : 'Description')) ?></strong>
        <?= badge($ev['review_status'] === 'accepted' ? 'approved' : ($ev['review_status'] === 'rejected' ? 'rejected' : 'pending')) ?>
        <?php if (!empty($ev['evidence_path'])): ?><br><a href="<?= e(evidence_url($ev['id'])) ?>" target="_blank" rel="noopener"><img class="thumb small" src="<?= e(evidence_url($ev['id'])) ?>" alt="Evidence photo"></a><?php endif; ?>
        <?php if (!empty($ev['evidence_description']) && $ev['evidence_type'] !== 'photo'): ?><p><?= nl2br_e($ev['evidence_description']) ?></p><?php endif; ?>
      </li><?php endforeach; ?></ul><?php endif; ?>
  </section>
</div>

<?php if ($claim['status'] === 'needs_info'): ?>
<section class="panel"><h2>Respond to staff</h2>
  <form method="post" action="<?= e(url('actions/save-claim.php')) ?>" enctype="multipart/form-data" data-submit-guard>
    <?= csrf_field() ?><?= hidden_field('mode', 'respond') ?><?= hidden_field('claim_id', $claim['id']) ?>
    <label>Your answer<textarea name="response_text" rows="4" maxlength="2000" required><?= e(ov($old, 'response_text')) ?></textarea></label>
    <label>More photos (optional)<input type="file" name="evidence_files[]" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-max-mb="5" data-max-files="<?= (int) MAX_EVIDENCE_FILES ?>"></label>
    <button class="btn primary" type="submit">Send to staff</button>
  </form>
</section>
<?php endif; ?>

<section class="panel"><h2>Status history</h2><?= timeline_html($history) ?></section>

<?php if ($cancellable): ?>
<form method="post" action="<?= e(url('actions/save-claim.php')) ?>" data-confirm="Cancel this claim? You will not be able to submit another claim for the same item.">
  <?= csrf_field() ?><?= hidden_field('mode', 'cancel') ?><?= hidden_field('claim_id', $claim['id']) ?><button class="btn danger" type="submit">Cancel this claim</button>
</form>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
