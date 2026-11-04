<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_login();
$old = take_old();
$categories = CategoryService::options();
$duplicates = (isset($_SESSION['duplicate_matches']) && is_array($_SESSION['duplicate_matches'])) ? $_SESSION['duplicate_matches'] : array();
unset($_SESSION['duplicate_matches']);
$type = ov($old, 'report_type', get_enum('type', array('lost', 'found'), ''));
$pageTitle = 'Report an item';
$breadcrumbs = array(array('Dashboard', home_path($me['role'])), array('Report an item', null));
require __DIR__ . '/../includes/header.php';
?>
<div class="form-wrap wide">
  <h1>Report a lost or found item</h1>
  <p>Give enough detail for others to recognise the item, but keep unique identifying details (serial numbers, names, contents) for the claim step.</p>

  <?php if (count($duplicates) > 0): ?>
    <div class="alert warning" role="alert">
      <strong>Possible duplicate<?= count($duplicates) > 1 ? 's' : '' ?> found.</strong> Please check these existing reports. If yours is different, choose “Submit anyway”. (Re-select your photo if you attached one.)
      <ul class="plain-list">
        <?php foreach ($duplicates as $d): ?>
          <li><a href="<?= e(url('student/item-details.php?id=' . (int) $d['id'])) ?>" target="_blank" rel="noopener"><?= e($d['item_name']) ?></a> — <?= e($d['category_name']) ?> · <?= e($d['location']) ?> · <?= e(fmt_date($d['date_occurred'])) ?> <?= badge($d['status']) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('actions/save-report.php')) ?>" enctype="multipart/form-data" class="panel" data-submit-guard>
    <?= csrf_field() ?>
    <?php if (count($duplicates) > 0): ?><input type="hidden" name="ack_duplicates" value="1"><?php endif; ?>
    <fieldset class="choice">
      <legend>This item was</legend>
      <label class="radio"><input type="radio" name="report_type" value="lost" <?= $type === 'lost' ? 'checked' : '' ?> required> Lost</label>
      <label class="radio"><input type="radio" name="report_type" value="found" <?= $type === 'found' ? 'checked' : '' ?>> Found</label>
    </fieldset>
    <div class="form-grid">
      <label>Item name<input name="item_name" maxlength="120" required value="<?= e(ov($old, 'item_name')) ?>" placeholder="e.g. Black umbrella"></label>
      <label>Category<select name="category_id" required><?= select_options($categories, ov($old, 'category_id'), 'Choose a category') ?></select></label>
    </div>
    <label>Description<textarea name="description" rows="4" maxlength="2000" required placeholder="Colour, brand, size, condition…"><?= e(ov($old, 'description')) ?></textarea></label>
    <div class="form-grid">
      <label>Location<input name="location" maxlength="200" required value="<?= e(ov($old, 'location')) ?>" placeholder="Where it was lost or found"></label>
      <label>Date lost or found<input type="date" name="date_occurred" required max="<?= e(today_local()) ?>" value="<?= e(ov($old, 'date_occurred', today_local())) ?>"></label>
    </div>
    <label>Photo (optional)<input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-max-mb="5"><small class="help">JPG, PNG or WebP, up to 5 MB.</small></label>
    <div class="actions"><button class="btn primary" type="submit"><?= count($duplicates) > 0 ? 'Submit anyway' : 'Submit report' ?></button><a class="btn ghost" href="<?= e(url(home_path($me['role']))) ?>">Cancel</a></div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
