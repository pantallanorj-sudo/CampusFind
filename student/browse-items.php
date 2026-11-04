<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_login();
$filters = array(
    'q' => get_str('q', 80),
    'type' => get_enum('type', array('lost', 'found'), ''),
    'category_id' => get_int('category_id'),
    'status' => get_enum('status', array('open', 'claim_pending'), ''),
    'location' => get_str('location', 80),
    'date_from' => get_date('date_from'),
    'date_to' => get_date('date_to'),
    'sort' => get_enum('sort', array('newest', 'oldest', 'name', 'date'), 'newest'),
);
$page = max(1, get_int('page', 1));
$result = ReportService::search(array_merge($filters, array('scope' => 'browse')), $page, SettingsService::perPage());
$rows = $result['rows'];
$pg = $result['pg'];
$categories = CategoryService::options();
$active = ($filters['q'] !== '' || $filters['type'] !== '' || $filters['category_id'] > 0 || $filters['status'] !== '' || $filters['location'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '');
$queryParams = $filters;
$queryParams['category_id'] = $filters['category_id'] > 0 ? $filters['category_id'] : '';
$pageTitle = 'Browse items';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Browse items</h1><p>Active lost and found reports. Open an item to see details or submit a claim.</p></div>
  <a class="btn primary" href="<?= e(url('student/report-item.php')) ?>">Report an item</a></div>
<form method="get" class="filters" action="<?= e(url('student/browse-items.php')) ?>">
  <label class="grow">Keyword<input type="search" name="q" maxlength="80" value="<?= e($filters['q']) ?>" placeholder="Name, description, location…"></label>
  <label>Type<select name="type"><?= select_options(array('lost' => 'Lost', 'found' => 'Found'), $filters['type'], 'Lost & found') ?></select></label>
  <label>Category<select name="category_id"><?= select_options($categories, $filters['category_id'] > 0 ? $filters['category_id'] : '', 'All categories') ?></select></label>
  <label>Status<select name="status"><?= select_options(array('open' => 'Open', 'claim_pending' => 'Claim pending'), $filters['status'], 'Any status') ?></select></label>
  <label>Location<input name="location" maxlength="80" value="<?= e($filters['location']) ?>" placeholder="e.g. Library"></label>
  <label>From<input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label>
  <label>To<input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label>
  <label>Sort<select name="sort"><?= select_options(array('newest' => 'Newest reported', 'oldest' => 'Oldest reported', 'date' => 'Date lost/found', 'name' => 'Name A–Z'), $filters['sort']) ?></select></label>
  <button class="btn primary" type="submit">Search</button>
  <?php if ($active): ?><a class="btn ghost" href="<?= e(url('student/browse-items.php')) ?>">Clear filters</a><?php endif; ?>
</form>
<p class="muted"><?= e(plural($pg['total'], 'item')) ?> found</p>
<div class="cards">
  <?php if (count($rows) === 0): ?>
    <div class="empty">No items match your search. Try fewer filters, or <a href="<?= e(url('student/report-item.php')) ?>">report an item</a>.</div>
  <?php else: ?>
    <?php foreach ($rows as $r): ?><?= item_card_html($r) ?><?php endforeach; ?>
  <?php endif; ?>
</div>
<?= pager_html($pg, 'student/browse-items.php', $queryParams) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
