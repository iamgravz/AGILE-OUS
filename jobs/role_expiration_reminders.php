<?php

declare(strict_types=1);

use App\Services\EligibilityService;

require __DIR__ . '/_bootstrap.php';

job_run('role_expiration_reminders', static function (): array {
    $counts = (new EligibilityService())->runRoleExpirationReminders(job_log(...));

    return ['summary' => sprintf('%d sent, %d failed.', $counts['sent'], $counts['failed']), 'failed' => $counts['failed']];
});
