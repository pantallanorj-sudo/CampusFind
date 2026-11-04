<?php
/** Claim list for staff and admins. Expects $me and $queuePath (the page that includes this view). */
$statusChoices = array_merge(array('active' => 'Open queue (pending, review, needs info)', 'all' => 'All statuses'), status_choices(CLAIM_STATUSES));
$status = get_enum('status', array_keys($statusChoices), 'active');
$f = array(
    'status' => $status === 'all' ? '' : $status,
    'q' => get_str('q', 80),
    'category_id' => get_int('category_id'),
    'date_from' => get_date('date_from'),
    'date_to' => get_date('date_to'),
    'sort' => get_enum('sort', array('newest', 'oldest'), 'oldest'),
);
$result = ClaimService::queue($f, max(1, get_int('page', 1)), SettingsService::perPage());
$rows = $result['rows'];
$pg = $result['pg'];
$categories = CategoryService::options();
$filtered = ($status !== 'active' || $f['q'] !== '' || $f['category_id'] > 0 || $f['date_from'] !== '' || $f['date_to'] !== '' || $f['sort'] !== 'oldest');
$params = array('status' => $status, 'q' => $f['q'], 'category_id' => $f['category_id'] > 0 ? $f['category_id'] : '', 'date_from' => $f['date_from'], 'date_to' => $f['date_to'], 'sort' => $f['sort']);
$pageTitle = 'Claims';
require __DIR__ . '/../header.php';
?>
<div class="page-head"><div><h1>Claims</h1><p>Review ownership claims. Open a claim to inspect evidence, request more information, approve or reject.</p></div></div>
<form method="get" class="filters" action="<?= e(url($queuePath)) ?>">
  <label class="grow">Search<input type="search" name="q" maxlength="80" value="<?= e($f['q']) ?>" placeholder="Item or claimant name"></label>
  <label>Status<select name="status"><?= select_options($statusChoices, $status) ?></select></label>
  <label>Category<select name="category_id"><?= select_options($categories, $f['category_id'] > 0 ? $f['category_id'] : '', 'All categories') ?></select></label>
  <label>Submitted from<input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label>to<input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <label>Order<select name="sort"><?= select_options(array('oldest' => 'Oldest first', 'newest' => 'Newest first'), $f['sort']) ?></select></label>
  <button class="btn primary" type="submit">Apply</button>
  <?php if ($filtered): ?><a class="btn ghost" href="<?= e(url($queuePath)) ?>">Clear filters</a><?php endif; ?>
</form>
<?php if (count($rows) === 0): ?>
  <?= empty_state('No claims match', $filtered ? 'Try different filters.' : 'There are no claims waiting for review.') ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>#</th><th>Item</th><th>Claimant</th><th>Submitted</th><th>Claim</th><th>Item</th><th>Evidence</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int) $r['id'] ?></td>
      <td><strong><?= e($r['item_name']) ?></strong><br><small class="muted"><?= e($r['category_name']) ?></small></td>
      <td><?= e($r['claimant_name']) ?></td>
      <td><?= e(fmt_dt($r['created_at'])) ?></td>
      <td><?= badge($r['status']) ?></td>
      <td><?= badge($r['report_status']) ?></td>
      <td><?= (int) $r['evidence_count'] ?></td>
      <td><a class="btn small primary" href="<?= e(url('staff/verify-claims.php?id=' . (int) $r['id'])) ?>">Review</a></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?= pager_html($pg, $queuePath, $params) ?>
<?php endif; ?>
<?php require __DIR__ . '/../footer.php'; ?>
