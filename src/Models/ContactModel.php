<?php

namespace App\Models;

use App\Db\Database;

class ContactModel
{
    public static function listByProject(string $projectId, ?string $role = null): array
    {
        if ($role !== null) {
            $stmt = Database::pdo()->prepare(
                "SELECT * FROM contacts WHERE project_id = ? AND meta LIKE ? ORDER BY created_at DESC"
            );
            $stmt->execute([$projectId, '%"role":"' . $role . '"%']);
        } else {
            $stmt = Database::pdo()->prepare(
                'SELECT * FROM contacts WHERE project_id = ? ORDER BY created_at DESC'
            );
            $stmt->execute([$projectId]);
        }
        return $stmt->fetchAll();
    }

    public static function create(string $id, string $projectId, string $email, ?string $name, string $metaJson): array
    {
        $now = self::now();
        Database::pdo()->prepare(
            'INSERT INTO contacts (id, project_id, email, name, meta, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$id, $projectId, $email, $name, $metaJson, $now]);
        $stmt = Database::pdo()->prepare('SELECT * FROM contacts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function delete(string $id, string $projectId): int
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM contacts WHERE id = ? AND project_id = ?'
        );
        $stmt->execute([$id, $projectId]);
        return $stmt->rowCount();
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
