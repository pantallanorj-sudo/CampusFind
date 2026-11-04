<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_login();
$id = get_int('id');
$report = $id > 0 ? ReportService::find($id) : null;
if (!$report || !report_view_allowed($me, $report)) {
    abort_page(404, 'Item not found', 'That item does not exist, or it is no longer available to view.');
}
$isStaff = is_staff_user($me);
$isOwner = ((int) $report['user_id'] === (int) $me['id']);
$canEdit = report_edit_check($me, $report);
$canDelete = report_withdraw_check($me, $report);
$elig = ($me['role'] === 'student') ? ClaimService::eligibility($me, $report) : array(false, '');
$myClaim = ($me['role'] === 'student') ? db_one('SELECT id, status FROM claim_requests WHERE item_report_id = ? AND claimant_id = ?', array((int) $report['id'], (int) $me['id'])) : null;
$history = ($isStaff || $isOwner) ? ReportService::history($report['id']) : array();
$claims = $isStaff ? ReportService::claimsFor($report['id']) : array();
$photo = upload_url($report['image_path']);
$managePath = ($me['role'] === 'admin') ? 'admin/manage-reports.php' : 'staff/manage-items.php';
$pageTitle = $report['item_name'];
$breadcrumbs = array(array('Browse items', 'student/browse-items.php'), array($report['item_name'], null));
require __DIR__ . '/../includes/header.php';
?>
<article class="detail">
  <div class="detail-media">
    <?php if ($photo !== ''): ?><img class="detail-photo" src="<?= e($photo) ?>" alt="Photo of <?= e($report['item_name']) ?>"><?php else: ?><?= item_photo_html($report, 'detail-photo') ?><?php endif; ?>
  </div>
  <div class="detail-body">
    <div class="card-tags"><?= type_pill($report['report_type']) ?> <?= badge($report['status']) ?></div>
    <h1><?= e($report['item_name']) ?></h1>
    <dl class="kv">
      <div><dt>Category</dt><dd><?= e($report['category_name']) ?></dd></div>
      <div><dt><?= $report['report_type'] === 'lost' ? 'Lost' : 'Found' ?> at</dt><dd><?= e($report['location']) ?></dd></div>
      <div><dt>Date</dt><dd><?= e(fmt_date($report['date_occurred'])) ?></dd></div>
      <div><dt>Reported</dt><dd><?= e(fmt_dt($report['created_at'])) ?></dd></div>
      <?php if ($isStaff): ?><div><dt>Reported by</dt><dd><?= e($report['reporter_name']) ?> (<?= e($report['reporter_email']) ?>)</dd></div>
      <?php elseif ($isOwner): ?><div><dt>Reported by</dt><dd>You</dd></div><?php endif; ?>
    </dl>
    <h2>Description</h2>
    <p><?= nl2br_e($report['description']) ?></p>

    <div class="actions">
      <?php if ($elig[0]): ?><a class="btn primary" href="<?= e(url('student/submit-claim.php?item_id=' . (int) $report['id'])) ?>">This is mine — submit a claim</a>
      <?php elseif ($myClaim): ?><a class="btn secondary" href="<?= e(url('student/my-claims.php?id=' . (int) $myClaim['id'])) ?>">View my claim (<?= e(status_label($myClaim['status'])) ?>)</a><?php endif; ?>
      <?php if ($canEdit[0]): ?><a class="btn secondary" href="<?= e(url('student/edit-report.php?id=' . (int) $report['id'])) ?>">Edit<?= $isOwner ? '' : ' details' ?></a><?php endif; ?>
      <?php if ($isStaff): ?><a class="btn secondary" href="<?= e(url($managePath . '?id=' . (int) $report['id'])) ?>">Manage this item</a><?php endif; ?>
      <?php if ($canDelete[0]): ?>
        <form method="post" action="<?= e(url('actions/delete-report.php')) ?>" class="inline-form" data-confirm="Delete this report? It will be removed from the listings and kept as a closed record.">
          <?= csrf_field() ?><?= hidden_field('report_id', $report['id']) ?><?= hidden_field('return_to', 'student/my-reports.php') ?><button class="btn danger" type="submit">Delete report</button>
        </form>
      <?php endif; ?>
    </div>
    <?php if ($me['role'] === 'student' && !$elig[0] && !$myClaim && !$isOwner && $report['report_type'] === 'found' && $elig[1] !== ''): ?><p class="notice"><?= e($elig[1]) ?></p>
    <?php elseif ($me['role'] === 'student' && $report['report_type'] === 'lost' && !$isOwner): ?><p class="notice">This is a lost-item report. If you found this item, please <a href="<?= e(url('student/report-item.php?type=found')) ?>">report it as found</a> or hand it to the lost-and-found desk.</p>
    <?php elseif ($isOwner && !$canEdit[0] && !$canDelete[0] && $canDelete[1] !== ''): ?><p class="notice"><?= e($canDelete[1]) ?></p><?php endif; ?>
  </div>
</article>

<?php if ($isStaff && count($claims) > 0): ?>
<section class="panel"><h2>Claims on this item</h2>
  <ul class="plain-list"><?php foreach ($claims as $c): ?>
    <li><a href="<?= e(url('staff/verify-claims.php?id=' . (int) $c['id'])) ?>">Claim #<?= (int) $c['id'] ?></a> by <?= e($c['claimant_name']) ?> <?= badge($c['status']) ?> <small class="muted"><?= e(fmt_dt($c['created_at'])) ?></small></li>
  <?php endforeach; ?></ul></section>
<?php endif; ?>
<?php if (count($history) > 0): ?><section class="panel"><h2>Report history</h2><?= timeline_html($history) ?></section><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
