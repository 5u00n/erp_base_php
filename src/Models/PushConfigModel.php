<?php

namespace App\Models;

use App\Db\Database;

class PushConfigModel
{
    public static function findByProject(string $projectId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM push_configs WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return $stmt->fetch() ?: null;
    }

    public static function upsert(string $id, string $projectId, string $provider, string $encryptedBlob): array
    {
        $now = self::now();
        $existing = self::findByProject($projectId);
        if ($existing) {
            Database::pdo()->prepare(
                'UPDATE push_configs SET provider = ?, encrypted_blob = ?, updated_at = ? WHERE project_id = ?'
            )->execute([$provider, $encryptedBlob, $now, $projectId]);
        } else {
            Database::pdo()->prepare(
                'INSERT INTO push_configs (id, project_id, provider, encrypted_blob, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$id, $projectId, $provider, $encryptedBlob, $now, $now]);
        }
        return self::findByProject($projectId);
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
