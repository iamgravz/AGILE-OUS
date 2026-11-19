<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;
use Throwable;
use App\Core\Database;
use App\Models\CommitteeRepository;
use App\Models\CredentialRepository;
use App\Models\MemberRepository;
use App\Models\MembershipApplicationRepository;

/** Member records for staff, and the Membership section of a member's own account. */
final class MemberRecordsService
{
    public function __construct(
        private readonly MemberRepository $members = new MemberRepository(),
        private readonly CredentialRepository $credentials = new CredentialRepository(),
        private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository(),
    ) {
    }

    /** @return array<string, mixed>|null The signed-in user's membership, or null when they are not a member. */
    public function ownProfile(): ?array
    {
        $member = $this->members->findByUser((int) current_user_id());

        return $member === null ? null : $this->profile((int) $member['member_id']);
    }

    /** @param array{q?: string, type?: string, committee_id?: int, role_status?: string, registration?: string} $filters */
    public function list(array $filters, int $page): array
    {
        authorize('member.view');

        return $this->members->search($filters, $page);
    }

    /** @return list<array<string, mixed>> */
    public function committeeOptions(): array
    {
        authorize('member.view');

        return (new CommitteeRepository())->options();
    }

    /**
     * Opens a record for staff. Every open is logged.
     *
     * @return array<string, mixed>|null
     */
    public function open(int $memberId): ?array
    {
        authorize('member.view');

        $profile = $this->profile($memberId);

        if ($profile !== null) {
            AuditService::log('member.viewed', 'member', $memberId);
        }

        return $profile;
    }

    /**
     * MSW Committee Head only. Emails an approved applicant the steps to create their account (BR-18).
     *
     * @throws DomainException when they have registered already
     */
    public function resendRegistration(int $memberId): void
    {
        authorize('admin.users');

        Database::transaction(function () use ($memberId): void {
            $member = $this->members->findDetailed($memberId);

            if ($member === null || $member['user_id'] !== null || $member['applicant_id'] === null) {
                throw new DomainException('This member has already registered.');
            }

            $this->sendInstructions($member['email'], $member['first_name'], $member['member_no'], (string) $member['student_no']);
            AuditService::log('member.registration_resent', 'member', $memberId);
        });
    }

    /**
     * For jobs/application_reminders.php: 7 and 14 days after approval, if still not registered. The count on the row
     * keeps a second run from sending again, and someone found late gets one email, not two.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendRegistrationReminders(callable $log): array
    {
        $counts = ['sent' => 0, 'failed' => 0];

        foreach ($this->members->dueForRegistrationReminder(date('Y-m-d')) as $member) {
            try {
                Database::transaction(function () use ($member): void {
                    $this->sendInstructions($member['email'], $member['first_name'], $member['member_no'], (string) $member['student_no']);
                    $this->members->update((int) $member['member_id'], ['registration_reminders' => (int) $member['reminders_after']]);
                });
                $counts['sent']++;
            } catch (Throwable $exception) {
                $counts['failed']++;
                $log('Member ' . $member['member_id'] . ': ' . $exception->getMessage());
            }
        }

        return $counts;
    }

    private function sendInstructions(string $email, string $name, string $memberNo, string $studentNo): void
    {
        (new NotificationService())->notifyEmail($email, 'MEMBER_REGISTRATION_REMINDER', [
            'name' => $name,
            'member_no' => $memberNo,
            'student_no' => $studentNo,
            'register_url' => url('/register'),
            'link' => '/register',
        ]);
    }

    /** MSW Committee Head only. Also issues credentials to a seeded or older member who has none. */
    public function reissueCredentials(int $memberId, string $reason): void
    {
        authorize('credential.reissue');

        Database::transaction(function () use ($memberId, $reason): void {
            (new CredentialService())->reissue($memberId, $reason, CredentialService::REASON_REISSUE);
        });
    }

    /** @return array<string, mixed>|null */
    private function profile(int $memberId): ?array
    {
        $member = $this->members->findDetailed($memberId);

        if ($member === null) {
            return null;
        }

        $credentials = $this->credentials->forMember($memberId);
        // An eligibility result is for the member and those who handle eligibility, not all member-record staff (section 6).
        $mayReadNotice = ($member['user_id'] !== null && (int) $member['user_id'] === current_user_id()) || can('eligibility.manage');

        return [
            'notice' => $mayReadNotice ? (new EligibilityService())->noticeFor($memberId) : null,
            'member' => $member,
            'roleLabel' => MembershipService::roleLabel($member),
            'history' => $this->members->history($memberId),
            'applications' => array_values(array_filter(
                $member['user_id'] === null ? $this->applications->forApplicant((int) $member['applicant_id']) : $this->applications->forUser((int) $member['user_id']),
                static fn (array $application): bool => $application['status'] !== 'draft'
            )),
            'credentials' => $credentials,
            'activeCredentials' => array_values(array_filter($credentials, static fn (array $credential): bool => $credential['revoked_at'] === null)),
        ];
    }
}
