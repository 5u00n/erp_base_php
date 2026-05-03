<?php

namespace App\Models;

use App\Db\Database;

class PaymentModel
{
    public static function listByProject(string $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM payments WHERE project_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function create(
        string $id,
        string $projectId,
        int $amountCents,
        string $currency,
        string $status,
        ?string $stripeIntentId,
        string $metaJson
    ): array {
        $now = self::now();
        Database::pdo()->prepare(
            'INSERT INTO payments (id, project_id, stripe_payment_intent_id, amount_cents, currency, status, meta, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $projectId, $stripeIntentId, $amountCents, $currency, $status, $metaJson, $now]);
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM payments')->fetchColumn();
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
