<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$cats = CategoryService::all();
$old = take_old();
$pageTitle = 'Manage categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Categories</h1><p>Names must be unique (not case-sensitive). A category that reports already use cannot be deleted — rename it instead.</p></div></div>
<section class="panel"><h2>Add a category</h2>
  <form method="post" action="<?= e(url('actions/save-category.php')) ?>" class="form-grid" data-submit-guard><?= csrf_field() ?>
    <label>Name<input name="name" maxlength="60" required value="<?= e(ov($old, 'name')) ?>"></label>
    <label>Description (optional)<input name="description" maxlength="200" value="<?= e(ov($old, 'description')) ?>"></label>
    <div class="form-actions"><button class="btn primary" type="submit">Add category</button></div>
  </form>
</section>
<section class="panel"><h2>All categories (<?= count($cats) ?>)</h2>
<?php if (count($cats) === 0): ?><?= empty_state('No categories', 'Add at least one category so reports can be created.') ?>
<?php else: ?>
  <div class="cat-list">
  <?php foreach ($cats as $c): ?>
    <div class="cat-row">
      <form method="post" action="<?= e(url('actions/update-category.php')) ?>" class="cat-edit">
        <?= csrf_field() ?><?= hidden_field('category_id', $c['id']) ?>
        <label class="sr-only" for="cn-<?= (int) $c['id'] ?>">Name</label><input id="cn-<?= (int) $c['id'] ?>" name="name" maxlength="60" required value="<?= e($c['name']) ?>">
        <label class="sr-only" for="cd-<?= (int) $c['id'] ?>">Description</label><input id="cd-<?= (int) $c['id'] ?>" name="description" maxlength="200" value="<?= e($c['description']) ?>" placeholder="Description">
        <button class="btn small secondary" type="submit">Save</button>
      </form>
      <span class="muted"><?= e(plural($c['report_count'], 'report')) ?></span>
      <?php if ((int) $c['report_count'] === 0): ?>
        <form method="post" action="<?= e(url('actions/delete-category.php')) ?>" class="inline-form" data-confirm="Delete the category “<?= e($c['name']) ?>”?"><?= csrf_field() ?><?= hidden_field('category_id', $c['id']) ?><button class="btn small danger" type="submit">Delete</button></form>
      <?php else: ?><span class="muted" title="Categories used by reports cannot be deleted">In use</span><?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
