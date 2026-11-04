<?php
/**
 * CampusFind - Bulk Password Reset (DEVELOPMENT / LOCAL ADMIN TOOL)
 *
 * Resets the password for EVERY account in the users table, including
 * seed accounts and accounts registered through the application.
 *
 * Run from the project root:
 *   php tools/reset-all-passwords.php
 *
 * This script is CLI-only and never deletes users or related records.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden. Run this tool from the command line only.\n");
}

require_once __DIR__ . '/../config/database.php';

function promptLine(string $message): string
{
    fwrite(STDOUT, $message);
    $value = fgets(STDIN);
    return $value === false ? '' : rtrim($value, "\r\n");
}

fwrite(STDOUT, "\nCampusFind - BULK PASSWORD RESET\n");
fwrite(STDOUT, "================================\n");
fwrite(STDOUT, "WARNING: This changes the password of EVERY account in the users table.\n");
fwrite(STDOUT, "It does not delete accounts, reports, claims, or other records.\n");
fwrite(STDOUT, "Back up database/campusfind.sqlite before continuing.\n\n");

$confirmation = promptLine("Type RESET ALL to continue: ");
if ($confirmation !== 'RESET ALL') {
    fwrite(STDOUT, "Cancelled. No changes were made.\n");
    exit(0);
}

$newPassword = promptLine("Enter the new temporary password (minimum 12 characters): ");
$confirmPassword = promptLine("Re-enter the new password: ");

if (strlen($newPassword) < 12) {
    fwrite(STDERR, "Error: Password must be at least 12 characters. No changes were made.\n");
    exit(1);
}

if (!hash_equals($newPassword, $confirmPassword)) {
    fwrite(STDERR, "Error: Passwords do not match. No changes were made.\n");
    exit(1);
}

try {
    $pdo = db();

    // Confirm the expected users table and password_hash column exist.
    $columns = $pdo->query("PRAGMA table_info('users')")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    if (!in_array('id', $columnNames, true) || !in_array('password_hash', $columnNames, true)) {
        throw new RuntimeException("The users table is missing the required id or password_hash column.");
    }

    $accountCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($accountCount === 0) {
        fwrite(STDOUT, "No user accounts were found. Nothing was changed.\n");
        exit(0);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($newHash === false) {
        throw new RuntimeException("PHP could not generate a password hash.");
    }

    $pdo->beginTransaction();

    $statement = $pdo->prepare('UPDATE users SET password_hash = :password_hash');
    $statement->execute([':password_hash' => $newHash]);

    $updatedCount = $statement->rowCount();
    $pdo->commit();

    // Confirm that all rows now verify against the chosen password.
    $verification = $pdo->query('SELECT password_hash FROM users')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($verification as $storedHash) {
        if (!password_verify($newPassword, (string) $storedHash)) {
            throw new RuntimeException("Post-reset verification failed. Check the database backup before proceeding.");
        }
    }

    fwrite(STDOUT, "\nSUCCESS: Password reset completed.\n");
    fwrite(STDOUT, "Accounts found: " . $accountCount . "\n");
    fwrite(STDOUT, "Rows updated: " . $updatedCount . "\n");
    fwrite(STDOUT, "Verification: all account password hashes match the new password.\n");
    fwrite(STDOUT, "\nAll accounts now use the new password you entered.\n");
    fwrite(STDOUT, "Delete this utility file after use, or keep it outside the public project folder.\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "\nFAILED: " . $exception->getMessage() . "\n");
    fwrite(STDERR, "If any issue occurred, restore the backup before trying again.\n");
    exit(1);
}
