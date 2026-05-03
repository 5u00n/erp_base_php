<?php

namespace App\Db;

use PDO;
use App\Config;

/**
 * PDO singleton.  Call Database::pdo() anywhere to get the shared connection.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct() {}

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }
        return self::$pdo;
    }

    private static function connect(): PDO
    {
        $dsn = Config::get()->databaseUrl;

        // Normalise Prisma-style "file:./foo.db" → "sqlite:./foo.db"
        if (str_starts_with($dsn, 'file:')) {
            $dsn = 'sqlite:' . substr($dsn, 5);
        }

        // Resolve relative SQLite paths relative to the backend root (parent of public/)
        if (preg_match('/^sqlite:(\.\/)(.+)/', $dsn, $m)) {
            $backendRoot = dirname(__DIR__, 2); // src/../.. = backend/
            $dsn = 'sqlite:' . $backendRoot . '/' . $m[2];
        }

        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // SQLite pragmas for performance and reliability
        if (str_starts_with($dsn, 'sqlite:')) {
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
        }

        return $pdo;
    }

    /** Allow tests / CLI scripts to swap in a fresh connection */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
