<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_student();
$itemId = get_int('item_id');
$report = $itemId > 0 ? ReportService::find($itemId) : null;
if (!$report || !report_view_allowed($me, $report)) {
    abort_page(404, 'Item not found', 'That item does not exist, or it is no longer available.');
}
$elig = ClaimService::eligibility($me, $report);
$old = take_old();
$photo = upload_url($report['image_path']);
$pageTitle = 'Submit a claim';
$breadcrumbs = array(array('Browse items', 'student/browse-items.php'), array($report['item_name'], 'student/item-details.php?id=' . (int) $report['id']), array('Claim', null));
require __DIR__ . '/../includes/header.php';
?>
<div class="form-wrap wide">
  <h1>Claim this item</h1>
  <section class="panel summary">
    <?php if ($photo !== ''): ?><img class="thumb" src="<?= e($photo) ?>" alt="Photo of <?= e($report['item_name']) ?>"><?php endif; ?>
    <div><h2><?= e($report['item_name']) ?></h2><p class="muted"><?= e($report['category_name']) ?> · found at <?= e($report['location']) ?> on <?= e(fmt_date($report['date_occurred'])) ?> <?= badge($report['status']) ?></p></div>
  </section>
  <?php if (!$elig[0]): ?>
    <div class="alert warning" role="alert"><?= e($elig[1]) ?></div>
    <p><a class="btn secondary" href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>">Back to the item</a></p>
  <?php else: ?>
    <div class="alert info">Staff will compare your answers with the item before approving. Submitting a claim does <strong>not</strong> approve it, and your answers stay private.</div>
    <form method="post" action="<?= e(url('actions/save-claim.php')) ?>" enctype="multipart/form-data" class="panel" data-submit-guard>
      <?= csrf_field() ?><?= hidden_field('mode', 'submit') ?><?= hidden_field('item_id', $report['id']) ?>
      <label>What do you know about the item?<textarea name="claim_details" rows="4" maxlength="2000" required placeholder="Describe unique features, contents, marks, when and where you lost it…"><?= e(ov($old, 'claim_details')) ?></textarea></label>
      <label>How can you prove it is yours?<textarea name="evidence_description" rows="3" maxlength="1000" required placeholder="Serial number, receipt, lock-screen photo, name inside, who can vouch for you…"><?= e(ov($old, 'evidence_description')) ?></textarea></label>
      <label>Supporting photos (optional, up to <?= (int) MAX_EVIDENCE_FILES ?>)<input type="file" name="evidence_files[]" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-max-mb="5" data-max-files="<?= (int) MAX_EVIDENCE_FILES ?>"><small class="help">Receipts, photos of you with the item, box or serial label. Private: only you and staff can see them.</small></label>
      <label class="checkbox"><input type="checkbox" name="confirm_accuracy" value="1" required> I confirm the information I provided is true.</label>
      <div class="actions"><button class="btn primary" type="submit">Submit claim</button><a class="btn ghost" href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>">Cancel</a></div>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
