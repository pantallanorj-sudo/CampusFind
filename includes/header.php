<?php
require_once __DIR__ . '/auth.php';
$me = current_user();
$cfFlashes = get_flashes();
$cfUnread = $me ? NotificationService::unreadCount($me['id']) : 0;
$cfAppName = app_name();
$pageTitle = isset($pageTitle) ? $pageTitle : $cfAppName;
$cfSidebar = $me && (!isset($useSidebar) || $useSidebar);
$cfBody = $cfSidebar ? 'with-sidebar' : 'public';
$cfScript = current_script();
$cfNotify = ($me && $me['role'] === 'student') ? 'student/notifications.php' : 'staff/notifications.php';
$cfHome = $me ? home_path($me['role']) : 'index.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#014D8A">
<meta name="description" content="CampusFind — the campus lost and found information system of La Verdad Christian College, Inc.">
<title><?= e($pageTitle) ?> | <?= e($cfAppName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/images/campusfind-icon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body class="<?= e($cfBody) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar">
  <div class="brand-lockup">
    <a class="school-brand" href="<?= e(url($cfHome)) ?>" aria-label="La Verdad Christian College home">
      <img src="<?= e(asset('assets/images/la-verdad-seal.png')) ?>" alt="La Verdad Christian College seal" class="school-seal">
      <span class="school-wordmark">
        <strong>LA VERDAD</strong>
        <small>CHRISTIAN COLLEGE, INC.</small>
        <em>APALIT, PAMPANGA</em>
      </span>
    </a>
    <span class="brand-divider" aria-hidden="true"></span>
    <a class="brand" href="<?= e(url($cfHome)) ?>" aria-label="CampusFind home">
      <img src="<?= e(asset('assets/images/campusfind-mark.svg')) ?>" alt="" class="campusfind-mark">
      <span class="brand-copy">
        <strong><?= e($cfAppName) ?></strong>
        <small>LOST &amp; FOUND SYSTEM</small>
      </span>
    </a>
  </div>
  <nav class="topnav" aria-label="Main">
    <?php if ($me): ?>
      <a href="<?= e(url($cfNotify)) ?>" class="topnav-notifications">Notifications<?php if ($cfUnread > 0): ?> <span class="count" title="Unread notifications"><?= (int) $cfUnread ?></span><?php endif; ?></a>
      <span class="user-chip" title="<?= e($me['email']) ?>"><span class="avatar" aria-hidden="true"><?= e(initials($me['full_name'])) ?></span><span class="user-name"><?= e($me['full_name']) ?> <small>(<?= e($me['role']) ?>)</small></span></span>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn small topbar-logout">Log out</button></form>
    <?php else: ?>
      <a href="<?= e(url('index.php')) ?>">Home</a>
      <a href="<?= e(url('student/browse-items.php')) ?>">Browse items</a>
      <a href="<?= e(url('public/claimed-items-history.php')) ?>">Returned history</a>
      <a href="<?= e(url('login.php')) ?>">Log in</a>
      <a class="nav-cta" href="<?= e(url('register.php')) ?>">Register</a>
    <?php endif; ?>
  </nav>
</header>
<div class="shell">
<?php if ($cfSidebar): ?>
  <aside id="sidebar" class="sidebar" aria-label="Section navigation">
    <div class="sidebar-brand-note">
      <span class="sidebar-brand-kicker">CAMPUS PORTAL</span>
      <strong><?= e(ucfirst($me['role'])) ?> Workspace</strong>
      <small>La Verdad Christian College</small>
    </div>
    <?php foreach (nav_items($me['role']) as $cfGroup): ?>
      <?php if ($cfGroup['label'] !== ''): ?><p class="nav-heading"><?= e($cfGroup['label']) ?></p><?php endif; ?>
      <ul class="sidebar-nav">
      <?php foreach ($cfGroup['items'] as $cfItem): ?>
        <?php $cfActive = ($cfScript === $cfItem[1]) || (isset($cfItem[2]) && in_array($cfScript, $cfItem[2], true)); ?>
        <li><a href="<?= e(url($cfItem[1])) ?>"<?= $cfActive ? ' class="active" aria-current="page"' : '' ?>><?= e($cfItem[0]) ?><?php if ($cfUnread > 0 && substr($cfItem[1], -17) === 'notifications.php'): ?> <span class="count"><?= (int) $cfUnread ?></span><?php endif; ?></a></li>
      <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </aside>
<?php endif; ?>
<main id="main" class="content">
<?php foreach ($cfFlashes as $cfFlash): ?>
  <div class="alert <?= e($cfFlash['type']) ?>" role="<?= $cfFlash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($cfFlash['message']) ?></div>
<?php endforeach; ?>
<?php if (!empty($breadcrumbs)): ?><?= breadcrumbs_html($breadcrumbs) ?><?php endif; ?>
