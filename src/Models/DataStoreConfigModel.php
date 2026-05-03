<?php

namespace App\Models;

use App\Db\Database;

class DataStoreConfigModel
{
    public static function findByProject(string $projectId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM data_store_configs WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return $stmt->fetch() ?: null;
    }

    public static function upsert(string $id, string $projectId, string $storeType, string $encryptedConfig): array
    {
        $now      = self::now();
        $existing = self::findByProject($projectId);
        if ($existing) {
            Database::pdo()->prepare(
                'UPDATE data_store_configs SET store_type = ?, encrypted_config = ?, updated_at = ? WHERE project_id = ?'
            )->execute([$storeType, $encryptedConfig, $now, $projectId]);
        } else {
            Database::pdo()->prepare(
                'INSERT INTO data_store_configs (id, project_id, store_type, encrypted_config, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$id, $projectId, $storeType, $encryptedConfig, $now, $now]);
        }
        return self::findByProject($projectId);
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
