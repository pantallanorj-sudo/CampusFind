<?php
declare(strict_types=1);

/**
 * CampusFind database initializer.
 *
 * Browser (local machine only): open setup-database.php and submit the setup form.
 * CLI: php setup-database.php
 *
 * This script creates database/campusfind.sqlite from database/schema.sql.
 * It does NOT overwrite, reset, or delete an existing database.
 * The schema itself inserts the default categories and system settings.
 */

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');

    // The setup utility is intended only for local development.
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('For security, the CampusFind database setup page can only be used from this computer.');
    }
}

$projectRoot = __DIR__;
$databaseDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'database';
$schemaPath = $databaseDirectory . DIRECTORY_SEPARATOR . 'schema.sql';
$databasePath = $databaseDirectory . DIRECTORY_SEPARATOR . 'campusfind.sqlite';

/** Escape text before placing it in the setup page. */
function setupEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Print a simple result page or a command-line message, then stop. */
function setupRespond(bool $success, string $title, string $message, int $exitCode = 0): void
{
    global $isCli;

    if ($isCli) {
        $stream = $success ? STDOUT : STDERR;
        fwrite($stream, $title . PHP_EOL . $message . PHP_EOL);
        exit($exitCode);
    }

    http_response_code($success ? 200 : 500);
    $safeTitle = setupEscape($title);
    $safeMessage = setupEscape($message);
    $statusColor = $success ? '#166534' : '#991b1b';

    echo '<!doctype html>';
    echo '<html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $safeTitle . ' | CampusFind Setup</title>';
    echo '<style>';
    echo 'body{font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937;margin:0;padding:32px 16px;}'
       . 'main{max-width:680px;margin:40px auto;background:#fff;padding:28px;border-radius:14px;'
       . 'box-shadow:0 8px 28px rgba(15,23,42,.08);}'
       . 'h1{margin-top:0;font-size:25px;color:' . $statusColor . ';}'
       . 'p{line-height:1.6;}code{background:#f1f5f9;padding:3px 6px;border-radius:4px;}'
       . 'a{color:#1d4ed8;} .note{margin-top:20px;padding:14px;background:#f8fafc;border-radius:8px;}'
       . 'button{background:#1d4ed8;color:#fff;border:0;border-radius:8px;padding:12px 18px;'
       . 'font-size:15px;cursor:pointer;}button:hover{background:#1e40af;}'
       . '.warning{color:#92400e;background:#fffbeb;padding:12px;border-radius:8px;}'
       . '</style></head><body><main>';
    echo '<h1>' . $safeTitle . '</h1>';
    echo '<p>' . nl2br($safeMessage) . '</p>';
    echo '<div class="note"><strong>Database location</strong><br><code>database/campusfind.sqlite</code></div>';
    echo '<div class="note">After successful setup, delete <code>setup-database.php</code> from the project root.</div>';
    echo '</main></body></html>';
    exit($exitCode);
}

/** Create a clean database file from the project's existing schema. */
function initializeCampusFindDatabase(string $databaseDirectory, string $schemaPath, string $databasePath): void
{
    if (!is_file($schemaPath) || !is_readable($schemaPath)) {
        throw new RuntimeException('database/schema.sql is missing or cannot be read.');
    }

    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql === false || trim($schemaSql) === '') {
        throw new RuntimeException('database/schema.sql is empty or could not be read.');
    }

    if (!extension_loaded('pdo_sqlite') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('The PDO SQLite extension is not enabled. Enable pdo_sqlite in the PHP configuration used by XAMPP.');
    }

    if (!is_dir($databaseDirectory)) {
        if (!mkdir($databaseDirectory, 0775, true) && !is_dir($databaseDirectory)) {
            throw new RuntimeException('The database directory could not be created.');
        }
    }

    if (!is_writable($databaseDirectory)) {
        throw new RuntimeException('The database directory is not writable by PHP.');
    }

    // Protect all existing files, including an empty SQLite file.
    if (file_exists($databasePath)) {
        throw new RuntimeException(
            'A database file already exists. No changes were made. Back it up and inspect it first; this setup script never overwrites an existing database.'
        );
    }

    $temporaryPath = $databaseDirectory . DIRECTORY_SEPARATOR
        . '.campusfind-setup-' . bin2hex(random_bytes(8)) . '.sqlite';
    $pdo = null;

    try {
        $pdo = new PDO('sqlite:' . $temporaryPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        // SQLite foreign-key enforcement must be enabled on each connection,
        // and it must be enabled before the schema transaction begins.
        $pdo->exec('PRAGMA foreign_keys = ON');
        if ((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
            throw new RuntimeException('SQLite foreign-key enforcement could not be enabled.');
        }

        $pdo->beginTransaction();
        $pdo->exec($schemaSql);

        $requiredTables = [
            'users',
            'categories',
            'item_reports',
            'claim_requests',
            'claim_evidence',
            'item_images',
            'handover_records',
            'status_history',
            'claim_status_history',
            'notifications',
            'system_settings',
        ];

        $tableRows = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $createdTables = array_fill_keys($tableRows, true);
        $missingTables = [];

        foreach ($requiredTables as $tableName) {
            if (!isset($createdTables[$tableName])) {
                $missingTables[] = $tableName;
            }
        }

        if ($missingTables !== []) {
            throw new RuntimeException('The schema is missing required tables: ' . implode(', ', $missingTables));
        }

        $categoryCount = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
        if ($categoryCount < 1) {
            throw new RuntimeException('The schema did not insert the initial item categories.');
        }

        $retentionSetting = $pdo->prepare(
            'SELECT setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1'
        );
        $retentionSetting->execute([':setting_key' => 'unclaimed_retention_days']);
        if ($retentionSetting->fetchColumn() === false) {
            throw new RuntimeException('The unclaimed_retention_days system setting was not created.');
        }

        $foreignKeyViolations = $pdo->query('PRAGMA foreign_key_check')->fetchAll();
        if ($foreignKeyViolations !== []) {
            throw new RuntimeException('The database schema contains foreign-key violations.');
        }

        $pdo->commit();
        $pdo = null; // Close the temporary database before moving it into place.

        // Recheck before publishing the completed database.
        if (file_exists($databasePath)) {
            throw new RuntimeException('A database file appeared during setup. The existing file was not changed.');
        }

        if (!rename($temporaryPath, $databasePath)) {
            throw new RuntimeException('The completed database could not be moved into database/campusfind.sqlite. Check folder permissions.');
        }
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo = null;

        // Only remove the uniquely named temporary file created by this run.
        foreach ([$temporaryPath, $temporaryPath . '-journal', $temporaryPath . '-wal', $temporaryPath . '-shm'] as $temporaryFile) {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }

        throw $exception;
    }
}

// Command-line use runs immediately. Browser use requires an explicit POST.
$shouldRun = $isCli
    || (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && ($_POST['run_setup'] ?? '') === '1');

if (!$shouldRun) {
    if (file_exists($databasePath)) {
        setupRespond(
            true,
            'Database already exists',
            'CampusFind found an existing database file. No setup was run and no records were changed.\n\nOpen CampusFind normally. Do not delete or reset this database if it contains records you need.'
        );
    }

    if (!is_file($schemaPath)) {
        setupRespond(false, 'Setup cannot continue', 'The required file database/schema.sql was not found.');
    }

    echo '<!doctype html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>CampusFind Database Setup</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937;margin:0;padding:32px 16px;}'
       . 'main{max-width:680px;margin:40px auto;background:#fff;padding:28px;border-radius:14px;'
       . 'box-shadow:0 8px 28px rgba(15,23,42,.08);}h1{margin-top:0;color:#173b70;}'
       . 'p{line-height:1.6;}button{background:#1d4ed8;color:#fff;border:0;border-radius:8px;'
       . 'padding:12px 18px;font-size:15px;cursor:pointer;}button:hover{background:#1e40af;}'
       . '.warning{margin:18px 0;padding:14px;background:#fffbeb;color:#92400e;border-radius:8px;}'
       . 'code{background:#f1f5f9;padding:3px 6px;border-radius:4px;}</style></head><body><main>';
    echo '<h1>CampusFind Database Setup</h1>';
    echo '<p>This utility will create <code>database/campusfind.sqlite</code> using your existing <code>database/schema.sql</code>.</p>';
    echo '<p>It will create the required tables, indexes, initial categories, and the <code>unclaimed_retention_days</code> setting.</p>';
    echo '<div class="warning"><strong>Important:</strong> This setup does not create demo accounts. It will not overwrite an existing database. Back up your project before database changes.</div>';
    echo '<form method="post"><input type="hidden" name="run_setup" value="1">';
    echo '<button type="submit">Initialize CampusFind Database</button></form>';
    echo '</main></body></html>';
    exit(0);
}

try {
    initializeCampusFindDatabase($databaseDirectory, $schemaPath, $databasePath);

    setupRespond(
        true,
        'CampusFind database initialized successfully',
        'The database was created at database/campusfind.sqlite.\n'
        . 'The required tables, indexes, initial categories, and unclaimed_retention_days setting were verified.\n\n'
        . 'No demo user accounts were created because this project ZIP does not contain a seed.sql file. '
        . 'Public registration creates student accounts only.\n\n'
        . 'Next: open CampusFind and test the database connection. After confirming setup, delete setup-database.php from the project root.'
    );
} catch (Throwable $exception) {
    error_log('CampusFind database setup failed: ' . $exception->getMessage());

    $message = 'The database was not initialized. No existing database was overwritten.\n\n'
        . 'Check that PHP has pdo_sqlite enabled, database/schema.sql exists, and the database folder is writable. '
        . 'For technical details, check the PHP error log.';

    if ($isCli) {
        $message .= '\nTechnical detail: ' . $exception->getMessage();
    }

    setupRespond(false, 'CampusFind database setup failed', $message, 1);
}
