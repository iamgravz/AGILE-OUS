<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$pdo = db();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(200) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql');
sort($files);
foreach ($files as $file) {
    $name = basename($file);
    $q = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE filename = ?');
    $q->execute([$name]);
    if ($q->fetchColumn()) { echo "Skipped $name\n"; continue; }
    // DDL is implicitly committed by MySQL: do not pretend this is transactional.
    $sql = file_get_contents($file);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) !== '') $pdo->exec($statement);
    }
    $q = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
    $q->execute([$name]);
    echo "Applied $name\n";
}
