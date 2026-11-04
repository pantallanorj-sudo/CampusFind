<?php
/** Archived reports filter + table (a fragment: the including page prints the header/footer). Expects $archivedPath. */
$af = array('q' => get_str('q', 80), 'category_id' => get_int('category_id'), 'date_from' => get_date('date_from'), 'date_to' => get_date('date_to'));
$aResult = ReportArchiveService::listArchived($af, max(1, get_int('page', 1)), SettingsService::perPage());
$aParams = $af;
$aParams['category_id'] = $af['category_id'] > 0 ? $af['category_id'] : '';
if (isset($archivedExtra)) {
    $aParams = array_merge($aParams, $archivedExtra);
}
$aCategories = CategoryService::options();
?>
<form method="get" class="filters" action="<?= e(url($archivedPath)) ?>">
  <?php if (isset($archivedExtra)): foreach ($archivedExtra as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; endif; ?>
  <label class="grow">Search<input type="search" name="q" maxlength="80" value="<?= e($af['q']) ?>" placeholder="Item or location"></label>
  <label>Category<select name="category_id"><?= select_options($aCategories, $af['category_id'] > 0 ? $af['category_id'] : '', 'All categories') ?></select></label>
  <label>Archived from<input type="date" name="date_from" value="<?= e($af['date_from']) ?>"></label>
  <label>to<input type="date" name="date_to" value="<?= e($af['date_to']) ?>"></label>
  <button class="btn primary" type="submit">Filter</button>
  <a class="btn ghost" href="<?= e(url($archivedPath . (isset($archivedExtra) ? '?' . build_query($archivedExtra) : ''))) ?>">Clear</a>
</form>
<?php if (count($aResult['rows']) === 0): ?><?= empty_state('No archived reports', 'Archived reports appear here. They stay in the database for audit and claim history.') ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Reported</th><th>Archived</th><th>Archived by</th></tr></thead>
  <tbody><?php foreach ($aResult['rows'] as $r): ?><tr>
    <td><?= (int) $r['id'] ?></td><td><a href="<?= e(url('staff/manage-items.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['item_name']) ?></strong></a> <?= type_pill($r['report_type']) ?><br><small class="muted"><?= e($r['location']) ?></small></td>
    <td><?= e($r['category_name']) ?></td><td><?= e(fmt_dt($r['created_at'], false)) ?></td><td><?= e(fmt_dt($r['archived_at'])) ?></td><td><?= e($r['archived_by_name'] !== null ? $r['archived_by_name'] : '—') ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div>
<?= pager_html($aResult['pg'], $archivedPath, $aParams) ?>
<?php endif; ?>
