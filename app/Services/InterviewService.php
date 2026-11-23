<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\AppointmentRepository;
use App\Models\InterviewRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\UserRepository;
use DomainException;
use Throwable;

/** Interviews (FR-REC-06, TC-11). An application has one pending interview at a time; rescheduling moves it. */
final class InterviewService
{
    public const MODES = ['online' => 'Online', 'onsite' => 'On site'];
    public const RESULTS = ['passed' => 'Passed', 'failed' => 'Failed', 'no_show' => 'No show'];

    /**
     * The job runs hourly, so the first run inside this window is about 24 hours before. Running late still reminds.
     * An interview booked closer than the lower limit only gets its invite, because "tomorrow" would be wrong.
     */
    private const REMINDER_LATEST_HOURS = 25;
    private const REMINDER_EARLIEST_HOURS = 20;

    private const SCHEDULABLE = ['submitted', 'under_review', 'approved'];

    public function __construct(
        private readonly InterviewRepository $interviews = new InterviewRepository(),
        private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /**
     * The rules for an interview's details, by field, so the form can show them inline and the service can enforce them.
     *
     * @param array{scheduled_at: string, mode: string, location: string} $data
     * @return array<string, string>
     */
    public static function detailErrors(array $data, string $now): array
    {
        $errors = [];

        if ($data['scheduled_at'] <= $now) {
            $errors['scheduled_at'] = 'The interview must be in the future.';
        }

        if (!isset(self::MODES[$data['mode']])) {
            $errors['mode'] = 'Please choose online or on site.';
        } elseif ($data['mode'] === 'online' && preg_match('#^https?://\S+$#i', $data['location']) !== 1) {
            $errors['location'] = 'Enter the meeting link, starting with https://.';
        }

        return $errors;
    }

    /** @return array<string, mixed> What the Interviews tab shows. */
    public function panel(array $application): array
    {
        $manages = can('interview.manage');

        // The head of the application's committee reads the interviews, and MSW staff run them (BR-20).
        if (!$manages && !(new MembershipService())->isOwnCommitteeHead($application)) {
            authorize('interview.manage');
        }

        $interviews = $this->interviews->forApplication((int) $application['application_id']);
        $pending = array_values(array_filter($interviews, static fn (array $i): bool => $i['result'] === 'pending'))[0] ?? null;

        return [
            'interviews' => $interviews,
            'pendingInterview' => $pending,
            'interviewers' => array_values(array_filter($this->users->activeInternal(), static fn (array $u): bool => (int) $u['user_id'] !== (int) $application['user_id'])),
            'canSchedule' => $manages && $this->canSchedule($application),
        ];
    }

    /** @param array{result?: string} $filters */
    public function list(array $filters, int $page): array
    {
        authorize('interview.manage');

        return $this->interviews->search($filters, $page);
    }

    /**
     * @param array{scheduled_at: string, mode: string, location: string, interviewer_id: int} $data
     * @throws DomainException
     */
    public function schedule(int $applicationId, array $data): void
    {
        authorize('interview.manage');

        Database::transaction(function () use ($applicationId, $data): void {
            $application = $this->lockApplication($applicationId);
            $this->assertDetails($application, $data);

            if (!$this->canSchedule($application)) {
                throw new DomainException('An interview cannot be scheduled for this application in its current status.');
            }

            foreach ($this->interviews->forApplication($applicationId) as $interview) {
                if ($interview['result'] === 'pending') {
                    throw new DomainException('This application already has a pending interview. Reschedule it or record its result.');
                }
            }

            $id = $this->interviews->insert($data + ['application_id' => $applicationId]);
            AuditService::log('interview.scheduled', 'membership_application', $applicationId, ['interview_id' => $id, 'scheduled_at' => $data['scheduled_at'], 'mode' => $data['mode']]);
            $this->invite($application, $data);
        });
    }

    /**
     * Moves a pending interview, sends a new invite, and arms the reminder again.
     *
     * @param array{scheduled_at: string, mode: string, location: string, interviewer_id: int} $data
     * @throws DomainException
     */
    public function reschedule(int $applicationId, int $interviewId, array $data): void
    {
        authorize('interview.manage');

        Database::transaction(function () use ($applicationId, $interviewId, $data): void {
            $application = $this->lockApplication($applicationId);
            $this->assertDetails($application, $data);
            $this->pendingInterview($applicationId, $interviewId);

            if (!$this->canSchedule($application)) {
                throw new DomainException('This interview cannot be rescheduled in the application\'s current status.');
            }

            // A new time or interviewer starts a new checklist.
            $this->interviews->update($interviewId, $data + ['reminder_sent_at' => null, 'identity_verified' => 0, 'role_confirmed' => 0,
                'privacy_reminder_given' => 0, 'conflict_of_interest' => null, 'checklist_by' => null, 'checklist_at' => null]);
            AuditService::log('interview.rescheduled', 'membership_application', $applicationId, ['interview_id' => $interviewId, 'scheduled_at' => $data['scheduled_at'], 'mode' => $data['mode']]);
            $this->invite($application, $data);
        });
    }

    /**
     * BR-25, AGILE's interview checklist: before scoring, the interviewer verifies the applicant's identity and record,
     * confirms the role applied for, gives the privacy reminder and declares any conflict of interest. With a conflict,
     * the interview has to be rescheduled with another interviewer.
     *
     * @param array{identity: bool, role: bool, privacy: bool, conflict: string} $checklist conflict is 'no' or 'yes'
     * @throws DomainException
     */
    public function saveChecklist(int $applicationId, int $interviewId, array $checklist): void
    {
        authorize('interview.manage');

        if (!$checklist['identity'] || !$checklist['role'] || !$checklist['privacy']) {
            throw new DomainException('Tick every item on the checklist: identity and record, role, and privacy reminder.');
        }

        if (!in_array($checklist['conflict'], ['no', 'yes'], true)) {
            throw new DomainException('Say whether the interviewer has a conflict of interest.');
        }

        Database::transaction(function () use ($applicationId, $interviewId, $checklist): void {
            $this->lockApplication($applicationId);
            $interview = $this->pendingInterview($applicationId, $interviewId);

            if ($interview['checklist_at'] !== null) {
                throw new DomainException('The checklist for this interview is already confirmed.');
            }

            $conflict = $checklist['conflict'] === 'yes';
            $this->interviews->update($interviewId, [
                'identity_verified' => 1,
                'role_confirmed' => 1,
                'privacy_reminder_given' => 1,
                'conflict_of_interest' => $conflict ? 1 : 0,
                'checklist_by' => current_user_id(),
                'checklist_at' => date('Y-m-d H:i:s'),
            ]);
            AuditService::log($conflict ? 'interview.conflict_declared' : 'interview.checklist_confirmed', 'membership_application', $applicationId, ['interview_id' => $interviewId]);
        });
    }

    /** @throws DomainException */
    public function recordResult(int $applicationId, int $interviewId, string $result, string $notes): void
    {
        authorize('interview.manage');

        if (!isset(self::RESULTS[$result])) {
            throw new DomainException('Please choose a result.');
        }

        Database::transaction(function () use ($applicationId, $interviewId, $result, $notes): void {
            $this->lockApplication($applicationId);
            $interview = $this->pendingInterview($applicationId, $interviewId);

            // A no-show needs no checklist. Passed or failed means the interview happened, so the checklist comes first (BR-25).
            if ($result !== 'no_show') {
                if ($interview['checklist_at'] === null) {
                    throw new DomainException('Confirm the interview checklist before recording the result.');
                }

                if ((int) $interview['conflict_of_interest'] === 1) {
                    throw new DomainException('The interviewer declared a conflict of interest. Reschedule this interview with another interviewer.');
                }
            }

            $this->interviews->update($interviewId, ['result' => $result, 'notes' => trim($notes) === '' ? null : trim($notes)]);
            AuditService::log('interview.result_recorded', 'membership_application', $applicationId, ['interview_id' => $interviewId, 'result' => $result]);
        });
    }

    /**
     * Runs from jobs/interview_reminders.php with nobody signed in, so it has no permission check. Controllers must
     * not call it. reminder_sent_at is saved with the notification, so a second run sends nothing.
     *
     * @param callable(string): void $log
     * @return array{sent: int, failed: int}
     */
    public function sendDueReminders(callable $log): array
    {
        $counts = ['sent' => 0, 'failed' => 0];
        $now = time();
        $from = date('Y-m-d H:i:s', $now + self::REMINDER_EARLIEST_HOURS * 3600);
        $to = date('Y-m-d H:i:s', $now + self::REMINDER_LATEST_HOURS * 3600);

        foreach ($this->interviews->dueForReminder($from, $to) as $id) {
            try {
                $sent = Database::transaction(fn (): bool => $this->remindOne($id));

                if ($sent) {
                    $counts['sent']++;
                    $log("Reminder queued for interview {$id}.");
                }
            } catch (Throwable $exception) {
                // One bad row must not stop the rest, and it is picked up again on the next run.
                $counts['failed']++;
                $log(sprintf('Interview %d failed with %s: %s', $id, $exception::class, $exception->getMessage()));
            }
        }

        return $counts;
    }

    private function remindOne(int $id): bool
    {
        $interview = $this->interviews->lockForReminder($id);

        if ($interview === null || $interview['reminder_sent_at'] !== null || $interview['result'] !== 'pending') {
            return false;
        }

        (new MembershipService())->notifyOwner($interview, 'INTERVIEW_REMINDER', $this->payload($interview, $interview['tracking_code']), '/my-applications/' . (int) $interview['application_id']);
        $this->interviews->update($id, ['reminder_sent_at' => date('Y-m-d H:i:s')]);
        AuditService::log('interview.reminder_sent', 'membership_application', (int) $interview['application_id'], ['interview_id' => $id]);

        return true;
    }

    private function canSchedule(array $application): bool
    {
        return in_array($application['status'], self::SCHEDULABLE, true)
            && (int) $application['user_id'] !== current_user_id()
            && ((new AppointmentRepository())->liveForApplication((int) $application['application_id'])['status'] ?? null) !== 'approved';
    }

    /** @param array{scheduled_at: string, mode: string, location: string, interviewer_id: int} $data */
    private function assertDetails(array $application, array $data): void
    {
        foreach (self::detailErrors($data, date('Y-m-d H:i:s')) as $message) {
            throw new DomainException($message);
        }

        $choices = array_map(static fn (array $u): int => (int) $u['user_id'], $this->users->activeInternal());

        if (!in_array($data['interviewer_id'], $choices, true) || $data['interviewer_id'] === (int) $application['user_id']) {
            throw new DomainException('Please choose an interviewer from the list.');
        }
    }

    /** @return array<string, mixed> The application row, locked. Nobody may act on their own application. */
    private function lockApplication(int $id): array
    {
        $application = $this->applications->findForUpdate($id);

        if ($application === null || $application['status'] === 'draft') {
            throw new DomainException('This application no longer exists.');
        }

        if ((int) $application['user_id'] === current_user_id()) {
            throw new ForbiddenException('application.own_review');
        }

        return $application;
    }

    /** @return array<string, mixed> */
    private function pendingInterview(int $applicationId, int $interviewId): array
    {
        $interview = $this->interviews->find($interviewId);

        if ($interview === null || (int) $interview['application_id'] !== $applicationId) {
            throw new DomainException('This interview no longer exists.');
        }

        if ($interview['result'] !== 'pending') {
            throw new DomainException('This interview already has a result.');
        }

        return $interview;
    }

    /** @param array{scheduled_at: string, mode: string, location: string} $data */
    private function invite(array $application, array $data): void
    {
        (new MembershipService())->notifyOwner($application, 'INTERVIEW_INVITE', $this->payload($data, $application['tracking_code']), '/my-applications/' . (int) $application['application_id']);
    }

    /**
     * @param array<string, mixed> $interview
     * @return array<string, string>
     */
    private function payload(array $interview, string $trackingCode): array
    {
        return [
            'tracking_code' => $trackingCode,
            'scheduled_at' => date('F j, Y, g:i A', strtotime($interview['scheduled_at'])),
            'mode' => self::MODES[$interview['mode']],
            'location' => $interview['location'],
        ];
    }
}
