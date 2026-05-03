<?php

namespace App\Models;

use App\Db\Database;

class ProjectModel
{
    public static function findById(string $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByOwner(string $ownerId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, slug, created_at, updated_at FROM projects
             WHERE owner_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$ownerId]);
        return $stmt->fetchAll();
    }

    public static function findByOwnerAndId(string $ownerId, string $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM projects WHERE id = ? AND owner_id = ?'
        );
        $stmt->execute([$id, $ownerId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $id, string $ownerId, string $name, string $slug): array
    {
        $now = self::now();
        Database::pdo()->prepare(
            'INSERT INTO projects (id, owner_id, name, slug, settings, tree_data, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $ownerId, $name, $slug, '{}', '{}', $now, $now]);
        return self::findById($id);
    }

    public static function update(string $id, array $fields): ?array
    {
        $fields['updated_at'] = self::now();
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $stmt = Database::pdo()->prepare("UPDATE projects SET $sets WHERE id = ?");
        $stmt->execute([...array_values($fields), $id]);
        return self::findById($id);
    }

    public static function delete(string $id): void
    {
        Database::pdo()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    }

    public static function countApiKeys(string $projectId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM api_keys WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }

    public static function listAllForAdmin(): array
    {
        return Database::pdo()->query(
            'SELECT p.id, p.name, p.slug, p.created_at, p.owner_id,
                    u.email as owner_email
             FROM projects p
             JOIN users u ON u.id = p.owner_id
             ORDER BY p.created_at DESC'
        )->fetchAll();
    }

    public static function getTreeData(string $projectId): array
    {
        $stmt = Database::pdo()->prepare('SELECT tree_data FROM projects WHERE id = ?');
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new \RuntimeException('Project not found');
        }
        return json_decode($row['tree_data'], true) ?? [];
    }

    public static function setTreeData(string $projectId, array $data): void
    {
        Database::pdo()->prepare(
            "UPDATE projects SET tree_data = ?, updated_at = ? WHERE id = ?"
        )->execute([json_encode($data), self::now(), $projectId]);
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
