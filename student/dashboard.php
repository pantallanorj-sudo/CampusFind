<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_student();
$uid = (int) $me['id'];
$stats = array(
    'reports' => db_count('SELECT COUNT(*) FROM item_reports WHERE user_id = ?', array($uid)),
    'live_reports' => db_count("SELECT COUNT(*) FROM item_reports WHERE user_id = ? AND status IN ('open','claim_pending')", array($uid)),
    'active_claims' => db_count("SELECT COUNT(*) FROM claim_requests WHERE claimant_id = ? AND status IN ('pending','under_review','needs_info','approved')", array($uid)),
    'returned' => db_count("SELECT COUNT(*) FROM claim_requests WHERE claimant_id = ? AND status = 'returned'", array($uid)),
);
$unread = NotificationService::unreadCount($uid);
$needsInfo = db_all("SELECT q.id, r.item_name FROM claim_requests q JOIN item_reports r ON r.id = q.item_report_id WHERE q.claimant_id = ? AND q.status = 'needs_info' ORDER BY q.updated_at DESC", array($uid));
$ready = db_all("SELECT q.id, r.item_name FROM claim_requests q JOIN item_reports r ON r.id = q.item_report_id WHERE q.claimant_id = ? AND q.status = 'approved' ORDER BY q.updated_at DESC", array($uid));
$notes = NotificationService::listFor($uid, false, 5, 0);
$mine = ReportService::search(array('scope' => 'mine', 'user_id' => $uid), 1, 5);
$pageTitle = 'Student dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">STUDENT DASHBOARD</p><h1>Hello, <?= e($me['full_name']) ?></h1></div>
  <div class="actions"><a class="btn primary" href="<?= e(url('student/report-item.php')) ?>">Report an item</a><a class="btn secondary" href="<?= e(url('student/browse-items.php')) ?>">Browse items</a></div></div>

<?php foreach ($needsInfo as $n): ?>
  <div class="alert warning">Staff needs more information for your claim on <strong><?= e($n['item_name']) ?></strong>. <a href="<?= e(url('student/my-claims.php?id=' . (int) $n['id'])) ?>">Respond now</a></div>
<?php endforeach; ?>
<?php foreach ($ready as $n): ?>
  <div class="alert success">Your claim for <strong><?= e($n['item_name']) ?></strong> was approved. Bring your school ID to the lost-and-found desk. <a href="<?= e(url('student/my-claims.php?id=' . (int) $n['id'])) ?>">View claim</a></div>
<?php endforeach; ?>

<div class="stats">
  <div class="stat"><span><?= (int) $stats['reports'] ?></span><small>My reports</small></div>
  <div class="stat"><span><?= (int) $stats['live_reports'] ?></span><small>Still open</small></div>
  <div class="stat"><span><?= (int) $stats['active_claims'] ?></span><small>Active claims</small></div>
  <div class="stat"><span><?= (int) $stats['returned'] ?></span><small>Items returned to me</small></div>
  <div class="stat"><span><?= (int) $unread ?></span><small>Unread notifications</small></div>
</div>

<div class="grid-2">
  <section class="panel">
    <div class="section-heading"><h2>Recent notifications</h2><a href="<?= e(url('student/notifications.php')) ?>">View all →</a></div>
    <?php if (count($notes) === 0): ?><?= empty_state('Nothing yet', 'Updates about your reports and claims appear here.') ?>
    <?php else: ?><ul class="notice-list compact">
      <?php foreach ($notes as $n): ?><li class="<?= $n['read_at'] === null ? 'unread' : 'read' ?>"><?= e($n['message']) ?><small><?= e(fmt_dt($n['created_at'])) ?></small></li><?php endforeach; ?>
    </ul><?php endif; ?>
  </section>
  <section class="panel">
    <div class="section-heading"><h2>My latest reports</h2><a href="<?= e(url('student/my-reports.php')) ?>">Manage →</a></div>
    <?php if (count($mine['rows']) === 0): ?><?= empty_state('No reports yet', 'Lost or found something? Report it so others can help.', '<a class="btn primary small" href="' . e(url('student/report-item.php')) . '">Report an item</a>') ?>
    <?php else: ?><ul class="plain-list">
      <?php foreach ($mine['rows'] as $r): ?><li><a href="<?= e(url('student/item-details.php?id=' . (int) $r['id'])) ?>"><?= e($r['item_name']) ?></a> <?= type_pill($r['report_type']) ?> <?= badge($r['status']) ?></li><?php endforeach; ?>
    </ul><?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
