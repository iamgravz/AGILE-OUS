<?php

declare(strict_types=1);

// Creates the database if missing, runs schema.sql, the migrations that have not run yet, then seed.php.
// Usage: php database/setup.php [--fresh]
//   --fresh drops the database first. It is refused when APP_ENV=production.

use App\Core\Database;

require_once __DIR__ . '/bootstrap.php';

$fresh = in_array('--fresh', $argv, true);
$name = (string) config('app.database.name');

if ($fresh && config('app.env') === 'production') {
    fwrite(STDERR, "Refusing --fresh because APP_ENV=production.\n");
    exit(1);
}

if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
    fwrite(STDERR, "DB_NAME may only contain letters, numbers and underscores.\n");
    exit(1);
}

try {
    $server = Database::connect(null);
} catch (PDOException $exception) {
    fwrite(STDERR, "Could not connect to MySQL: {$exception->getMessage()}\nCheck the DB_* values in .env.\n");
    exit(1);
}

if ($fresh) {
    $server->exec("DROP DATABASE IF EXISTS `{$name}`");
    echo "Dropped database {$name}.\n";
}

$server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Database {$name} is ready.\n";

$sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(__DIR__ . '/schema.sql'));
$statements = array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql)));

$db = Database::connection();

foreach ($statements as $statement) {
    $db->exec($statement);
}

echo 'Schema applied (' . count($statements) . " statements).\n";

// CREATE TABLE IF NOT EXISTS leaves existing tables alone, so migrations bring older databases up to date.
// schema_migrations records each file that ran, so a file runs once per database. Every file is also safe to run
// again (it checks information_schema first), so a database from before schema_migrations simply runs them all once.
$migrations = glob(__DIR__ . '/migrations/*.{php,sql}', GLOB_BRACE) ?: [];
sort($migrations);
$ran = array_flip($db->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$record = $db->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
$count = 0;

foreach ($migrations as $migration) {
    $file = basename($migration);

    if (isset($ran[$file])) {
        continue;
    }

    try {
        if (str_ends_with($migration, '.php')) {
            (require $migration)($db);
        } else {
            $sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($migration));

            foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql))) as $statement) {
                $db->exec($statement);
            }
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, "Migration {$file} failed: {$exception->getMessage()}\nNothing after it ran. Fix it and run setup.php again.\n");
        exit(1);
    }

    $record->execute([$file]);
    $count++;
}

echo $count === 0 ? "Migrations: up to date.\n" : "Migrations: ran {$count}.\n";

require __DIR__ . '/seed.php';
require __DIR__ . '/report.php';
