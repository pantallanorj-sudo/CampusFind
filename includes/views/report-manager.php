<?php
/** Item report management (list + detail). Used by staff/manage-items.php and admin/manage-reports.php. Expects $me and $managePath. */
$id = get_int('id');
$report = $id > 0 ? ReportService::find($id) : null;
if ($id > 0 && !$report) {
    abort_page(404, 'Report not found', 'That report does not exist.');
}
if ($report) {
    $transitions = ReportService::manualTransitions($report);
    $canEdit = report_edit_check($me, $report);
    $archiveCheck = ReportArchiveService::check($report);
    $claims = ReportService::claimsFor($report['id']);
    $history = ReportService::history($report['id']);
    $dups = DuplicateDetectionService::find($report['report_type'], $report['category_id'], $report['item_name'], $report['location'], $report['date_occurred'], $report['id']);
    $archivedBy = ($report['archived_by'] !== null) ? db_scalar('SELECT full_name FROM users WHERE id = ?', array((int) $report['archived_by'])) : null;
    $photo = upload_url($report['image_path']);
    $pageTitle = 'Manage: ' . $report['item_name'];
    $breadcrumbs = array(array('Item reports', $managePath), array($report['item_name'], null));
} else {
    $f = array(
        'scope' => 'staff',
        'q' => get_str('q', 80),
        'type' => get_enum('type', array('lost', 'found'), ''),
        'category_id' => get_int('category_id'),
        'status' => get_enum('status', REPORT_STATUSES, ''),
        'date_from' => get_date('date_from'),
        'date_to' => get_date('date_to'),
        'sort' => get_enum('sort', array('newest', 'oldest', 'name', 'date'), 'newest'),
    );
    $result = ReportService::search($f, max(1, get_int('page', 1)), SettingsService::perPage());
    $rows = $result['rows'];
    $pg = $result['pg'];
    $categories = CategoryService::options();
    $filtered = ($f['q'] !== '' || $f['type'] !== '' || $f['category_id'] > 0 || $f['status'] !== '' || $f['date_from'] !== '' || $f['date_to'] !== '');
    $params = $f;
    unset($params['scope']);
    $params['category_id'] = $f['category_id'] > 0 ? $f['category_id'] : '';
    $pageTitle = 'Item reports';
}
require __DIR__ . '/../header.php';
?>
<?php if ($report): ?>
<div class="page-head"><div><p class="eyebrow">REPORT #<?= (int) $report['id'] ?></p><h1><?= e($report['item_name']) ?></h1></div><div><?= type_pill($report['report_type']) ?> <?= badge($report['status']) ?></div></div>
<div class="grid-2">
  <section class="panel"><h2>Details</h2>
    <?php if ($photo !== ''): ?><img class="thumb" src="<?= e($photo) ?>" alt="Photo of <?= e($report['item_name']) ?>"><?php endif; ?>
    <dl class="kv">
      <div><dt>Category</dt><dd><?= e($report['category_name']) ?></dd></div>
      <div><dt>Location</dt><dd><?= e($report['location']) ?></dd></div>
      <div><dt>Date <?= e($report['report_type']) ?></dt><dd><?= e(fmt_date($report['date_occurred'])) ?></dd></div>
      <div><dt>Reported</dt><dd><?= e(fmt_dt($report['created_at'])) ?></dd></div>
      <div><dt>Last updated</dt><dd><?= e(fmt_dt($report['updated_at'])) ?></dd></div>
      <div><dt>Reported by</dt><dd><?= e($report['reporter_name']) ?> (<?= e($report['reporter_email']) ?>)</dd></div>
      <?php if ($report['status'] === 'archived'): ?><div><dt>Archived</dt><dd><?= e(fmt_dt($report['archived_at'])) ?> by <?= e($archivedBy !== null ? $archivedBy : 'unknown') ?></dd></div><?php endif; ?>
    </dl>
    <h3>Description</h3><p><?= nl2br_e($report['description']) ?></p>
    <?php if ($canEdit[0]): ?><a class="btn secondary" href="<?= e(url('student/edit-report.php?id=' . (int) $report['id'])) ?>">Correct details</a><?php endif; ?>
  </section>
  <section class="panel"><h2>Actions</h2>
    <?php if (count($transitions) > 0): ?>
    <form method="post" action="<?= e(url('actions/update-report.php')) ?>" data-submit-guard>
      <?= csrf_field() ?><?= hidden_field('mode', 'status') ?><?= hidden_field('report_id', $report['id']) ?><?= hidden_field('return_to', $managePath . '?id=' . (int) $report['id']) ?>
      <label>Change status to<select name="new_status" required><?= select_options(status_choices($transitions), '', 'Choose…') ?></select></label>
      <label>Reason / notes<textarea name="notes" rows="2" maxlength="500" placeholder="Required when closing a report"></textarea></label>
      <button class="btn primary" type="submit">Update status</button>
    </form>
    <?php else: ?><p class="muted">No manual status change is available for a <?= e(strtolower(status_label($report['status']))) ?> report<?= $report['status'] === 'claim_pending' ? ' with an active claim' : '' ?>.</p><?php endif; ?>
    <hr>
    <h3>Archive</h3>
    <?php if ($archiveCheck[0]): ?>
    <form method="post" action="<?= e(url('actions/archive-report.php')) ?>" data-confirm="Archive this report? It will leave the active lists; its history is kept.">
      <?= csrf_field() ?><?= hidden_field('report_id', $report['id']) ?><?= hidden_field('return_to', $managePath . '?id=' . (int) $report['id']) ?>
      <label>Notes (optional)<input name="notes" maxlength="500"></label>
      <button class="btn secondary" type="submit">Archive report</button>
    </form>
    <?php else: ?><p class="muted"><?= e($archiveCheck[1]) ?></p><?php endif; ?>
  </section>
</div>
<?php if (count($dups) > 0): ?><section class="panel"><h2>Possible duplicates to review</h2><ul class="plain-list"><?php foreach ($dups as $d): ?>
  <li><a href="<?= e(url($managePath . '?id=' . (int) $d['id'])) ?>"><?= e($d['item_name']) ?></a> — <?= e($d['location']) ?> · <?= e(fmt_date($d['date_occurred'])) ?> <?= badge($d['status']) ?> <small class="muted">similarity <?= (int) $d['score'] ?>%</small></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php if (count($claims) > 0): ?><section class="panel"><h2>Claims</h2><ul class="plain-list"><?php foreach ($claims as $c): ?>
  <li><a href="<?= e(url('staff/verify-claims.php?id=' . (int) $c['id'])) ?>">Claim #<?= (int) $c['id'] ?></a> by <?= e($c['claimant_name']) ?> <?= badge($c['status']) ?> <small class="muted"><?= e(fmt_dt($c['created_at'])) ?></small></li><?php endforeach; ?></ul></section><?php endif; ?>
<section class="panel"><h2>Status history</h2><?= timeline_html($history) ?></section>

<?php else: ?>
<div class="page-head"><div><h1>Item reports</h1><p>All lost and found reports. Archived reports are listed separately.</p></div></div>
<form method="get" class="filters" action="<?= e(url($managePath)) ?>">
  <label class="grow">Search<input type="search" name="q" maxlength="80" value="<?= e($f['q']) ?>" placeholder="Name, description, location…"></label>
  <label>Type<select name="type"><?= select_options(array('lost' => 'Lost', 'found' => 'Found'), $f['type'], 'Lost & found') ?></select></label>
  <label>Category<select name="category_id"><?= select_options($categories, $f['category_id'] > 0 ? $f['category_id'] : '', 'All categories') ?></select></label>
  <label>Status<select name="status"><?= select_options(status_choices(REPORT_STATUSES), $f['status'], 'All (not archived)') ?></select></label>
  <label>From<input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label>To<input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <label>Sort<select name="sort"><?= select_options(array('newest' => 'Newest reported', 'oldest' => 'Oldest reported', 'date' => 'Date lost/found', 'name' => 'Name A–Z'), $f['sort']) ?></select></label>
  <button class="btn primary" type="submit">Apply</button>
  <?php if ($filtered): ?><a class="btn ghost" href="<?= e(url($managePath)) ?>">Clear filters</a><?php endif; ?>
</form>
<?php if (count($rows) === 0): ?><?= empty_state('No reports match', 'Try different filters.') ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Reporter</th><th>Reported</th><th>Status</th><th>Active claims</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?><tr>
    <td><?= (int) $r['id'] ?></td>
    <td><strong><?= e($r['item_name']) ?></strong> <?= type_pill($r['report_type']) ?><br><small class="muted"><?= e($r['location']) ?></small></td>
    <td><?= e($r['category_name']) ?></td><td><?= e($r['reporter_name']) ?></td><td><?= e(fmt_dt($r['created_at'], false)) ?></td>
    <td><?= badge($r['status']) ?></td><td><?= (int) $r['active_claims'] ?></td>
    <td><a class="btn small primary" href="<?= e(url($managePath . '?id=' . (int) $r['id'])) ?>">Manage</a></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?= pager_html($pg, $managePath, $params) ?>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../footer.php'; ?>
