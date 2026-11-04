<?php
require_once __DIR__ . '/includes/auth.php';
$me = current_user();
$recent = db_all("SELECT r.id, r.item_name, r.report_type, r.location, r.date_occurred, c.name AS category_name "
    . "FROM item_reports r JOIN categories c ON c.id = r.category_id WHERE r.status = 'open' ORDER BY r.created_at DESC, r.id DESC LIMIT 6");
$returnedCount = HandoverService::returnedCount();
$activeCount = db_count("SELECT COUNT(*) FROM item_reports WHERE status IN ('open','claim_pending')");
$pageTitle = 'Find what matters';
require __DIR__ . '/includes/header.php';
?>
<section class="hero hero-home">
  <div class="hero-copy">
    <div class="institution-kicker"><span class="institution-dot"></span> LA VERDAD CHRISTIAN COLLEGE, INC. <span class="kicker-divider">·</span> APALIT, PAMPANGA</div>
    <p class="eyebrow">CAMPUS LOST &amp; FOUND INFORMATION SYSTEM</p>
    <h1>Lost something on campus? <span>Let's get it back to you.</span></h1>
    <p class="hero-description">CampusFind brings lost and found reports together in one trusted campus space. Browse listings, report an item, and follow every claim update until the handover is complete.</p>
    <div class="actions">
      <?php if ($me): ?>
        <a class="btn primary" href="<?= e(url(home_path($me['role']))) ?>">Go to my dashboard</a>
        <a class="btn secondary" href="<?= e(url('student/browse-items.php')) ?>">Browse items</a>
      <?php else: ?>
        <a class="btn primary" href="<?= e(url('student/browse-items.php')) ?>">Browse items</a>
        <a class="btn secondary" href="<?= e(url('register.php')) ?>">Create account</a>
      <?php endif; ?>
    </div>
    <p class="hero-stats"><strong><?= (int) $activeCount ?></strong> active listings <span>·</span> <strong><?= (int) $returnedCount ?></strong> items returned to their owners</p>
  </div>
  <aside class="hero-card hero-brand-card">
    <div class="hero-card-brand">
      <span class="hero-mark-wrap"><img src="<?= e(asset('assets/images/campusfind-mark.svg')) ?>" alt="" class="hero-mark"></span>
      <span><strong>CampusFind</strong><small>LA VERDAD CAMPUS SERVICE</small></span>
    </div>
    <div class="hero-search-art" aria-hidden="true">
      <div class="search-art-ring search-art-ring-one"></div>
      <div class="search-art-ring search-art-ring-two"></div>
      <div class="search-art-core"><span class="search-art-lens"></span><span class="search-art-handle"></span><span class="search-art-check">✓</span></div>
      <span class="art-tag art-tag-lost">LOST</span>
      <span class="art-tag art-tag-found">FOUND</span>
    </div>
    <div class="hero-card-caption"><strong>One campus. One place to reconnect.</strong><p>Search reported items, submit proof of ownership, and receive updates from authorized staff.</p></div>
    <div class="hero-card-footer"><span><b><?= (int) $activeCount ?></b> active listings</span><span><b><?= (int) $returnedCount ?></b> returned</span></div>
  </aside>
</section>

<section class="home-listings">
  <div class="section-heading"><div><p class="eyebrow">LATEST LISTINGS</p><h2>Recently reported items</h2><p class="section-intro">A quick look at the latest open reports on campus.</p></div><a href="<?= e(url('student/browse-items.php')) ?>">View all listings <span aria-hidden="true">→</span></a></div>
  <div class="cards">
    <?php if (count($recent) === 0): ?>
      <div class="empty"><strong>No active listings yet</strong>Be the first to report an item after signing in.</div>
    <?php else: ?>
      <?php foreach ($recent as $r): ?>
        <article class="item-card"><div class="card-body">
          <span class="pill <?= e($r['report_type']) ?>"><?= e(ucfirst($r['report_type'])) ?></span>
          <h3><?= e($r['item_name']) ?></h3>
          <p><?= e($r['category_name']) ?> · <?= e($r['location']) ?></p>
          <small>Date: <?= e(fmt_date($r['date_occurred'])) ?></small>
          <a class="text-link" href="<?= e(url('student/item-details.php?id=' . (int) $r['id'])) ?>">View details →</a>
        </div></article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="how home-how">
  <p class="eyebrow">HOW CAMPUSFIND WORKS</p>
  <h2>Simple steps. Safer returns.</h2>
  <p class="section-intro">From the first report to the final handover, every step has a clear place in the process.</p>
  <div class="cards">
    <article class="step"><b>01</b><h3>Report or search</h3><p>Browse campus listings or submit a lost or found item report.</p></article>
    <article class="step"><b>02</b><h3>Submit a claim</h3><p>Describe identifying details to help authorized staff verify ownership.</p></article>
    <article class="step"><b>03</b><h3>Confirm handover</h3><p>Staff records the actual return so the item is marked as returned.</p></article>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
