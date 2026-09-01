#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Creates the first admin account.
 *
 *   php bin/create-admin.php <username> <password> ["Display Name"]
 *
 * Interactive password entry would be nicer, but this runs once on a machine
 * only Quitano touches, and a documented one-liner beats a prompt he has to
 * remember the shape of.
 */
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/UserRepository.php';

[$script, $username, $password] = array_pad($argv, 3, null);
$display = $argv[3] ?? $username;

if (!$username || !$password) {
    fwrite(STDERR, "usage: php bin/create-admin.php <username> <password> [\"Display Name\"]\n");
    exit(1);
}
if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$repo = new UserRepository(Database::connection());
if ($repo->findByUsername($username)) {
    fwrite(STDERR, "A user called '$username' already exists.\n");
    exit(1);
}
$id = $repo->create([
    'username' => $username,
    'password' => $password,
    'display_name' => $display,
    'email' => null,
    'role' => Auth::ROLE_ADMIN,
    'coach_id' => null,
]);
echo "Created admin '$username' (id $id).\n";
