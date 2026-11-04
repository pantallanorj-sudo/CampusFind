<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_staff();
$focus = get_int('claim_id');
$waiting = HandoverService::awaiting();
$recent = HandoverService::recent(10);
$old = take_old();
$nowLocal = date('Y-m-d\TH:i');
$pageTitle = 'Handover';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Item handover</h1><p>Hand over an item only to the approved claimant, after checking their school ID. Completing the form marks the claim and the item as <strong>Returned</strong>.</p></div></div>
<?php if (count($waiting) === 0): ?>
  <?= empty_state('No approved claims are waiting', 'When staff approve a claim it appears here until the item is collected.') ?>
<?php else: ?>
  <?php foreach ($waiting as $w): ?>
  <section class="panel handover-card" id="claim-<?= (int) $w['id'] ?>">
    <div class="handover-head">
      <?= item_photo_html($w, 'thumb') ?>
      <div><h2><?= e($w['item_name']) ?> <small class="muted">claim #<?= (int) $w['id'] ?></small></h2>
        <p class="muted"><?= e($w['category_name']) ?> · found at <?= e($w['location']) ?></p>
        <p>Recipient: <strong><?= e($w['claimant_name']) ?></strong> (<?= e($w['claimant_email']) ?>)<br><small class="muted">Approved <?= e(fmt_dt($w['reviewed_at'])) ?></small></p></div>
    </div>
    <details <?= $focus === (int) $w['id'] ? 'open' : '' ?>>
      <summary class="btn secondary small">Confirm handover…</summary>
      <form method="post" action="<?= e(url('actions/confirm-handover.php')) ?>" data-confirm="Confirm that this item was handed to <?= e($w['claimant_name']) ?>? This cannot be undone." data-submit-guard>
        <?= csrf_field() ?><?= hidden_field('claim_id', $w['id']) ?><?= hidden_field('recipient_id', $w['claimant_id']) ?>
        <div class="form-grid">
          <label>Handover date and time (Manila)<input type="datetime-local" name="handed_over_at" required max="<?= e($nowLocal) ?>" value="<?= e($nowLocal) ?>"></label>
          <label>Recipient<input value="<?= e($w['claimant_name']) ?>" disabled></label>
        </div>
        <label>Handover notes (optional)<textarea name="handover_notes" rows="2" maxlength="1000"></textarea></label>
        <label class="checkbox"><input type="checkbox" name="identity_confirmed" value="1" required> I checked the recipient’s school ID and they are the approved claimant.</label>
        <button class="btn primary" type="submit">Confirm handover</button>
      </form>
    </details>
  </section>
  <?php endforeach; ?>
<?php endif; ?>
<section class="panel"><h2>Recent handovers</h2>
  <?php if (count($recent) === 0): ?><p class="muted">No handovers recorded yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th>Recipient</th><th>Handed over</th><th>Confirmed by</th><th>Notes</th></tr></thead><tbody>
    <?php foreach ($recent as $h): ?><tr><td><a href="<?= e(url('staff/verify-claims.php?id=' . (int) $h['claim_id'])) ?>"><?= e($h['item_name']) ?></a></td><td><?= e($h['recipient_name']) ?></td><td><?= e(fmt_dt($h['handed_over_at'])) ?></td><td><?= e($h['staff_name']) ?></td><td><?= e($h['handover_notes']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
