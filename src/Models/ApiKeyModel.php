<?php

namespace App\Models;

use App\Db\Database;

class ApiKeyModel
{
    public static function findById(string $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM api_keys WHERE id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function listByProject(string $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM api_keys WHERE project_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function create(
        string $id,
        string $projectId,
        string $name,
        string $keyHash,
        string $keyPrefix,
        string $keyLast4,
        string $scopesJson
    ): array {
        $now = self::now();
        Database::pdo()->prepare(
            'INSERT INTO api_keys (id, project_id, name, key_hash, key_prefix, key_last4, scopes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $projectId, $name, $keyHash, $keyPrefix, $keyLast4, $scopesJson, $now]);
        $stmt = Database::pdo()->prepare('SELECT * FROM api_keys WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function revoke(string $id, string $projectId): int
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE api_keys SET revoked_at = ? WHERE id = ? AND project_id = ? AND revoked_at IS NULL"
        );
        $stmt->execute([self::now(), $id, $projectId]);
        return $stmt->rowCount();
    }

    public static function countActive(string $projectId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM api_keys WHERE project_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }

    public static function countRevoked(string $projectId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM api_keys WHERE project_id = ? AND revoked_at IS NOT NULL'
        );
        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }

    public static function countTotal(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM api_keys')->fetchColumn();
    }

    public static function countTotalActive(): int
    {
        return (int) Database::pdo()
            ->query('SELECT COUNT(*) FROM api_keys WHERE revoked_at IS NULL')
            ->fetchColumn();
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
