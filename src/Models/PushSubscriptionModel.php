<?php

namespace App\Models;

use App\Db\Database;

class PushSubscriptionModel
{
    public static function listByProject(string $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, endpoint, user_id, created_at FROM push_subscriptions
             WHERE project_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function listFull(string $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM push_subscriptions WHERE project_id = ?'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function upsert(
        string $id,
        string $projectId,
        string $endpoint,
        string $p256dh,
        string $auth,
        ?string $userId
    ): array {
        $now      = self::now();
        $existing = self::findByEndpoint($projectId, $endpoint);
        if ($existing) {
            Database::pdo()->prepare(
                'UPDATE push_subscriptions SET p256dh = ?, auth = ? WHERE project_id = ? AND endpoint = ?'
            )->execute([$p256dh, $auth, $projectId, $endpoint]);
            return self::findByEndpoint($projectId, $endpoint);
        }
        Database::pdo()->prepare(
            'INSERT INTO push_subscriptions (id, project_id, endpoint, p256dh, auth, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $projectId, $endpoint, $p256dh, $auth, $userId, $now]);
        return self::findByEndpoint($projectId, $endpoint);
    }

    public static function findByEndpoint(string $projectId, string $endpoint): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM push_subscriptions WHERE project_id = ? AND endpoint = ?'
        );
        $stmt->execute([$projectId, $endpoint]);
        return $stmt->fetch() ?: null;
    }

    public static function deleteByEndpoint(string $projectId, string $endpoint): void
    {
        Database::pdo()->prepare(
            'DELETE FROM push_subscriptions WHERE project_id = ? AND endpoint = ?'
        )->execute([$projectId, $endpoint]);
    }

    public static function countByProject(string $projectId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }

    public static function countTotal(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn();
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
