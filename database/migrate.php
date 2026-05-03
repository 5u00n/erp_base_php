<?php
/**
 * Plain PHP database migration runner.
 * Usage: php database/migrate.php
 *
 * Reads all *.sql files in database/migrations/, tracks applied migrations
 * in a schema_migrations table, and applies any that are new.
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$dsn = $_ENV['DATABASE_URL'] ?? 'sqlite:' . __DIR__ . '/../database/erp.db';

// Normalise: Prisma uses "file:./foo.db", PHP PDO expects "sqlite:./foo.db"
if (str_starts_with($dsn, 'file:')) {
    $dsn = 'sqlite:' . substr($dsn, 5);
}

try {
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Enable WAL for SQLite (ignored by other drivers)
try { $pdo->exec('PRAGMA journal_mode=WAL'); } catch (Exception) {}

// Create migrations tracking table
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    migration  TEXT NOT NULL UNIQUE,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
)");

$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$applied = $pdo->query("SELECT migration FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "  skip  $name" . PHP_EOL;
        continue;
    }
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
        $stmt->execute([$name]);
        echo "  apply $name" . PHP_EOL;
        $count++;
    } catch (PDOException $e) {
        echo "  ERROR $name: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

echo PHP_EOL . "Done. $count migration(s) applied." . PHP_EOL;
