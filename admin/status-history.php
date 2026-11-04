<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$kind = get_enum('kind', array('item', 'claim'), 'item');
$statuses = ($kind === 'claim') ? CLAIM_STATUSES : REPORT_STATUSES;
$f = array(
    'q' => get_str('q', 80),
    'status' => get_enum('status', $statuses, ''),
    'actor' => get_int('actor'),
    'subject_id' => get_int('subject_id'),
    'date_from' => get_date('date_from'),
    'date_to' => get_date('date_to'),
);
$result = HistoryService::feed($kind, $f, max(1, get_int('page', 1)), max(20, SettingsService::perPage()));
$table = ($kind === 'claim') ? 'claim_status_history' : 'status_history';
$actors = array();
foreach (db_all('SELECT DISTINCT u.id, u.full_name FROM users u JOIN ' . $table . ' h ON h.changed_by = u.id ORDER BY u.full_name COLLATE NOCASE') as $a) {
    $actors[(int) $a['id']] = $a['full_name'];
}
$params = array('kind' => $kind, 'q' => $f['q'], 'status' => $f['status'], 'actor' => $f['actor'] > 0 ? $f['actor'] : '', 'subject_id' => $f['subject_id'] > 0 ? $f['subject_id'] : '', 'date_from' => $f['date_from'], 'date_to' => $f['date_to']);
$filtered = ($f['q'] !== '' || $f['status'] !== '' || $f['actor'] > 0 || $f['subject_id'] > 0 || $f['date_from'] !== '' || $f['date_to'] !== '');
$pageTitle = 'Status history';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Status history &amp; audit trail</h1><p>Every important status change is recorded with who made it and when. These records are append-only: the database blocks edits and deletions.</p></div></div>
<div class="tabs" role="tablist">
  <a class="tab<?= $kind === 'item' ? ' active' : '' ?>" href="<?= e(url('admin/status-history.php?kind=item')) ?>">Item reports</a>
  <a class="tab<?= $kind === 'claim' ? ' active' : '' ?>" href="<?= e(url('admin/status-history.php?kind=claim')) ?>">Claims</a>
</div>
<form method="get" class="filters" action="<?= e(url('admin/status-history.php')) ?>">
  <?= hidden_field('kind', $kind) ?>
  <label class="grow">Item name<input type="search" name="q" maxlength="80" value="<?= e($f['q']) ?>"></label>
  <label><?= $kind === 'claim' ? 'Claim #' : 'Report #' ?><input type="number" name="subject_id" min="1" value="<?= e($f['subject_id'] > 0 ? $f['subject_id'] : '') ?>"></label>
  <label>New status<select name="status"><?= select_options(status_choices($statuses), $f['status'], 'Any') ?></select></label>
  <label>Changed by<select name="actor"><?= select_options($actors, $f['actor'] > 0 ? $f['actor'] : '', 'Anyone') ?></select></label>
  <label>From<input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label>To<input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <button class="btn primary" type="submit">Filter</button>
  <?php if ($filtered): ?><a class="btn ghost" href="<?= e(url('admin/status-history.php?kind=' . $kind)) ?>">Clear filters</a><?php endif; ?>
</form>
<?php if (count($result['rows']) === 0): ?><?= empty_state('No history entries', $filtered ? 'No entries match these filters.' : 'History appears here as reports and claims change.') ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>When (Manila)</th><th><?= $kind === 'claim' ? 'Claim' : 'Report' ?></th><th>Change</th><th>By</th><th>Notes</th></tr></thead>
  <tbody><?php foreach ($result['rows'] as $h): ?><tr>
    <td><?= e(fmt_dt($h['changed_at'])) ?></td>
    <td><?php if ($kind === 'claim'): ?><a href="<?= e(url('staff/verify-claims.php?id=' . (int) $h['claim_id'])) ?>">Claim #<?= (int) $h['claim_id'] ?></a><?php else: ?><a href="<?= e(url('admin/manage-reports.php?id=' . (int) $h['report_id'])) ?>">#<?= (int) $h['report_id'] ?></a><?php endif; ?><br><small class="muted"><?= e($h['item_name']) ?></small></td>
    <td><?php if ($h['old_status'] !== null && $h['old_status'] === $h['new_status']): ?><span class="muted">Details edited</span><?php else: ?><?= $h['old_status'] !== null ? badge($h['old_status']) . ' → ' : '' ?><?= badge($h['new_status']) ?><?php endif; ?></td>
    <td><?= e($h['actor_name'] !== null ? $h['actor_name'] : 'System') ?><?= $h['actor_role'] ? ' <small class="muted">(' . e($h['actor_role']) . ')</small>' : '' ?></td>
    <td><?= e($h['notes']) ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?= pager_html($result['pg'], 'admin/status-history.php', $params) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
