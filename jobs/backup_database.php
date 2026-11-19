<?php

declare(strict_types=1);

// Daily 02:00. Restoring is in docs/restore.md.

use App\Services\BackupService;

require __DIR__ . '/_bootstrap.php';

job_run('backup_database', static function (): array {
    $service = new BackupService();
    $backup = $service->run();
    $removed = $service->prune();

    return ['summary' => sprintf('%s, %.1f KB. %d old backups removed.', $backup['file'], $backup['size'] / 1024, count($removed)), 'failed' => 0];
});
