<?php

declare(strict_types=1);

use App\Services\WelfareService;

require __DIR__ . '/_bootstrap.php';

// Daily at 08:00 for follow-ups. Hourly with --urgent for cases not acknowledged after 24 hours (BR-07).
$urgent = in_array('--urgent', $argv, true);

job_run($urgent ? 'welfare_followups_urgent' : 'welfare_followups', static function () use ($urgent): array {
    $service = new WelfareService();
    $counts = $urgent ? $service->sendOverdueAlerts(job_log(...)) : $service->sendFollowupReminders(job_log(...));

    return ['summary' => sprintf('%d sent, %d failed.', $counts['sent'], $counts['failed']), 'failed' => $counts['failed']];
});
