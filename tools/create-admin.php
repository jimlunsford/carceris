<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$username = $argv[1] ?? '';
$displayName = $argv[2] ?? '';
$password = $argv[3] ?? '';

if ($username === '' || $displayName === '' || $password === '') {
    echo "Usage:\n";
    echo "php tools/create-admin.php username \"Display Name\" \"Password\"\n";
    exit(1);
}

if (strlen($password) < 12) {
    echo "Password must be at least 12 characters.\n";
    exit(1);
}

$stmt = db()->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
$stmt->execute(['username' => $username]);

if ($stmt->fetch()) {
    echo "A user with that username already exists.\n";
    exit(1);
}

$insert = db()->prepare(
    'INSERT INTO users (username, password_hash, display_name, role, is_active)
     VALUES (:username, :password_hash, :display_name, "admin", 1)'
);

$insert->execute([
    'username' => $username,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'display_name' => $displayName,
]);

echo "Admin user created: {$username}\n";
