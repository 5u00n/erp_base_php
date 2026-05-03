<?php

namespace App\Models;

use App\Db\Database;

class PdfJobModel
{
    public static function listByProject(string $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM pdf_jobs WHERE project_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function create(string $id, string $projectId, ?string $templateId, string $inputMetaJson): array
    {
        $now = self::now();
        Database::pdo()->prepare(
            'INSERT INTO pdf_jobs (id, project_id, template_id, status, input_meta, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $projectId, $templateId, 'processing', $inputMetaJson, $now, $now]);
        return self::findById($id);
    }

    public static function findById(string $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM pdf_jobs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function updateStatus(string $id, string $status, ?string $outputUrl): array
    {
        $now = self::now();
        Database::pdo()->prepare(
            'UPDATE pdf_jobs SET status = ?, output_url = ?, updated_at = ? WHERE id = ?'
        )->execute([$status, $outputUrl, $now, $id]);
        return self::findById($id);
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM pdf_jobs')->fetchColumn();
    }

    public static function countCompleted(): int
    {
        return (int) Database::pdo()
            ->query("SELECT COUNT(*) FROM pdf_jobs WHERE status = 'completed'")
            ->fetchColumn();
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
