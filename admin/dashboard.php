<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$s = ExportService::summary();
$users = array('total' => db_count('SELECT COUNT(*) FROM users'), 'active' => db_count('SELECT COUNT(*) FROM users WHERE is_active = 1'));
$byRole = array();
foreach (db_all('SELECT role, COUNT(*) AS n FROM users GROUP BY role') as $r) {
    $byRole[$r['role']] = (int) $r['n'];
}
$reportCounts = array();
foreach (db_all('SELECT status, COUNT(*) AS n FROM item_reports GROUP BY status') as $r) {
    $reportCounts[$r['status']] = (int) $r['n'];
}
$categories = db_count('SELECT COUNT(*) FROM categories');
$recentItem = HistoryService::feed('item', array(), 1, 6);
$recentClaim = HistoryService::feed('claim', array(), 1, 6);
$pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">ADMIN DASHBOARD</p><h1>Administration</h1><p>System overview from live database records.</p></div></div>
<div class="stats">
  <a class="stat" href="<?= e(url('admin/manage-users.php')) ?>"><span><?= (int) $users['total'] ?></span><small>Users (<?= (int) $users['active'] ?> active)</small></a>
  <a class="stat" href="<?= e(url('admin/manage-users.php?role=staff')) ?>"><span><?= (int) (isset($byRole['staff']) ? $byRole['staff'] : 0) ?></span><small>Staff accounts</small></a>
  <a class="stat" href="<?= e(url('admin/manage-reports.php')) ?>"><span><?= (int) array_sum($reportCounts) ?></span><small>Item reports</small></a>
  <a class="stat" href="<?= e(url('admin/manage-claims.php?status=all')) ?>"><span><?= (int) $s['claims_total'] ?></span><small>Claims</small></a>
  <a class="stat" href="<?= e(url('admin/manage-categories.php')) ?>"><span><?= (int) $categories ?></span><small>Categories</small></a>
  <a class="stat" href="<?= e(url('admin/archived-reports.php')) ?>"><span><?= (int) $s['archived'] ?></span><small>Archived reports</small></a>
</div>
<div class="grid-2">
  <section class="panel"><h2>Reports by status</h2><ul class="plain-list">
    <?php foreach (REPORT_STATUSES as $st): ?><li><?= badge($st) ?> <strong><?= (int) (isset($reportCounts[$st]) ? $reportCounts[$st] : 0) ?></strong></li><?php endforeach; ?></ul></section>
  <section class="panel"><h2>Claims by status</h2><ul class="plain-list">
    <?php foreach ($s['claims'] as $st => $n): ?><li><?= badge($st) ?> <strong><?= (int) $n ?></strong></li><?php endforeach; ?></ul></section>
</div>
<section class="panel"><div class="section-heading"><h2>Latest audit entries</h2><a href="<?= e(url('admin/status-history.php')) ?>">Open audit trail →</a></div>
  <?php if (count($recentItem['rows']) + count($recentClaim['rows']) === 0): ?><p class="muted">No history recorded yet.</p><?php else: ?>
  <ul class="plain-list">
    <?php foreach ($recentItem['rows'] as $h): ?><li><small class="muted"><?= e(fmt_dt($h['changed_at'])) ?></small> · Item “<?= e($h['item_name']) ?>” <?= $h['old_status'] ? e(status_label($h['old_status'])) . ' → ' : '' ?><?= e(status_label($h['new_status'])) ?> by <?= e($h['actor_name'] !== null ? $h['actor_name'] : 'System') ?></li><?php endforeach; ?>
    <?php foreach ($recentClaim['rows'] as $h): ?><li><small class="muted"><?= e(fmt_dt($h['changed_at'])) ?></small> · Claim #<?= (int) $h['claim_id'] ?> “<?= e($h['item_name']) ?>” <?= $h['old_status'] ? e(status_label($h['old_status'])) . ' → ' : '' ?><?= e(status_label($h['new_status'])) ?> by <?= e($h['actor_name'] !== null ? $h['actor_name'] : 'System') ?></li><?php endforeach; ?>
  </ul><?php endif; ?>
</section>
<section class="panel"><h2>Shortcuts</h2><div class="actions">
  <a class="btn secondary" href="<?= e(url('admin/manage-users.php')) ?>">Manage users</a>
  <a class="btn secondary" href="<?= e(url('admin/system-settings.php')) ?>">System settings</a>
  <a class="btn secondary" href="<?= e(url('staff/handover.php')) ?>">Handover</a>
  <a class="btn secondary" href="<?= e(url('admin/reports.php')) ?>">Reports &amp; export</a></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
