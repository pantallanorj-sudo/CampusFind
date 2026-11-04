<?php
/** Operational reports + CSV export for staff and admins. Expects $me and $reportsPath. */
$types = ExportService::types();
$type = get_enum('type', array_keys($types), '');
$summary = ExportService::summary();
$categories = CategoryService::options();
$statusOptions = ($type !== '') ? ExportService::statusOptions($type) : null;
$f = array(
    'category_id' => get_int('category_id'),
    'status' => ($statusOptions !== null) ? get_enum('status', array_keys($statusOptions), '') : '',
    'date_from' => get_date('date_from'),
    'date_to' => get_date('date_to'),
);
$data = ($type !== '') ? ExportService::dataset($type, $f) : null;
$exportParams = array('type' => $type, 'category_id' => $f['category_id'] > 0 ? $f['category_id'] : '', 'status' => $f['status'], 'date_from' => $f['date_from'], 'date_to' => $f['date_to']);
$pageTitle = 'Reports & export';
require __DIR__ . '/../header.php';
?>
<div class="page-head"><div><h1>Reports &amp; export</h1><p>Live counts from the database. Every report can be downloaded as CSV with the filters you choose.</p></div></div>
<div class="tabs" role="tablist">
  <a class="tab<?= $type === '' ? ' active' : '' ?>" href="<?= e(url($reportsPath)) ?>">Overview</a>
  <?php foreach ($types as $k => $label): ?><a class="tab<?= $type === $k ? ' active' : '' ?>" href="<?= e(url($reportsPath . '?type=' . $k)) ?>"><?= e($label) ?></a><?php endforeach; ?>
</div>

<?php if ($type === ''): ?>
<div class="stats">
  <div class="stat"><span><?= (int) $summary['lost_total'] ?></span><small>Lost reports (<?= (int) $summary['lost_open'] ?> open)</small></div>
  <div class="stat"><span><?= (int) $summary['found_total'] ?></span><small>Found reports (<?= (int) $summary['found_open'] ?> open)</small></div>
  <div class="stat"><span><?= (int) $summary['claims_total'] ?></span><small>Claims in total</small></div>
  <div class="stat"><span><?= (int) $summary['returned'] ?></span><small>Items returned</small></div>
  <div class="stat"><span><?= (int) $summary['unclaimed_eligible'] ?></span><small>Unclaimed past retention</small></div>
  <div class="stat"><span><?= (int) $summary['closed'] ?></span><small>Closed reports</small></div>
  <div class="stat"><span><?= (int) $summary['archived'] ?></span><small>Archived reports</small></div>
</div>
<section class="panel"><h2>Claims by status</h2>
  <div class="table-wrap"><table class="table"><thead><tr><th>Status</th><th>Claims</th><th></th></tr></thead><tbody>
    <?php foreach ($summary['claims'] as $st => $n): ?><tr><td><?= badge($st) ?></td><td><?= (int) $n ?></td><td><a href="<?= e(url($reportsPath . '?type=claims&status=' . $st)) ?>">View list →</a></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php else: ?>
<form method="get" class="filters" action="<?= e(url($reportsPath)) ?>">
  <?= hidden_field('type', $type) ?>
  <label>Category<select name="category_id"><?= select_options($categories, $f['category_id'] > 0 ? $f['category_id'] : '', 'All categories') ?></select></label>
  <?php if ($statusOptions !== null): ?><label>Status<select name="status"><?= select_options($statusOptions, $f['status'], 'Any status') ?></select></label><?php endif; ?>
  <label><?= e(ExportService::dateLabel($type)) ?><input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label>and<input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <button class="btn primary" type="submit">Apply</button>
  <a class="btn ghost" href="<?= e(url($reportsPath . '?type=' . $type)) ?>">Clear filters</a>
</form>
<div class="page-head"><h2><?= e($data['title']) ?> <small class="muted">(<?= e(plural($data['total'], 'record')) ?>)</small></h2>
  <?php if ($data['total'] > 0): ?><a class="btn primary" href="<?= e(page_url('actions/export-reports.php', $exportParams)) ?>">Download CSV</a><?php endif; ?></div>
<?php if ($data['total'] === 0): ?><?= empty_state('No records match', 'There is nothing to show or export for these filters. Try widening the date range.') ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><?php foreach ($data['headers'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
  <tbody><?php foreach (array_slice($data['rows'], 0, 100) as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= e(text_cut((string) $cell, 80)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
</table></div>
<?php if ($data['total'] > 100): ?><p class="muted">Showing the first 100 of <?= (int) $data['total'] ?> records. The CSV contains all of them.</p><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../footer.php'; ?>
