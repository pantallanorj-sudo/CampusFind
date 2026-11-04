<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_staff();
$s = ExportService::summary();
$c = $s['claims'];
$activeReports = db_count("SELECT COUNT(*) FROM item_reports WHERE status IN ('open','claim_pending')");
$queue = ClaimService::queue(array('status' => 'active', 'sort' => 'oldest'), 1, 5);
$awaiting = HandoverService::awaiting();
$eligible = ReportArchiveService::eligible(1, 5);
$retention = ReportArchiveService::retentionDays();
$metrics = array(
    array($activeReports, 'Active reports', 'staff/manage-items.php'),
    array($s['lost_open'], 'Open lost reports', 'staff/manage-items.php?type=lost'),
    array($s['found_open'], 'Open found reports', 'staff/manage-items.php?type=found'),
    array($c['pending'], 'Pending claims', 'staff/manage-claims.php?status=pending'),
    array($c['under_review'], 'Under review', 'staff/manage-claims.php?status=under_review'),
    array($c['needs_info'], 'Needs more info', 'staff/manage-claims.php?status=needs_info'),
    array($c['approved'], 'Approved, awaiting handover', 'staff/handover.php'),
    array($s['returned'], 'Items returned', 'public/claimed-items-history.php'),
    array($s['unclaimed_eligible'], 'Unclaimed, need attention', 'staff/unclaimed-items.php'),
);
$pageTitle = 'Staff dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">STAFF DASHBOARD</p><h1>Welcome, <?= e($me['full_name']) ?></h1><p>Live numbers from the CampusFind database.</p></div>
  <div class="actions"><a class="btn primary" href="<?= e(url('staff/manage-claims.php')) ?>">Open claim queue</a><a class="btn secondary" href="<?= e(url('student/report-item.php?type=found')) ?>">Log a found item</a></div></div>
<div class="stats">
  <?php foreach ($metrics as $m): ?>
    <a class="stat" href="<?= e(url($m[2])) ?>"><span><?= (int) $m[0] ?></span><small><?= e($m[1]) ?></small></a>
  <?php endforeach; ?>
</div>
<div class="grid-2">
  <section class="panel"><div class="section-heading"><h2>Claims waiting longest</h2><a href="<?= e(url('staff/manage-claims.php')) ?>">Queue →</a></div>
    <?php if (count($queue['rows']) === 0): ?><?= empty_state('Queue is clear', 'No claims are waiting for review.') ?>
    <?php else: ?><ul class="plain-list"><?php foreach ($queue['rows'] as $q): ?>
      <li><a href="<?= e(url('staff/verify-claims.php?id=' . (int) $q['id'])) ?>"><?= e($q['item_name']) ?></a> · <?= e($q['claimant_name']) ?> <?= badge($q['status']) ?> <small class="muted"><?= e(fmt_dt($q['created_at'])) ?></small></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>
  <section class="panel"><div class="section-heading"><h2>Approved — ready for handover</h2><a href="<?= e(url('staff/handover.php')) ?>">Handover →</a></div>
    <?php if (count($awaiting) === 0): ?><?= empty_state('Nothing to hand over', 'Approved claims will appear here.') ?>
    <?php else: ?><ul class="plain-list"><?php foreach (array_slice($awaiting, 0, 5) as $a): ?>
      <li><a href="<?= e(url('staff/handover.php?claim_id=' . (int) $a['id'])) ?>"><?= e($a['item_name']) ?></a> → <?= e($a['claimant_name']) ?> <small class="muted">approved <?= e(fmt_dt($a['reviewed_at'])) ?></small></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>
</div>
<section class="panel"><div class="section-heading"><h2>Unclaimed items past the <?= (int) $retention ?>-day retention period</h2><a href="<?= e(url('staff/unclaimed-items.php')) ?>">Manage →</a></div>
  <?php if (count($eligible['rows']) === 0): ?><?= empty_state('Nothing to archive', 'No found item has been unclaimed longer than the retention period.') ?>
  <?php else: ?><ul class="plain-list"><?php foreach ($eligible['rows'] as $r): ?>
    <li><a href="<?= e(url('staff/manage-items.php?id=' . (int) $r['id'])) ?>"><?= e($r['item_name']) ?></a> · <?= e($r['category_name']) ?> <small class="muted">unclaimed for <?= plural(days_since($r['created_at']), 'day') ?></small></li>
  <?php endforeach; ?></ul><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
