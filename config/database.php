<?php
/**
 * One shared PDO (SQLite) connection for the whole application.
 * Every page, action, service and CLI tool calls db() - there is only one database file.
 * Override the path with the CAMPUSFIND_DB_PATH environment variable (used by the test suite).
 */
require_once __DIR__ . '/app.php';

class DatabaseNotReadyException extends RuntimeException
{
}

function db_path()
{
    $override = getenv('CAMPUSFIND_DB_PATH');
    if ($override !== false && $override !== '') {
        return $override;
    }
    return APP_ROOT . '/database/campusfind.sqlite';
}

function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $path = db_path();
    if (!is_file($path) || filesize($path) === 0) {
        throw new DatabaseNotReadyException('The database file is missing or empty.');
    }
    try {
        $conn = new PDO('sqlite:' . $path);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $conn->exec('PRAGMA foreign_keys = ON');
        $conn->exec('PRAGMA busy_timeout = 5000');
        if ((int) $conn->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
            throw new RuntimeException('SQLite foreign keys could not be enabled.');
        }
        $hasUsers = (int) $conn->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
    } catch (PDOException $e) {
        error_log('CampusFind DB error: ' . $e->getMessage());
        throw new RuntimeException('Could not connect to the CampusFind database.', 0, $e);
    }
    if ($hasUsers === 0) {
        throw new DatabaseNotReadyException('The database tables have not been created yet.');
    }
    $pdo = $conn;
    return $pdo;
}

/** First column of the first row (or null). */
function db_scalar($sql, $params = array())
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

/** Number from a COUNT()/SUM() query. */
function db_count($sql, $params = array())
{
    return (int) db_scalar($sql, $params);
}

function db_one($sql, $params = array())
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all($sql, $params = array())
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Run $fn(PDO) atomically. Any exception rolls everything back and is re-thrown.
 * If a transaction is already open (nested call) the work joins it.
 */
function db_transaction($fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn($pdo);
    }
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
