<?php

namespace App\Models;

use App\Db\Database;

class AuditLogModel
{
    public static function create(string $id, ?string $userId, string $action, string $metaJson = '{}'): void
    {
        $now = gmdate('Y-m-d H:i:s');
        Database::pdo()->prepare(
            'INSERT INTO audit_logs (id, user_id, action, meta, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, $userId, $action, $metaJson, $now]);
    }
}
