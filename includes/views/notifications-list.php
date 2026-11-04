<?php
/** Shared notification list for students and staff. Expects $me (the signed-in user) and optional $pageTitle. */
$filterUnread = (get_str('filter', 10) === 'unread');
$page = max(1, get_int('page', 1));
$total = NotificationService::countFor($me['id'], $filterUnread);
$pg = paginate($total, SettingsService::perPage(), $page);
$items = NotificationService::listFor($me['id'], $filterUnread, $pg['per_page'], $pg['offset']);
$unreadTotal = NotificationService::unreadCount($me['id']);
$self = current_script();
$pageTitle = 'Notifications';
require __DIR__ . '/../header.php';
?>
<div class="page-head"><div><h1>Notifications</h1><p>Updates about your reports and claims.</p></div>
  <?php if ($unreadTotal > 0): ?>
    <form method="post" action="<?= e(url('actions/mark-notification-read.php')) ?>"><?= csrf_field() ?><?= hidden_field('all', '1') ?><?= hidden_field('return_to', $self) ?><button class="btn secondary" type="submit">Mark all as read</button></form>
  <?php endif; ?></div>
<div class="tabs" role="tablist">
  <a class="tab<?= $filterUnread ? '' : ' active' ?>" href="<?= e(url($self)) ?>">All</a>
  <a class="tab<?= $filterUnread ? ' active' : '' ?>" href="<?= e(url($self . '?filter=unread')) ?>">Unread (<?= (int) $unreadTotal ?>)</a>
</div>
<?php if (count($items) === 0): ?>
  <?= empty_state($filterUnread ? 'No unread notifications' : 'No notifications yet', 'New updates will appear here.') ?>
<?php else: ?>
<ul class="notice-list">
  <?php foreach ($items as $n): ?>
    <?php $link = NotificationService::linkFor($n, $me['role']); ?>
    <li class="<?= $n['read_at'] === null ? 'unread' : 'read' ?>">
      <div><p><?= e($n['message']) ?></p><small><?= e(fmt_dt($n['created_at'])) ?><?= $n['read_at'] === null ? ' · Unread' : ' · Read' ?></small></div>
      <div class="notice-actions">
        <?php if ($link !== null): ?><a class="btn small secondary" href="<?= e(url($link)) ?>">Open</a><?php endif; ?>
        <?php if ($n['read_at'] === null): ?>
          <form method="post" action="<?= e(url('actions/mark-notification-read.php')) ?>" class="inline-form"><?= csrf_field() ?><?= hidden_field('notification_id', $n['id']) ?><?= hidden_field('return_to', current_relative_url()) ?><button class="btn small ghost" type="submit">Mark as read</button></form>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
<?= pager_html($pg, $self, array('filter' => $filterUnread ? 'unread' : '')) ?>
<?php endif; ?>
<?php require __DIR__ . '/../footer.php'; ?>
