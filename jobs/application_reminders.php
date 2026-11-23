<?php

declare(strict_types=1);

use App\Services\MemberRecordsService;
use App\Services\ApplicationReminderService;

require __DIR__ . '/_bootstrap.php';

job_run('application_reminders', static function (): array {
    $reminders = new ApplicationReminderService();
    $pending = $reminders->sendPendingReminders(job_log(...));
    $revision = $reminders->sendRevisionReminders(job_log(...));
    $registration = (new MemberRecordsService())->sendRegistrationReminders(job_log(...));
    $failed = $pending['failed'] + $revision['failed'] + $registration['failed'];

    return ['summary' => sprintf('%d pending, %d revision and %d registration reminders sent, %d failed.', $pending['sent'], $revision['sent'], $registration['sent'], $failed), 'failed' => $failed];
});
