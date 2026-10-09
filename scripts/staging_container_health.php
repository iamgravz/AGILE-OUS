<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__).'/app/bootstrap.php';

try {
    if (envValue('APP_ENV') !== 'staging' ||
        envValue('AGILE_UAT_READ_ONLY') !== 'true' ||
        envValue('SESSION_SECURE') !== 'true') {
        throw new RuntimeException('Not a protected staging container.');
    }
    foreach (['MFA_KEY_B64','AGILE_BACKUP_KEY_B64','AGILE_FILE_BACKUP_KEY_B64'] as $name) {
        $decoded = base64_decode(envValue($name), true);
        if (!is_string($decoded) || strlen($decoded) !== 32) {
            throw new RuntimeException('Staging encryption keys have not been provisioned.');
        }
    }
    $private = dirname(__DIR__).'/storage/private';
    if (!is_dir($private) || !is_writable($private)) {
        throw new RuntimeException('Private document storage is unavailable.');
    }
    $db = db();
    if ((int)$db->query('SELECT 1')->fetchColumn() !== 1) {
        throw new RuntimeException('Database connection check failed.');
    }
    // Web traffic remains blocked until manually reviewed migrations exist.
    $q = $db->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name='schema_migrations'");
    if ((int)$q->fetchColumn() !== 1) {
        throw new RuntimeException('Database schema has not been initialized.');
    }
    echo "HEALTHY\n";
} catch (Throwable $e) {
    // Do not expose secret paths, SQL errors or credentials in health logs.
    fwrite(STDERR,"UNHEALTHY\n");
    exit(1);
}
