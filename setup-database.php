<?php
/**
 * CampusFind database setup. It never deletes or overwrites data and is safe to run more than once.
 *
 *   Browser (this computer only): open setup-database.php and press "Create database".
 *                                 It only creates a NEW database; an existing one is left untouched.
 *   Command line:                 php setup-database.php             create the database if it is missing
 *                                 php setup-database.php --check     show tables and row counts
 *                                 php setup-database.php --upgrade   back up the database, then apply schema.sql again
 *                                                                    (idempotent: adds missing tables/indexes/settings only)
 *
 * After setup you can delete this file; nothing else depends on it.
 */
require_once __DIR__ . '/includes/csrf.php';

$required = array('users', 'categories', 'item_reports', 'claim_requests', 'claim_evidence', 'item_images',
    'handover_records', 'status_history', 'claim_status_history', 'notifications', 'system_settings');
$schemaFile = APP_ROOT . '/database/schema.sql';
$dbFile = db_path();

function setup_inspect($path, $required)
{
    $info = array('exists' => (is_file($path) && filesize($path) > 0), 'present' => array(), 'missing' => $required, 'counts' => array());
    if (!$info['exists']) {
        return $info;
    }
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll() as $row) {
        $info['present'][] = $row['name'];
    }
    $info['missing'] = array_values(array_diff($required, $info['present']));
    foreach ($required as $table) {
        if (in_array($table, $info['present'], true)) {
            $info['counts'][$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
    }
    return $info;
}

/** Applies schema.sql (all statements are IF NOT EXISTS / INSERT OR IGNORE). Returns the backup file path or null. */
function setup_apply($path, $schemaFile, $backup)
{
    $schema = is_file($schemaFile) ? file_get_contents($schemaFile) : false;
    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException('database/schema.sql is missing or empty.');
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('The database folder could not be created.');
    }
    $backupFile = null;
    if ($backup && is_file($path) && filesize($path) > 0) {
        $backupDir = $dir . '/backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
            throw new RuntimeException('The backup folder could not be created, so nothing was changed.');
        }
        $backupFile = $backupDir . '/campusfind-' . date('Ymd-His') . '.sqlite';
        if (!copy($path, $backupFile)) {
            throw new RuntimeException('A backup could not be created, so nothing was changed.');
        }
    }
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->beginTransaction();
    try {
        $pdo->exec($schema);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $backupFile;
}

$info = setup_inspect($dbFile, $required);
$notInitialised = (count($info['present']) === 0);

/* ---------------- command line ---------------- */
if (PHP_SAPI === 'cli') {
    $args = isset($argv) ? $argv : array();
    if (in_array('--check', $args, true)) {
        echo ($info['exists'] ? 'Database file found.' : 'No database file yet.') . PHP_EOL;
        foreach ($required as $t) {
            echo '  ' . str_pad($t, 22) . (isset($info['counts'][$t]) ? $info['counts'][$t] . ' rows' : 'MISSING') . PHP_EOL;
        }
        exit(count($info['missing']) === 0 ? 0 : 1);
    }
    $upgrade = in_array('--upgrade', $args, true);
    if (!$notInitialised && !$upgrade) {
        echo 'The database is already initialised. Nothing was changed.' . PHP_EOL . 'Use --upgrade to back it up and apply schema.sql again (adds anything missing), or --check to inspect it.' . PHP_EOL;
        exit(0);
    }
    $backupFile = setup_apply($dbFile, $schemaFile, true);
    echo ($notInitialised ? 'Database created.' : 'Schema applied.') . PHP_EOL;
    if ($backupFile !== null) {
        echo 'Backup saved to: ' . $backupFile . PHP_EOL;
    }
    echo 'Next: create the first administrator with  php tools/create-admin.php' . PHP_EOL;
    exit(0);
}

/* ---------------- browser ---------------- */
$remote = client_ip();
if ($remote !== '127.0.0.1' && $remote !== '::1') {
    abort_page(403, 'Setup is local only', 'For safety, setup-database.php only runs from the computer hosting CampusFind. Use the command line (php setup-database.php) instead.');
}
$message = '';
$errorText = '';
if (is_post()) {
    verify_csrf();
    if (!$notInitialised) {
        $errorText = 'The database already exists, so it was not changed. Use "php setup-database.php --upgrade" if you need to apply schema updates.';
    } else {
        try {
            setup_apply($dbFile, $schemaFile, false);
            $info = setup_inspect($dbFile, $required);
            $notInitialised = false;
            $message = 'Database created successfully.';
        } catch (Throwable $e) {
            error_log('CampusFind setup failed: ' . $e->getMessage());
            $errorText = 'The database could not be created. Check that the database folder is writable and the pdo_sqlite extension is enabled.';
        }
    }
}
$pageTitle = 'Database setup';
$useSidebar = false;
require __DIR__ . '/includes/header.php';
?>
<section class="panel narrow">
  <h1>CampusFind database setup</h1>
  <?php if ($message !== ''): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($errorText !== ''): ?><div class="alert error"><?= e($errorText) ?></div><?php endif; ?>
  <?php if ($notInitialised): ?>
    <p>No database has been created yet. This will create <code>database/campusfind.sqlite</code> with all tables, the default categories and the default settings.</p>
    <form method="post" action="<?= e(url('setup-database.php')) ?>"><?= csrf_field() ?><button class="btn primary" type="submit">Create database</button></form>
  <?php else: ?>
    <p>The database is initialised. Existing data was <strong>not</strong> changed.</p>
    <table class="table"><thead><tr><th>Table</th><th>Rows</th></tr></thead><tbody>
      <?php foreach ($required as $t): ?>
        <tr><td><?= e($t) ?></td><td><?= isset($info['counts'][$t]) ? (int) $info['counts'][$t] : 'missing' ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <h2>Next steps</h2>
    <ol>
      <li>Create the first administrator from a terminal: <code>php tools/create-admin.php</code></li>
      <li>Open <a href="<?= e(url('login.php')) ?>">the login page</a>.</li>
      <li>Delete <code>setup-database.php</code> when you no longer need it.</li>
    </ol>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
