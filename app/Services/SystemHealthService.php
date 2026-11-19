<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JobRunRepository;
use App\Models\NotificationRepository;

/** What the System health page shows, for backup.manage. Read-only, except retrying failed emails. */
final class SystemHealthService
{
    /** job => schedule, and how many hours may pass before a run counts as late. */
    public const JOBS = [
        'send_email_queue' => ['Every 5 minutes', 1],
        'close_vacancies' => ['Daily 00:05', 26],
        'backup_database' => ['Daily 02:00', 26],
        'cleanup_expired' => ['Daily 02:30', 26],
        'account_digest' => ['Daily 08:00', 26],
        'credential_digest' => ['Daily 08:00', 26],
        'role_expiration_reminders' => ['Daily 08:00', 26],
        'welfare_followups' => ['Daily 08:00', 26],
        'welfare_followups_urgent' => ['Hourly', 3],
        'interview_reminders' => ['Hourly', 3],
        'eligibility_reminders' => ['Daily 09:00', 26],
        'application_reminders' => ['Daily 09:00', 26],
    ];

    public function __construct(
        private readonly JobRunRepository $runs = new JobRunRepository(),
        private readonly NotificationRepository $notifications = new NotificationRepository(),
    ) {
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        authorize('backup.manage');

        $latest = $this->runs->latestByJob();
        $jobs = [];

        foreach (self::JOBS as $job => [$schedule, $lateAfterHours]) {
            $run = $latest[$job] ?? null;
            $jobs[] = [
                'job' => $job,
                'schedule' => $schedule,
                'run' => $run,
                'late' => $run === null || strtotime($run['started_at']) < time() - $lateAfterHours * 3600,
            ];
        }

        return [
            'jobs' => $jobs,
            'queued' => $this->notifications->countEmails('queued'),
            'failed' => $this->notifications->countEmails('failed'),
            'retryable' => $this->notifications->countRetryableFailed(),
            'backup' => (new BackupService())->latest(),
        ];
    }

    /** @return int How many emails went back in the queue. Emails whose one-time link was erased cannot be sent again. */
    public function retryFailed(): int
    {
        authorize('backup.manage');

        $count = $this->notifications->requeueFailedEmails();
        AuditService::log('email.retry_failed', 'notification', null, ['requeued' => $count]);

        return $count;
    }
}
