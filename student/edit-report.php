<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_login();
$id = get_int('id');
$report = $id > 0 ? ReportService::find($id) : null;
if (!$report || !(is_staff_user($me) || (int) $report['user_id'] === (int) $me['id'])) {
    abort_page(404, 'Report not found', 'That report does not exist or you do not have access to it.');
}
$check = report_edit_check($me, $report);
if (!$check[0]) {
    flash('error', $check[1]);
    redirect('student/item-details.php?id=' . (int) $report['id']);
}
$old = take_old();
$categories = CategoryService::options();
$photo = upload_url($report['image_path']);
$pageTitle = 'Edit report';
$breadcrumbs = array(array('My reports', 'student/my-reports.php'), array($report['item_name'], 'student/item-details.php?id=' . (int) $report['id']), array('Edit', null));
require __DIR__ . '/../includes/header.php';
?>
<div class="form-wrap wide">
  <h1>Edit report</h1>
  <p><?= type_pill($report['report_type']) ?> <?= badge($report['status']) ?> The report type cannot be changed. <?php if (is_staff_user($me) && (int) $report['user_id'] !== (int) $me['id']): ?>You are editing this report as staff; the reporter will be notified.<?php endif; ?></p>
  <form method="post" action="<?= e(url('actions/update-report.php')) ?>" enctype="multipart/form-data" class="panel" data-submit-guard>
    <?= csrf_field() ?><?= hidden_field('report_id', $report['id']) ?>
    <div class="form-grid">
      <label>Item name<input name="item_name" maxlength="120" required value="<?= e(ov($old, 'item_name', $report['item_name'])) ?>"></label>
      <label>Category<select name="category_id" required><?= select_options($categories, ov($old, 'category_id', (string) $report['category_id']), 'Choose a category') ?></select></label>
    </div>
    <label>Description<textarea name="description" rows="4" maxlength="2000" required><?= e(ov($old, 'description', $report['description'])) ?></textarea></label>
    <div class="form-grid">
      <label>Location<input name="location" maxlength="200" required value="<?= e(ov($old, 'location', $report['location'])) ?>"></label>
      <label>Date<input type="date" name="date_occurred" required max="<?= e(today_local()) ?>" value="<?= e(ov($old, 'date_occurred', $report['date_occurred'])) ?>"></label>
    </div>
    <?php if ($photo !== ''): ?>
      <div class="current-photo"><img class="thumb" src="<?= e($photo) ?>" alt="Current photo of <?= e($report['item_name']) ?>"><label class="checkbox"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label></div>
    <?php endif; ?>
    <label><?= $photo !== '' ? 'Replace photo' : 'Add a photo' ?> (optional)<input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-max-mb="5"><small class="help">JPG, PNG or WebP, up to 5 MB.</small></label>
    <div class="actions"><button class="btn primary" type="submit">Save changes</button><a class="btn ghost" href="<?= e(url('student/item-details.php?id=' . (int) $report['id'])) ?>">Cancel</a></div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
