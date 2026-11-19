<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MemberRepository;

/**
 * BR-18: an approved applicant registers with their PUP student number. The code goes to the email on their approved
 * application, so knowing a student number is not enough. AuthService::registerMember() creates the account.
 */
final class MemberRegistrationService
{
    private const MAX_REQUESTS = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(
        private readonly MemberRepository $members = new MemberRepository(),
        private readonly GuestVerificationService $verification = new GuestVerificationService(),
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    /**
     * Sends a code only for an approved applicant with no account yet. The caller shows one message for every outcome
     * except the limit, so this page cannot be used to test student numbers.
     *
     * @return bool False when the limit stopped it, which every student number reaches the same way.
     */
    public function requestCode(string $studentNo, string $ip): bool
    {
        // Both count, so a blocked address cannot hide how often a student number is tried.
        $blockedForIp = $this->limiter->hit("register:{$ip}", self::MAX_REQUESTS + 1, self::WINDOW_MINUTES);
        $blockedForNumber = $this->limiter->hit("register_no:{$studentNo}", self::MAX_REQUESTS + 1, self::WINDOW_MINUTES);

        if ($blockedForIp || $blockedForNumber) {
            AuditService::log('register.rate_limited');

            return false;
        }

        $member = $this->members->findUnregisteredByStudentNo($studentNo);

        return $member === null || $this->verification->requestCode($member['email'], $ip, GuestVerificationService::PURPOSE_MEMBER);
    }

    /** @return int|null The member the code belongs to, or null for a wrong, used or expired code or an unknown number. */
    public function confirmCode(string $studentNo, string $code): ?int
    {
        $member = $this->members->findUnregisteredByStudentNo($studentNo);

        if ($member === null || $this->verification->verify($member['email'], $code, GuestVerificationService::PURPOSE_MEMBER) !== GuestVerificationService::RESULT_OK) {
            return null;
        }

        return (int) $member['member_id'];
    }
}
