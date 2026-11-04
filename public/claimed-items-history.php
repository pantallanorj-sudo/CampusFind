<?php
/**
 * PUBLIC page (no login required, same as the original project): items that were really handed back.
 * Only sanitized fields are shown: name, category, return date, and a photo (never for ID/card items).
 * Never shown: claimant, evidence, claim text, staff notes, user ids, locations or descriptions.
 */
require_once __DIR__ . '/../includes/auth.php';
$q = get_str('q', 80);
$categoryId = get_int('category_id');
$page = max(1, get_int('page', 1));

$where = array("q.status = 'returned'", "r.status = 'returned'");
$params = array();
if ($q !== '') {
    $where[] = "r.item_name LIKE ? ESCAPE '!'";
    $params[] = like_pattern($q);
}
if ($categoryId > 0) {
    $where[] = 'r.category_id = ?';
    $params[] = $categoryId;
}
$from = 'FROM handover_records h JOIN claim_requests q ON q.id = h.claim_id JOIN item_reports r ON r.id = q.item_report_id '
    . 'JOIN categories c ON c.id = r.category_id WHERE ' . implode(' AND ', $where);
$total = db_count('SELECT COUNT(*) ' . $from, $params);
$pg = paginate($total, SettingsService::perPage(), $page);
$rows = db_all('SELECT r.item_name, r.image_path, c.name AS category_name, h.handed_over_at ' . $from
    . ' ORDER BY h.handed_over_at DESC, h.id DESC LIMIT ' . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'], $params);
$categories = CategoryService::options();
$pageTitle = 'Returned items history';
require __DIR__ . '/../includes/header.php';
?>
<h1>Returned items history</h1>
<p>This public list only shows items whose physical handover was confirmed by staff. Claimant names, claim details and private evidence are never displayed.</p>
<form method="get" class="filters" action="<?= e(url('public/claimed-items-history.php')) ?>">
  <label>Search<input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Item name"></label>
  <label>Category<select name="category_id"><?= select_options($categories, $categoryId > 0 ? $categoryId : '', 'All categories') ?></select></label>
  <button class="btn primary" type="submit">Search</button>
  <?php if ($q !== '' || $categoryId > 0): ?><a class="btn ghost" href="<?= e(url('public/claimed-items-history.php')) ?>">Clear</a><?php endif; ?>
</form>
<div class="cards">
  <?php if (count($rows) === 0): ?>
    <div class="empty">No returned items to display yet.</div>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <?php $sensitive = (strtolower(trim($r['category_name'])) === 'id / cards'); ?>
      <article class="item-card">
        <?= $sensitive ? '<div class="card-photo placeholder" aria-hidden="true">ID</div>' : item_photo_html($r) ?>
        <div class="card-body">
          <div class="card-tags"><span class="pill returned">Returned</span></div>
          <h3><?= e($sensitive ? 'ID / card (details withheld)' : $r['item_name']) ?></h3>
          <p class="card-meta"><?= e($r['category_name']) ?></p>
          <small>Returned <?= e(fmt_dt($r['handed_over_at'])) ?></small>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?= pager_html($pg, 'public/claimed-items-history.php', array('q' => $q, 'category_id' => $categoryId > 0 ? $categoryId : '')) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
