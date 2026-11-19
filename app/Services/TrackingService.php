<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MembershipApplicationRepository;
use App\Models\WelfareCaseRepository;

/**
 * Public status lookup by code and email. A result holds only the type, status, date and a non-confidential
 * timeline. The repositories do not even select a case's category, subject or description.
 */
final class TrackingService
{
    private const MAX_TRIES = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(
        private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository(),
        private readonly WelfareCaseRepository $cases = new WelfareCaseRepository(),
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    /** Every try counts, right or wrong, so the limit also stops anyone guessing codes for a known email. */
    public function isBlocked(string $ip): bool
    {
        return $this->limiter->tooMany($this->key($ip), self::MAX_TRIES, self::WINDOW_MINUTES);
    }

    /**
     * Returns null when the code does not exist, or exists with another email. Callers show one message for both.
     *
     * @return array{type: string, status: string, submitted_at: string, timeline: list<array{time: string, status: ?string, note: ?string}>}|null
     */
    public function lookup(string $code, string $email, string $ip): ?array
    {
        $this->limiter->hit($this->key($ip), self::MAX_TRIES, self::WINDOW_MINUTES);

        $code = strtoupper(trim($code));
        $email = strtolower(trim($email));
        $result = $this->lookupApplication($code, $email) ?? $this->lookupCase($code, $email);

        if ($result === null) {
            AuditService::log('track.failed');
        }

        return $result;
    }

    /**
     * Emails a link, to the address on the application and never to the one typed here: to edit when it was returned
     * for revision, to withdraw while it is Submitted. The code and email only ask for it, which is why they alone
     * never change anything (BR-18). Each request counts toward the same limit as a lookup.
     *
     * @return bool False when nothing could be sent: no match, wrong status, or too many requests.
     */
    public function emailEditLink(string $code, string $email, string $ip): bool
    {
        $this->limiter->hit($this->key($ip), self::MAX_TRIES, self::WINDOW_MINUTES);
        $application = $this->applications->findTrackable(strtoupper(trim($code)));

        if ($application === null
            || !$this->sameEmail($application['owner_email'], strtolower(trim($email)))
            || !in_array($application['status'], ['for_revision', 'submitted'], true)
            || $application['applicant_id'] === null) {
            AuditService::log('track.edit_link_refused');

            return false;
        }

        $access = new ApplicantAccessService();
        $id = (int) $application['application_id'];

        return $application['status'] === 'for_revision' ? $access->emailRevisionLink($id, $ip) : $access->emailWithdrawalLink($id, $ip);
    }

    /** The same result as lookup(), for a page shown again right after a lookup that already counted. */
    public function refresh(string $code, string $email): ?array
    {
        return $this->lookupApplication(strtoupper(trim($code)), strtolower(trim($email)));
    }

    private function lookupApplication(string $code, string $email): ?array
    {
        $application = $this->applications->findTrackable($code);

        if ($application === null || !$this->sameEmail($application['owner_email'], $email)) {
            return null;
        }

        $id = (int) $application['application_id'];
        AuditService::log('application.tracked', 'membership_application', $id);

        return [
            'type' => 'Membership application',
            'code' => $code,
            // Only an applicant without an account needs the emailed link. A member revises after logging in.
            'may_request_link' => $application['status'] === 'for_revision' && $application['applicant_id'] !== null,
            'accepted_by' => $this->acceptedBy($id, $application['status']),
            'may_register' => $application['status'] === 'approved' && $application['applicant_id'] !== null && $application['applicant_user_id'] === null,
            'may_request_withdrawal' => $application['status'] === 'submitted' && $application['applicant_id'] !== null,
            'status' => $application['status'],
            'submitted_at' => $application['submitted_at'],
            'timeline' => array_map(
                static fn (array $row): array => ['time' => $row['changed_at'], 'status' => $row['new_status'], 'note' => null],
                $this->applications->statusHistory($id)
            ),
        ];
    }

    private function acceptedBy(int $id, string $status): ?string
    {
        if ($status !== 'approved') {
            return null;
        }

        $application = $this->applications->findDetailed($id);

        return (new MembershipService())->committeeDecider($application) === null ? null : $application['committee_name'];
    }

    private function lookupCase(string $code, string $email): ?array
    {
        $case = $this->cases->findTrackable($code);

        if ($case === null || !$this->sameEmail($case['owner_email'], $email)) {
            return null;
        }

        $id = (int) $case['case_id'];

        // Every view of a welfare case is logged in both places (section 11).
        $this->cases->recordView($id, current_user_id());
        AuditService::log('welfare.tracked', 'welfare_case', $id);

        return [
            'type' => 'Welfare concern',
            'code' => $code,
            'status' => $case['status'],
            'submitted_at' => $case['submitted_at'],
            'timeline' => (new WelfareService())->timeline($id),
        ];
    }

    private function sameEmail(?string $owner, string $email): bool
    {
        return $owner !== null && $email !== '' && hash_equals(strtolower($owner), $email);
    }

    private function key(string $ip): string
    {
        return "track:{$ip}";
    }
}
