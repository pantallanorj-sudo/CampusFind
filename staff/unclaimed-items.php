<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_staff();
$view = get_enum('view', array('eligible', 'approaching', 'archived'), 'eligible');
$retention = ReportArchiveService::retentionDays();
$eligibleCount = ReportArchiveService::eligibleCount();
$approaching = ReportArchiveService::approaching(7);
$archivedCount = db_count("SELECT COUNT(*) FROM item_reports WHERE status = 'archived'");
$eligible = ($view === 'eligible') ? ReportArchiveService::eligible(max(1, get_int('page', 1)), SettingsService::perPage()) : array('rows' => array(), 'pg' => paginate(0, 10, 1));
$pageTitle = 'Unclaimed items & archive';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Unclaimed items &amp; archive</h1>
  <p>Found items that nobody has claimed for <strong><?= (int) $retention ?> days</strong> (the retention period from System Settings<?= $me['role'] === 'admin' ? ', <a href="' . e(url('admin/system-settings.php')) . '">change it</a>' : '' ?>) can be archived. Age is counted from the day the item was reported.</p></div></div>
<div class="stats">
  <div class="stat"><span><?= (int) $eligibleCount ?></span><small>Eligible to archive</small></div>
  <div class="stat"><span><?= (int) count($approaching) ?></span><small>Eligible within 7 days</small></div>
  <div class="stat"><span><?= (int) $archivedCount ?></span><small>Archived reports</small></div>
</div>
<div class="tabs" role="tablist">
  <a class="tab<?= $view === 'eligible' ? ' active' : '' ?>" href="<?= e(url('staff/unclaimed-items.php?view=eligible')) ?>">Eligible now (<?= (int) $eligibleCount ?>)</a>
  <a class="tab<?= $view === 'approaching' ? ' active' : '' ?>" href="<?= e(url('staff/unclaimed-items.php?view=approaching')) ?>">Soon (<?= (int) count($approaching) ?>)</a>
  <a class="tab<?= $view === 'archived' ? ' active' : '' ?>" href="<?= e(url('staff/unclaimed-items.php?view=archived')) ?>">Archived (<?= (int) $archivedCount ?>)</a>
</div>

<?php if ($view === 'eligible'): ?>
  <?php if (count($eligible['rows']) === 0): ?><?= empty_state('Nothing to archive', 'No open found item is older than the retention period.') ?>
  <?php else: ?>
  <form method="post" action="<?= e(url('actions/archive-report.php')) ?>" data-confirm="Archive the selected reports? Their history is kept." data-select-all-form>
    <?= csrf_field() ?><?= hidden_field('return_to', 'staff/unclaimed-items.php?view=eligible') ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th><input type="checkbox" data-select-all aria-label="Select all"></th><th>Item</th><th>Category</th><th>Reported</th><th>Age</th><th>Status</th><th></th></tr></thead>
      <tbody><?php foreach ($eligible['rows'] as $r): ?><tr>
        <td><input type="checkbox" name="report_ids[]" value="<?= (int) $r['id'] ?>" aria-label="Select <?= e($r['item_name']) ?>"></td>
        <td><strong><?= e($r['item_name']) ?></strong><br><small class="muted"><?= e($r['location']) ?></small></td>
        <td><?= e($r['category_name']) ?></td><td><?= e(fmt_dt($r['created_at'], false)) ?></td>
        <td><?= e(plural(days_since($r['created_at']), 'day')) ?></td><td><?= badge($r['status']) ?></td>
        <td><a class="btn small ghost" href="<?= e(url('staff/manage-items.php?id=' . (int) $r['id'])) ?>">Open</a></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
    <div class="actions"><button class="btn primary" type="submit">Archive selected</button>
      <button class="btn secondary" type="submit" name="archive_all" value="1" data-confirm="Archive ALL <?= (int) $eligibleCount ?> eligible reports?">Archive all eligible (<?= (int) $eligibleCount ?>)</button></div>
  </form>
  <?= pager_html($eligible['pg'], 'staff/unclaimed-items.php', array('view' => 'eligible')) ?>
  <?php endif; ?>
<?php elseif ($view === 'approaching'): ?>
  <?php if (count($approaching) === 0): ?><?= empty_state('Nothing due soon', 'No found item reaches the retention limit within the next 7 days.') ?>
  <?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th>Category</th><th>Reported</th><th>Becomes eligible</th></tr></thead><tbody>
    <?php foreach ($approaching as $r): ?><tr><td><a href="<?= e(url('staff/manage-items.php?id=' . (int) $r['id'])) ?>"><?= e($r['item_name']) ?></a></td><td><?= e($r['category_name']) ?></td><td><?= e(fmt_dt($r['created_at'], false)) ?></td><td><?= e(fmt_dt(ReportArchiveService::eligibleOn($r['created_at']), false)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
<?php else: ?>
  <?php $archivedPath = 'staff/unclaimed-items.php'; $archivedExtra = array('view' => 'archived'); require __DIR__ . '/../includes/views/archived-table.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
