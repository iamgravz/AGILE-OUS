<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ApplicantRepository;
use App\Models\CredentialRepository;
use App\Models\MemberRepository;
use App\Models\NotificationRepository;
use App\Models\UserRepository;
use DomainException;

/** BR-21: the MSW Committee Head approves or rejects every new account before it can be used. */
final class AccountApprovalService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        authorize('account.approve');

        return $this->users->pendingAccounts();
    }

    /** Someone rejected earlier may have lost their credentials, so anything missing is generated again for review. */
    public function approve(int $userId): void
    {
        authorize('account.approve');

        Database::transaction(function () use ($userId): void {
            $this->lockPending($userId);
            $this->users->update($userId, ['status' => 'active', 'approved_by' => current_user_id(), 'approved_at' => date('Y-m-d H:i:s'), 'rejection_remarks' => null]);
            AuditService::log('account.approved', 'user', $userId);
            $member = (new MemberRepository())->findByUser($userId);

            if ($member !== null) {
                (new CredentialService())->issueFor((int) $member['member_id']);
            }

            $this->notifications->notify($userId, 'ACCOUNT_APPROVED', ['link' => '/account'], null, ['email']);
        });
    }

    /**
     * @param list<int> $userIds
     * @return int How many were approved. One no longer waiting is skipped.
     */
    public function approveSelected(array $userIds): int
    {
        authorize('account.approve');

        $userIds = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));

        if ($userIds === []) {
            throw new DomainException('Please select at least one account.');
        }

        $count = 0;

        foreach ($userIds as $userId) {
            try {
                $this->approve($userId);
                $count++;
            } catch (DomainException) {
                continue;
            }
        }

        return $count;
    }

    /**
     * BR-21: the account is disabled and freed from the person, so they may register again once the issue is fixed.
     * The row stays under a placeholder email, because the audit trail points at it. The email goes to their real
     * address, which the queue could no longer find from the row.
     */
    public function reject(int $userId, string $reason): void
    {
        authorize('account.approve');

        if (trim($reason) === '') {
            throw new DomainException('Please give a reason for rejecting this account.');
        }

        Database::transaction(function () use ($userId, $reason): void {
            $user = $this->lockPending($userId);
            $member = (new MemberRepository())->findByUser($userId);

            // The member record stays (BR-13), but an ID or certificate must not keep verifying as valid.
            if ($member !== null) {
                $serials = (new CredentialRepository())->revokeActive((int) $member['member_id']);
                AuditService::log('credential.revoked', 'member', (int) $member['member_id'], ['serials' => $serials, 'reason' => 'account_rejected']);
                (new MemberRepository())->update((int) $member['member_id'], ['user_id' => null, 'registered_at' => null]);
            }

            foreach ((new ApplicantRepository())->findBy(['user_id' => $userId]) as $applicant) {
                (new ApplicantRepository())->update((int) $applicant['applicant_id'], ['user_id' => null]);
            }

            (new WelfareService())->unlinkGuestCases($userId);
            $this->users->update($userId, [
                'status' => 'disabled',
                'email' => "retired-applicant-{$userId}@agile.invalid",
                'student_no' => null,
                'rejection_remarks' => trim($reason),
            ]);
            AuditService::log('account.rejected', 'user', $userId, ['reason' => mb_substr(trim($reason), 0, 255)]);
            $this->notifications->notifyEmail($user['email'], 'ACCOUNT_REJECTED', ['name' => $user['first_name'], 'remarks' => trim($reason)]);
        });
    }

    /**
     * For jobs/account_digest.php. Nobody is logged in, so this checks nothing. Once a day per person.
     *
     * @return int How many digests were queued.
     */
    public function sendDigest(): int
    {
        $count = count($this->users->pendingAccounts());

        if ($count === 0) {
            return 0;
        }

        $sent = 0;

        foreach ($this->users->activeIdsByRole('msw_head') as $userId) {
            if (!(new NotificationRepository())->sentToday((int) $userId, 'ACCOUNTS_FOR_APPROVAL')) {
                $this->notifications->notify((int) $userId, 'ACCOUNTS_FOR_APPROVAL', ['count' => $count, 'link' => '/accounts/approvals'], null, ['email']);
                $sent++;
            }
        }

        return $sent;
    }

    /** @return array<string, mixed> */
    private function lockPending(int $userId): array
    {
        $this->users->lockRow($userId);
        $user = $this->users->find($userId);

        if ($user === null || $user['status'] !== 'pending') {
            throw new DomainException('This account is no longer waiting for approval.');
        }

        return $user;
    }
}
