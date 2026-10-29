
<?php

require_once __DIR__ . '/config/database.php';

$email = 'student@campusfind.test';
$password = 'CampusFind123!';

$stmt = $pdo->prepare(
    'SELECT password_hash FROM users WHERE email = :email LIMIT 1'
);
$stmt->execute(['email' => $email]);

$user = $stmt->fetch();

if (!$user) {
    exit('Test failed: User account was not found.');
}

if (password_verify($password, $user['password_hash'])) {
    echo 'SUCCESS: Password matches the stored hash!';
} else {
    echo 'FAILED: Password does not match the stored hash.';
}
