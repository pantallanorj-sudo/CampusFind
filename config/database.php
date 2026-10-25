<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$databasePath = dirname(__DIR__) . '/database/campusfind.sqlite';
$databaseDirectory = dirname($databasePath);
if (!is_dir($databaseDirectory)) { throw new RuntimeException('Database directory is missing.'); }
try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ((int)$pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1) throw new RuntimeException('SQLite foreign keys could not be enabled.');
} catch (PDOException $e) {
    error_log('CampusFind DB error: ' . $e->getMessage());
    throw new RuntimeException('Could not connect to CampusFind database.', 0, $e);
}
