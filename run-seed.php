
<?php

$databasePath = __DIR__ . '/database/campusfind.sqlite';
$seedPath = __DIR__ . '/database/seed.sql';

try {
    if (!is_file($databasePath)) {
        throw new RuntimeException('Database file not found.');
    }

    if (!is_file($seedPath)) {
        throw new RuntimeException('database/seed.sql was not found.');
    }

    $seedSql = file_get_contents($seedPath);

    if ($seedSql === false || trim($seedSql) === '') {
        throw new RuntimeException('The seed.sql file is empty.');
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    // seed.sql manages its own transaction.
    $pdo->exec($seedSql);

    echo "CampusFind seed data executed successfully!" . PHP_EOL;

    foreach (['users', 'categories', 'item_reports'] as $table) {
        $count = $pdo
            ->query("SELECT COUNT(*) FROM {$table}")
            ->fetchColumn();

        echo "{$table}: {$count} records" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Seed execution failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
