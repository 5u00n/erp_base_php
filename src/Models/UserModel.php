<?php

namespace App\Models;

use App\Db\Database;

class UserModel
{
    public static function findById(string $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, email, role, created_at, updated_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $id, string $email, string $passwordHash, string $role = 'USER'): array
    {
        $now = self::now();
        Database::pdo()->prepare(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$id, $email, $passwordHash, $role, $now, $now]);
        return self::findById($id);
    }

    public static function updatePassword(string $id, string $passwordHash): void
    {
        Database::pdo()->prepare(
            "UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?"
        )->execute([$passwordHash, self::now(), $id]);
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function listAll(): array
    {
        return Database::pdo()
            ->query('SELECT id, email, role, created_at FROM users ORDER BY created_at DESC')
            ->fetchAll();
    }

    public static function countProjects(string $userId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM projects WHERE owner_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
