<?php

namespace App\Models;

use App\Db\Database;

class EmailConfigModel
{
    public static function findByProject(string $projectId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM email_configs WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return $stmt->fetch() ?: null;
    }

    public static function upsert(
        string $id,
        string $projectId,
        string $host,
        int    $port,
        bool   $secure,
        string $fromAddress,
        string $fromName,
        string $encryptedCredentials
    ): array {
        $now      = self::now();
        $secureInt = $secure ? 1 : 0;
        $existing = self::findByProject($projectId);
        if ($existing) {
            Database::pdo()->prepare(
                'UPDATE email_configs SET host=?, port=?, secure=?, from_address=?, from_name=?,
                 encrypted_credentials=?, updated_at=? WHERE project_id=?'
            )->execute([$host, $port, $secureInt, $fromAddress, $fromName, $encryptedCredentials, $now, $projectId]);
        } else {
            Database::pdo()->prepare(
                'INSERT INTO email_configs (id, project_id, host, port, secure, from_address, from_name,
                 encrypted_credentials, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$id, $projectId, $host, $port, $secureInt, $fromAddress, $fromName,
                        $encryptedCredentials, $now, $now]);
        }
        return self::findByProject($projectId);
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
