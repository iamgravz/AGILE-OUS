<?php

declare(strict_types=1);

use App\Services\EligibilityService;

require __DIR__ . '/_bootstrap.php';

job_run('eligibility_reminders', static function (): array {
    $counts = (new EligibilityService())->runReminders(job_log(...));

    return ['summary' => sprintf('%d reminded, %d flagged, %d failed.', $counts['reminded'], $counts['flagged'], $counts['failed']), 'failed' => $counts['failed']];
});
