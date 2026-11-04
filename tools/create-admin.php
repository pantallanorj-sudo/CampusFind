<?php
/**
 * Create the first administrator (or a staff account), or promote an existing account. COMMAND LINE ONLY.
 *
 *   php tools/create-admin.php                                   asks for name, email and password
 *   php tools/create-admin.php --name="Ana Cruz" --email=ana@school.edu --role=admin
 *   php tools/create-admin.php --promote=someone@school.edu --role=staff
 *
 * The password is never accepted as an argument (it would stay in your shell history). It is typed at the prompt,
 * or read from the CAMPUSFIND_ADMIN_PASSWORD environment variable for scripted setups.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This tool can only be run from the command line.');
}
require_once __DIR__ . '/../includes/auth.php';

$opts = array();
foreach (array_slice(isset($argv) ? $argv : array(), 1) as $arg) {
    if (strpos($arg, '--') === 0) {
        $eq = strpos($arg, '=');
        $opts[$eq === false ? substr($arg, 2) : substr($arg, 2, $eq - 2)] = ($eq === false) ? '1' : substr($arg, $eq + 1);
    }
}

function cli_ask($label, $hidden = false)
{
    $canHide = $hidden && DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
    echo $label . ': ';
    if ($canHide) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($canHide) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    return $line === false ? '' : trim($line);
}

$role = isset($opts['role']) ? $opts['role'] : 'admin';
if (!in_array($role, array('admin', 'staff'), true)) {
    fwrite(STDERR, 'Role must be admin or staff.' . PHP_EOL);
    exit(1);
}

if (isset($opts['promote'])) {
    $email = strtolower(trim($opts['promote']));
    $user = db_one('SELECT id, full_name, role FROM users WHERE email = ?', array($email));
    if (!$user) {
        fwrite(STDERR, 'No account with that email exists. Ask the person to register first.' . PHP_EOL);
        exit(1);
    }
    db()->prepare('UPDATE users SET role = ?, is_active = 1 WHERE id = ?')->execute(array($role, (int) $user['id']));
    audit_log(0, 'cli.promote', 'id=' . $user['id'] . ' role=' . $role);
    echo $user['full_name'] . ' (' . $email . ') is now ' . $role . '.' . PHP_EOL;
    exit(0);
}

$name = isset($opts['name']) ? $opts['name'] : cli_ask('Full name');
$email = isset($opts['email']) ? $opts['email'] : cli_ask('Email address');
$password = getenv('CAMPUSFIND_ADMIN_PASSWORD');
if ($password === false || $password === '') {
    if (DIRECTORY_SEPARATOR === '\\') {
        echo 'Note: on Windows the password is visible while you type it.' . PHP_EOL;
    }
    $password = cli_ask('Password (min 8 characters)', true);
    if ($password !== cli_ask('Repeat password', true)) {
        fwrite(STDERR, 'The passwords did not match. Nothing was created.' . PHP_EOL);
        exit(1);
    }
}
$result = UserService::insert($name, $email, $password, $role);
if (!$result['ok']) {
    foreach ($result['errors'] as $msg) {
        fwrite(STDERR, '- ' . $msg . PHP_EOL);
    }
    exit(1);
}
audit_log(0, 'cli.create', 'id=' . $result['id'] . ' role=' . $role);
echo 'Created ' . $role . ' account for ' . strtolower(trim($email)) . '. You can log in now.' . PHP_EOL;
exit(0);
