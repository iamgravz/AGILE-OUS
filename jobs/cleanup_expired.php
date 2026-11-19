<?php

declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/_bootstrap.php';

// Data minimization (RA 10173): one-time codes, links and tokens are deleted a week after they expire or are used,
// and rate-limit counters a day after their window. Only their hashes were ever stored. The audit log keeps the record.
const CLEANUP_RETENTION_DAYS = 7;

job_run('cleanup_expired', static function (): array {
    $db = Database::connection();
    $days = CLEANUP_RETENTION_DAYS;
    $rules = [
        'user_tokens' => "expires_at < NOW() - INTERVAL {$days} DAY OR used_at < NOW() - INTERVAL {$days} DAY",
        'guest_verifications' => "expires_at < NOW() - INTERVAL {$days} DAY",
        'application_access_links' => "expires_at < NOW() - INTERVAL {$days} DAY OR used_at < NOW() - INTERVAL {$days} DAY",
        'rate_limits' => 'window_start < NOW() - INTERVAL 1 DAY',
    ];
    $parts = [];

    foreach ($rules as $table => $where) {
        $deleted = (int) $db->exec("DELETE FROM {$table} WHERE {$where}");
        job_log("{$table}: {$deleted} deleted.");
        $parts[] = "{$table} {$deleted}";
    }

    return ['summary' => 'Deleted: ' . implode(', ', $parts) . '.', 'failed' => 0];
});
