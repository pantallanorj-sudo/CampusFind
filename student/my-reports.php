<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_login();
$status = get_enum('status', REPORT_STATUSES, '');
$type = get_enum('type', array('lost', 'found'), '');
$page = max(1, get_int('page', 1));
$result = ReportService::search(array('scope' => 'mine', 'user_id' => $me['id'], 'status' => $status, 'type' => $type), $page, SettingsService::perPage());
$rows = $result['rows'];
$pg = $result['pg'];
$pageTitle = 'My reports';
$breadcrumbs = array(array('Dashboard', home_path($me['role'])), array('My reports', null));
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>My reports</h1><p>Reports you submitted. You can edit or delete a report while it is open and has no claim.</p></div>
  <a class="btn primary" href="<?= e(url('student/report-item.php')) ?>">Report an item</a></div>
<form method="get" class="filters" action="<?= e(url('student/my-reports.php')) ?>">
  <label>Type<select name="type"><?= select_options(array('lost' => 'Lost', 'found' => 'Found'), $type, 'All types') ?></select></label>
  <label>Status<select name="status"><?= select_options(status_choices(REPORT_STATUSES), $status, 'All statuses') ?></select></label>
  <button class="btn primary" type="submit">Filter</button>
  <?php if ($status !== '' || $type !== ''): ?><a class="btn ghost" href="<?= e(url('student/my-reports.php')) ?>">Clear filters</a><?php endif; ?>
</form>
<?php if (count($rows) === 0): ?>
  <?= empty_state('No reports found', $status !== '' || $type !== '' ? 'No reports match these filters.' : 'You have not reported anything yet.', '<a class="btn primary small" href="' . e(url('student/report-item.php')) . '">Report an item</a>') ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Item</th><th>Category</th><th>Date</th><th>Status</th><th>Claims</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <?php $canEdit = report_edit_check($me, $r); $canDelete = report_withdraw_check($me, $r); ?>
    <tr>
      <td><a href="<?= e(url('student/item-details.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['item_name']) ?></strong></a> <?= type_pill($r['report_type']) ?><br><small class="muted"><?= e($r['location']) ?></small></td>
      <td><?= e($r['category_name']) ?></td>
      <td><?= e(fmt_date($r['date_occurred'])) ?></td>
      <td><?= badge($r['status']) ?></td>
      <td><?= (int) $r['active_claims'] ?></td>
      <td class="table-actions">
        <a class="btn small ghost" href="<?= e(url('student/item-details.php?id=' . (int) $r['id'])) ?>">View</a>
        <?php if ($canEdit[0]): ?><a class="btn small secondary" href="<?= e(url('student/edit-report.php?id=' . (int) $r['id'])) ?>">Edit</a><?php endif; ?>
        <?php if ($canDelete[0]): ?>
          <form method="post" action="<?= e(url('actions/delete-report.php')) ?>" class="inline-form" data-confirm="Delete this report? It will be removed from the listings and kept as a closed record.">
            <?= csrf_field() ?><?= hidden_field('report_id', $r['id']) ?><?= hidden_field('return_to', 'student/my-reports.php') ?>
            <button class="btn small danger" type="submit">Delete</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= pager_html($pg, 'student/my-reports.php', array('status' => $status, 'type' => $type)) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
