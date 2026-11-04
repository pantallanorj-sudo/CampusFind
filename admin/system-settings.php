<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$defs = SettingsService::definitions();
$current = SettingsService::all();
$updated = array();
foreach (db_all('SELECT setting_key, updated_at FROM system_settings') as $r) {
    $updated[$r['setting_key']] = $r['updated_at'];
}
$old = take_old();
$pageTitle = 'System settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>System settings</h1><p>Settings are validated on the server. Authentication and role checks cannot be changed from here.</p></div></div>
<form method="post" action="<?= e(url('actions/save-settings.php')) ?>" class="panel settings-form" data-submit-guard>
  <?= csrf_field() ?>
  <?php foreach ($defs as $key => $d): ?>
    <div class="setting">
      <?php if ($d['type'] === 'bool'): ?>
        <?= hidden_field($key, '0') ?>
        <label class="checkbox"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= ov($old, $key, $current[$key]) === '1' ? 'checked' : '' ?>> <?= e($d['label']) ?></label>
      <?php elseif ($d['type'] === 'int'): ?>
        <label><?= e($d['label']) ?><input type="number" name="<?= e($key) ?>" min="<?= (int) $d['min'] ?>" max="<?= (int) $d['max'] ?>" required value="<?= e(ov($old, $key, $current[$key])) ?>"></label>
      <?php else: ?>
        <label><?= e($d['label']) ?><input type="text" name="<?= e($key) ?>" minlength="<?= (int) $d['min'] ?>" maxlength="<?= (int) $d['max'] ?>" required value="<?= e(ov($old, $key, $current[$key])) ?>"></label>
      <?php endif; ?>
      <small class="help"><?= e($d['help']) ?> <?= isset($updated[$key]) ? 'Last changed ' . e(fmt_dt($updated[$key])) . '.' : 'Using the default value.' ?></small>
    </div>
  <?php endforeach; ?>
  <button class="btn primary" type="submit">Save settings</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
