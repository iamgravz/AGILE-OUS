<?php

declare(strict_types=1);

use App\Services\InterviewService;

require __DIR__ . '/_bootstrap.php';

job_run('interview_reminders', static function (): array {
    $counts = (new InterviewService())->sendDueReminders(job_log(...));

    return ['summary' => sprintf('%d sent, %d failed.', $counts['sent'], $counts['failed']), 'failed' => $counts['failed']];
});
