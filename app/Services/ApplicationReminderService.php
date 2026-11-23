<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ApplicantRepository;
use App\Models\ApplicationAccessLinkRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\NotificationRepository;
use App\Models\UserRepository;
use Throwable;

/**
 * The application reminders jobs/application_reminders.php sends (Nov 23, 2026: moved out of MembershipService, ISO
 * 25010 finding F-2): MSW staff about applications waiting, and applicants about applications returned for revision.
 */
final class ApplicationReminderService
{
    private const PENDING_REMINDER_DAYS = 3;
    private const REVISION_REMINDER_DAYS = 3;

    public function __construct(
        private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly ApplicantRepository $applicants = new ApplicantRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    /**
     * Tells MSW staff about applications waiting over 3 days. Once a day per person.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendPendingReminders(callable $log): array
    {
        $counts = ['sent' => 0, 'failed' => 0];
        $count = $this->applications->countPendingSince(date('Y-m-d H:i:s', time() - self::PENDING_REMINDER_DAYS * 86400));

        if ($count === 0) {
            return $counts;
        }

        $sent = new NotificationRepository();

        foreach (array_merge($this->users->activeIdsByRole('msw_head'), $this->users->activeIdsByRole('msw_member')) as $userId) {
            try {
                if (!$sent->sentToday((int) $userId, 'APP_PENDING_REMINDER')) {
                    $this->notifications->notify((int) $userId, 'APP_PENDING_REMINDER', ['count' => $count, 'link' => '/applications'], null, ['email']);
                    $counts['sent']++;
                }
            } catch (Throwable $exception) {
                $counts['failed']++;
                $log("User {$userId}: " . $exception->getMessage());
            }
        }

        return $counts;
    }

    /**
     * Reminds people whose application is For Revision every 3 days, with a fresh edit link for those without an
     * account. The queue erases the payload, so for them the edit link's date says when the last one went out, which
     * also counts the email sent when it was returned.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendRevisionReminders(callable $log): array
    {
        $counts = ['sent' => 0, 'failed' => 0];
        // By calendar day, so the job's exact run time does not push a reminder to the next day.
        $since = date('Y-m-d 00:00:00', time() - (self::REVISION_REMINDER_DAYS - 1) * 86400);
        $sent = new NotificationRepository();
        $links = new ApplicationAccessLinkRepository();
        $membership = new MembershipService();

        foreach ($this->applications->forRevisionWithRemarks() as $row) {
            $id = (int) $row['application_id'];

            try {
                $userId = $row['user_id'] ?? ($row['applicant_id'] === null ? null : $this->applicants->find((int) $row['applicant_id'])['user_id'] ?? null);
                $alreadyToday = $row['applicant_id'] !== null && $links->issuedSince($id, ApplicantAccessService::PURPOSE_REVISE, $since);
                $alreadyToday = $alreadyToday || ($userId !== null && $sent->sentSince((int) $userId, 'APP_FOR_REVISION', $since));

                if ($alreadyToday) {
                    continue;
                }

                Database::transaction(static function () use ($membership, $row, $id): void {
                    $membership->notifyOwner($row, 'APP_FOR_REVISION', [
                        'tracking_code' => $row['tracking_code'],
                        'remarks' => (string) $row['revision_remarks'],
                    ] + ($row['applicant_id'] !== null ? ['link' => (new ApplicantAccessService())->newRevisionLink($id)] : []), "/my-applications/{$id}");
                });
                $counts['sent']++;
            } catch (Throwable $exception) {
                $counts['failed']++;
                $log("Application {$id}: " . $exception->getMessage());
            }
        }

        return $counts;
    }
}
