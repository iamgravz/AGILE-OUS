<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\ApplicantRepository;
use App\Models\ApplicationAccessLinkRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\UserRepository;

/**
 * How someone without an account proves an application is theirs (BR-18): an emailed code at the start, or an
 * emailed link that opens one application. Knowing a tracking code and email is never enough to edit.
 */
final class ApplicantAccessService
{
    public const OUTCOME_OK = 'ok';
    public const OUTCOME_HAS_ACCOUNT = 'has_account';
    public const OUTCOME_ACTIVE = 'active';

    public const PURPOSE_DRAFT = 'resume_draft';
    public const PURPOSE_REVISE = 'revise';
    public const PURPOSE_WITHDRAW = 'withdraw';

    private const SESSION_KEY = 'applicant_access';
    private const LINK_HOURS = 72;
    private const MAX_LINK_REQUESTS = 3;
    private const LINK_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly ApplicantRepository $applicants = new ApplicantRepository(),
        private readonly ApplicationAccessLinkRepository $links = new ApplicationAccessLinkRepository(),
        private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    /** @return array{applicant_id: int, application_id: ?int, may_revise: bool}|null Who this visitor has proven to be. */
    public static function current(): ?array
    {
        $access = Session::get(self::SESSION_KEY);

        if (!is_array($access)) {
            return null;
        }

        // The same idle limit as a login (section 11). Their draft is saved, and a new code or link continues it.
        if ($access['last_activity'] + session_idle_minutes() * 60 < time()) {
            Session::set(self::SESSION_KEY, null);

            return null;
        }

        $access['last_activity'] = time();
        Session::set(self::SESSION_KEY, $access);

        return $access;
    }

    /** Ties a new draft to this visit. */
    public static function bindApplication(int $applicationId): void
    {
        $access = self::current();

        if ($access !== null) {
            Session::set(self::SESSION_KEY, ['application_id' => $applicationId] + $access);
        }
    }

    /** Submitting ends the right to revise, which then needs a fresh link. */
    public static function endRevision(): void
    {
        $access = self::current();

        if ($access !== null) {
            Session::set(self::SESSION_KEY, ['may_revise' => false] + $access);
        }
    }

    /**
     * The code proved the email is theirs, so only now may they learn it has an account or an active application.
     *
     * @param array{email: string, first_name: string, last_name: string, student_no: string} $details
     * @return array{outcome: string, tracking_code?: string}
     */
    public function confirmEmail(array $details): array
    {
        // A registered account still waiting for approval cannot log in, so that person applies from here.
        $user = (new UserRepository())->findByEmail($details['email']);

        if ($user !== null && $user['status'] !== 'pending') {
            return ['outcome' => self::OUTCOME_HAS_ACCOUNT];
        }

        // Checked first: an active application under this student number is the clearer answer (BR-02).
        $active = $this->applications->findActiveGeneral($details['email'], $details['student_no'], 0);

        if ($active !== null) {
            return ['outcome' => self::OUTCOME_ACTIVE, 'tracking_code' => $active['tracking_code']];
        }

        $applicantId = $this->applicants->saveVerified($details);

        $draft = $this->applications->findDraftForApplicant($applicantId);
        $this->grant($applicantId, $draft === null ? null : (int) $draft['application_id'], false);
        // Consent is recorded here because the form that asked for it saves nothing until the code is confirmed.
        AuditService::log('applicant.email_verified', 'applicant', $applicantId, ['privacy_consent' => true]);

        return ['outcome' => self::OUTCOME_OK];
    }

    public function isLinkUsable(string $token): bool
    {
        $link = $this->usableLink($token);

        return $link !== null && $link['purpose'] !== self::PURPOSE_WITHDRAW;
    }

    public function isWithdrawalLinkUsable(string $token): bool
    {
        return ($this->usableLink($token)['purpose'] ?? null) === self::PURPOSE_WITHDRAW;
    }

    /** Uses the link and withdraws its application (section 8). False when the link cannot be used. */
    public function withdrawWithLink(string $token): bool
    {
        $link = $this->usableLink($token);

        if (($link['purpose'] ?? null) !== self::PURPOSE_WITHDRAW) {
            AuditService::log('applicant.link_failed');

            return false;
        }

        return Database::transaction(function () use ($link): bool {
            if (!$this->links->consume((int) $link['link_id'])) {
                return false;
            }

            (new MembershipService())->withdrawByLink((int) $link['application_id']);

            return true;
        });
    }

    /** Opens the one application the link is for. Returns its id, or null when the link cannot be used. */
    public function openLink(string $token): ?int
    {
        $link = $this->usableLink($token);

        if ($link === null || $link['purpose'] === self::PURPOSE_WITHDRAW || !$this->links->consume((int) $link['link_id'])) {
            AuditService::log('applicant.link_failed');

            return null;
        }

        $applicationId = (int) $link['application_id'];
        $this->grant((int) $link['applicant_id'], $applicationId, $link['purpose'] === self::PURPOSE_REVISE);
        AuditService::log('applicant.link_opened', 'membership_application', $applicationId, ['purpose' => $link['purpose']]);

        return $applicationId;
    }

    /** One live link at a time is enough, so saving a draft many times sends one email. */
    public function sendDraftLink(int $applicationId): void
    {
        if (!$this->links->hasUsable($applicationId, self::PURPOSE_DRAFT)) {
            $this->emailLink($applicationId, self::PURPOSE_DRAFT, 'Your application draft is saved. Use this link to continue it.', 'Continue my application');
        }
    }

    /** @return string A path holding a new edit link, for the APP_FOR_REVISION email. Older links stop working. */
    public function newRevisionLink(int $applicationId): string
    {
        return $this->issue($applicationId, self::PURPOSE_REVISE);
    }

    /**
     * For /track. The caller has already matched the code and email. Sends nothing once the limit is reached.
     */
    public function emailRevisionLink(int $applicationId, string $ip): bool
    {
        if ($this->limiter->hit("edit_link:{$ip}:{$applicationId}", self::MAX_LINK_REQUESTS, self::LINK_WINDOW_MINUTES)) {
            AuditService::log('applicant.link_limited', 'membership_application', $applicationId);

            return false;
        }

        $this->emailLink($applicationId, self::PURPOSE_REVISE, 'You asked for a link to revise your application.', 'Revise my application');

        return true;
    }

    /** For /track, while the application is Submitted. Sends nothing once the limit is reached. */
    public function emailWithdrawalLink(int $applicationId, string $ip): bool
    {
        if ($this->limiter->hit("edit_link:{$ip}:{$applicationId}", self::MAX_LINK_REQUESTS, self::LINK_WINDOW_MINUTES)) {
            AuditService::log('applicant.link_limited', 'membership_application', $applicationId);

            return false;
        }

        $this->emailLink($applicationId, self::PURPOSE_WITHDRAW, 'You asked for a link to withdraw your application.', 'Withdraw my application');

        return true;
    }

    private function emailLink(int $applicationId, string $purpose, string $message, string $action): void
    {
        Database::transaction(function () use ($applicationId, $purpose, $message, $action): void {
            $application = $this->applications->find($applicationId);
            $applicant = $this->applicants->find((int) $application['applicant_id']);

            // The link goes in the payload only, because the queue erases that after sending.
            $this->notifications->notifyEmail($applicant['email'], 'APPLICATION_LINK', [
                'name' => $applicant['first_name'],
                'message' => $message,
                'action' => $action,
                'link' => $this->issue($applicationId, $purpose),
            ]);
            AuditService::log('applicant.link_sent', 'membership_application', $applicationId, ['purpose' => $purpose]);
        });
    }

    /** Only the hash is stored. */
    private function issue(int $applicationId, string $purpose): string
    {
        $this->links->revokeAll($applicationId, $purpose);

        $token = bin2hex(random_bytes(32));
        $this->links->insert([
            'application_id' => $applicationId,
            'token_hash' => hash('sha256', $token),
            'purpose' => $purpose,
            'expires_at' => date('Y-m-d H:i:s', time() + self::LINK_HOURS * 3600),
        ]);

        return ($purpose === self::PURPOSE_WITHDRAW ? '/apply/withdraw?token=' : '/apply/continue?token=') . $token;
    }

    /** @return array<string, mixed>|null A link that still fits its application's status. */
    private function usableLink(string $token): ?array
    {
        $link = $this->links->findUsable(hash('sha256', $token));
        $expected = match ($link['purpose'] ?? null) {
            self::PURPOSE_REVISE => 'for_revision',
            self::PURPOSE_WITHDRAW => 'submitted',
            default => 'draft',
        };

        return $link !== null && $link['application_status'] === $expected ? $link : null;
    }

    private function grant(int $applicantId, ?int $applicationId, bool $mayRevise): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, [
            'applicant_id' => $applicantId,
            'application_id' => $applicationId,
            'may_revise' => $mayRevise,
            'last_activity' => time(),
        ]);
    }
}
