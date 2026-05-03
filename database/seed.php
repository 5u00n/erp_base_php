<?php

/**
 * Database seeder — upserts the admin user (mirrors prisma/seed.ts).
 * Usage: php database/seed.php
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$dsn = $_ENV['DATABASE_URL'] ?? 'sqlite:' . __DIR__ . '/../database/erp.db';
if (str_starts_with($dsn, 'file:')) {
    $dsn = 'sqlite:' . substr($dsn, 5);
}

try {
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

try { $pdo->exec('PRAGMA foreign_keys=ON'); } catch (Exception) {}

$email    = $_ENV['SEED_ADMIN_EMAIL'] ?? 'admin@example.com';
$password = $_ENV['SEED_ADMIN_PASSWORD'] ?? 'admin123456';
$hash     = password_hash($password, PASSWORD_ARGON2ID);

function generateUuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

$now = gmdate('Y-m-d H:i:s');

$existing = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$existing->execute([$email]);
$row = $existing->fetch();

if ($row) {
    $pdo->prepare("UPDATE users SET password_hash = ?, role = 'ADMIN', updated_at = ? WHERE email = ?")
        ->execute([$hash, $now, $email]);
    echo "Updated admin: $email" . PHP_EOL;
} else {
    $id = generateUuid();
    $pdo->prepare(
        "INSERT INTO users (id, email, password_hash, role, created_at, updated_at)
         VALUES (?, ?, ?, 'ADMIN', ?, ?)"
    )->execute([$id, $email, $hash, $now, $now]);
    echo "Created admin: $email / $password" . PHP_EOL;
}

echo "Done." . PHP_EOL;
