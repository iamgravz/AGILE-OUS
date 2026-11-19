<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Models\ApplicantRepository;
use App\Models\CommitteeRepository;
use App\Models\MemberRepository;
use App\Models\PositionRepository;
use App\Models\RoleRepository;
use App\Models\TokenRepository;
use App\Models\UserRepository;
use DomainException;

final class AuthService
{
    public const LOGIN_OK = 'ok';
    public const LOGIN_PENDING = 'pending';
    public const LOGIN_FAILED = 'failed';

    private const MAX_FAILED_LOGINS = 5;
    private const LOCK_MINUTES = 15;
    private const TOKEN_MINUTES = 60;
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_MINUTES = 15;
    // Across all emails from one address, which stops one source trying a password on many accounts.
    private const LOGIN_MAX_PER_IP = 50;
    private const SETUP_TOKEN_HOURS = 72;

    private const TOKEN_SETUP = 'account_setup';
    private const TOKEN_RESET = 'password_reset';

    // Checked when the email is unknown or locked, so those cases take as long as a wrong password.
    private const DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuuOQK3s6pbzYb8kbZ1QKzZ9Gm2W3Vq9xS';

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly TokenRepository $tokens = new TokenRepository(),
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    /**
     * BR-18: creates the general_member account of an approved applicant whose code was confirmed, and links the member
     * record, the applicant and earlier guest concerns. The account waits for the MSW Committee Head (BR-21).
     *
     * @throws DomainException when the member already has an account, or the email or student number does
     */
    public function registerMember(int $memberId, string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        Database::transaction(function () use ($memberId, $hash): void {
            $members = new MemberRepository();
            $members->lockRow($memberId);
            $member = $members->find($memberId);
            $applicants = new ApplicantRepository();
            $applicant = $member === null || $member['applicant_id'] === null ? null : $applicants->find((int) $member['applicant_id']);

            if ($applicant === null || $member['user_id'] !== null) {
                throw new DomainException('This member already has an account. Please log in.');
            }

            if ($this->users->findByEmail($applicant['email']) !== null || $this->users->studentNoExists((string) $applicant['student_no'])) {
                throw new DomainException('This email or student number already has an account. Please contact the MSW Committee.');
            }

            // A member who already holds a position (an officer imported from AGILE's records, or appointed before
            // registering) gets that position's role, not General Member.
            $position = $member['position_id'] === null ? null : (new PositionRepository())->findWithCommittee((int) $member['position_id']);

            $userId = $this->users->insert([
                'role_id' => $this->roles->findBySlug(MembershipService::roleSlugFor($position))['role_id'],
                'email' => $applicant['email'],
                'password_hash' => $hash,
                'first_name' => $applicant['first_name'],
                'last_name' => $applicant['last_name'],
                'student_no' => $applicant['student_no'],
                'contact_no' => (string) $applicant['contact_no'],
                'status' => 'pending',
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);
            $applicants->update((int) $applicant['applicant_id'], ['user_id' => $userId]);
            $members->update($memberId, ['user_id' => $userId, 'registered_at' => date('Y-m-d H:i:s')]);

            if (($position['role_type'] ?? null) === 'committee_head' && $position['head_user_id'] === null) {
                (new CommitteeRepository())->update((int) $position['committee_id'], ['head_user_id' => $userId]);
            }

            // Both sides proved this email with a code, so their earlier guest concerns join the account.
            (new WelfareService())->linkGuestCases($userId, $applicant['email']);

            // Consent is recorded here because the users table has no consent column.
            AuditService::log('member.registered', 'user', $userId, ['member_id' => $memberId, 'privacy_consent' => true]);
            $this->notifications->notify($userId, 'MEMBER_REGISTERED', ['member_no' => $member['member_no']], null, ['email']);
        });
    }

    /**
     * An officer's account, created by the MSW Committee Head (BR-18), active at once. An empty hash means no password
     * yet: no login can match it until they use the emailed link. The CLI job runs with nobody logged in.
     *
     * @throws DomainException
     */
    public function createAccount(string $email, string $firstName, string $lastName, string $roleSlug, ?string $studentNo = null): int
    {
        if (PHP_SAPI !== 'cli') {
            authorize('admin.users');
        }

        $email = $this->normalizeEmail($email);
        $role = $this->roles->findBySlug($roleSlug);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Please give a valid email address.');
        }

        if ($role === null || $roleSlug === 'guest') {
            throw new DomainException('Please give a role that has an account.');
        }

        if ($this->users->findByEmail($email) !== null || ($studentNo !== null && $this->users->studentNoExists($studentNo))) {
            throw new DomainException('This email or student number already has an account.');
        }

        return Database::transaction(function () use ($email, $firstName, $lastName, $role, $studentNo): int {
            $userId = $this->users->insert([
                'role_id' => $role['role_id'],
                'email' => $email,
                'password_hash' => '',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'student_no' => $studentNo,
                'contact_no' => '',
                'status' => 'active',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'approved_by' => current_user_id(),
                'approved_at' => date('Y-m-d H:i:s'),
            ]);
            AuditService::log('account.created', 'user', $userId, ['role' => $role['slug']]);

            $token = $this->issueToken($userId, self::TOKEN_SETUP, self::SETUP_TOKEN_HOURS * 60);
            // The link goes in the payload only, because the queue erases that after sending.
            $this->notifications->notify($userId, 'ACCOUNT_SETUP', ['link' => '/account/setup?token=' . $token], null, ['email']);

            return $userId;
        });
    }

    public function isSetupTokenValid(string $token): bool
    {
        return $this->tokens->findUsable($this->hashToken($token), self::TOKEN_SETUP) !== null;
    }

    public function setPassword(string $token, string $password): bool
    {
        $row = $this->tokens->findUsable($this->hashToken($token), self::TOKEN_SETUP);

        if ($row === null) {
            AuditService::log('account.setup_failed');

            return false;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        return Database::transaction(function () use ($row, $hash): bool {
            if (!$this->tokens->consume((int) $row['token_id'])) {
                return false;
            }

            $userId = (int) $row['user_id'];
            $this->users->update($userId, ['password_hash' => $hash, 'failed_logins' => 0, 'locked_until' => null]);
            AuditService::log('account.password_set', 'user', $userId);

            return true;
        });
    }

    /**
     * Unknown email, wrong password, locked account and rate limit all return LOGIN_FAILED, and the page shows
     * one message for them. LOGIN_PENDING is only returned to someone who gave the correct password.
     */
    public function attempt(string $email, string $password, string $ip): string
    {
        $email = $this->normalizeEmail($email);
        $key = "login:{$ip}|{$email}";
        $ipKey = "login_ip:{$ip}";

        if ($this->limiter->tooMany($key, self::RATE_LIMIT_MAX, self::RATE_LIMIT_MINUTES)
            || $this->limiter->tooMany($ipKey, self::LOGIN_MAX_PER_IP, self::RATE_LIMIT_MINUTES)) {
            AuditService::log('login.rate_limited', null, null, ['email' => $email]);

            return self::LOGIN_FAILED;
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            password_verify($password, self::DUMMY_HASH);
            $this->limiter->hit($key, self::RATE_LIMIT_MAX, self::RATE_LIMIT_MINUTES);
            $this->limiter->hit($ipKey, self::LOGIN_MAX_PER_IP, self::RATE_LIMIT_MINUTES);
            AuditService::log('login.failed', null, null, ['email' => $email, 'reason' => 'unknown_email']);

            return self::LOGIN_FAILED;
        }

        $userId = (int) $user['user_id'];

        if ($user['locked_until'] !== null && $user['locked_until'] > date('Y-m-d H:i:s')) {
            password_verify($password, self::DUMMY_HASH);
            AuditService::log('login.failed', 'user', $userId, ['reason' => 'locked']);

            return self::LOGIN_FAILED;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->limiter->hit($key, self::RATE_LIMIT_MAX, self::RATE_LIMIT_MINUTES);
            $this->limiter->hit($ipKey, self::LOGIN_MAX_PER_IP, self::RATE_LIMIT_MINUTES);
            $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60);
            AuditService::log('login.failed', 'user', $userId, ['reason' => 'wrong_password']);

            if ($this->users->recordFailedLogin($userId, self::MAX_FAILED_LOGINS, $lockedUntil)) {
                AuditService::log('login.lockout', 'user', $userId, ['locked_until' => $lockedUntil]);
            }

            return self::LOGIN_FAILED;
        }

        if ($user['status'] === 'pending') {
            AuditService::log('login.failed', 'user', $userId, ['reason' => 'pending']);

            return self::LOGIN_PENDING;
        }

        if ($user['status'] !== 'active') {
            AuditService::log('login.failed', 'user', $userId, ['reason' => $user['status']]);

            return self::LOGIN_FAILED;
        }

        $this->limiter->clear($key);
        $this->startSession($user);
        AuditService::log('login.success', 'user', $userId);

        return self::LOGIN_OK;
    }

    /** Local development only: logs in as a seeded demo account without a password. */
    public function loginAsDemoUser(string $email): bool
    {
        if (!dev_tools_allowed() || !str_ends_with($email, '@agile.test')) {
            return false;
        }

        $user = $this->users->findByEmail($email);

        if ($user === null || $user['status'] !== 'active') {
            return false;
        }

        $this->startSession($user);
        AuditService::log('login.dev_switch', 'user', (int) $user['user_id']);

        return true;
    }

    /** @param array<string, mixed> $user */
    private function startSession(array $user): void
    {
        $userId = (int) $user['user_id'];

        $this->users->update($userId, [
            'failed_logins' => 0,
            'locked_until' => null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        Session::regenerate();
        Csrf::rotate();
        Session::set('user_id', $userId);
        Session::set('role', $user['role_slug']);
        Session::set('last_activity', time());
        SessionGuard::remember((string) $user['password_hash']);
    }

    public function logout(): void
    {
        AuditService::log('logout', 'user', current_user_id());
        Session::destroy();
    }

    /** Sends nothing for unknown or inactive accounts, and the page always shows the same message. */
    public function requestPasswordReset(string $email, string $ip): void
    {
        $email = $this->normalizeEmail($email);

        if ($this->limiter->hit("forgot:{$ip}|{$email}", self::RATE_LIMIT_MAX + 1, self::RATE_LIMIT_MINUTES)) {
            AuditService::log('password.reset_rate_limited');

            return;
        }

        $user = $this->users->findByEmail($email);

        if ($user === null || $user['status'] !== 'active') {
            return;
        }

        $userId = (int) $user['user_id'];

        Database::transaction(function () use ($userId): void {
            $token = $this->issueToken($userId, self::TOKEN_RESET);
            // The link goes in the payload only, because the queue erases that after sending.
            $this->notifications->notify($userId, 'PASSWORD_RESET', ['link' => '/reset-password?token=' . $token], null, ['email']);
            AuditService::log('password.reset_requested', 'user', $userId);
        });
    }

    /** The current password stops working, and the person gets a link to set a new one. The caller checks permission. */
    public function forcePasswordReset(int $userId): void
    {
        authorize('admin.users');

        $user = $this->users->find($userId);

        if ($user === null || $user['status'] !== 'active') {
            throw new DomainException('Only an active account can be reset.');
        }

        Database::transaction(function () use ($userId): void {
            $this->users->update($userId, ['password_hash' => '', 'failed_logins' => 0, 'locked_until' => null]);
            $token = $this->issueToken($userId, self::TOKEN_RESET);
            // The link goes in the payload only, because the queue erases that after sending.
            $this->notifications->notify($userId, 'PASSWORD_RESET', ['link' => '/reset-password?token=' . $token], null, ['email']);
            AuditService::log('password.reset_forced', 'user', $userId);
        });
    }

    public function isResetTokenValid(string $token): bool
    {
        return $this->tokens->findUsable($this->hashToken($token), self::TOKEN_RESET) !== null;
    }

    public function resetPassword(string $token, string $password): bool
    {
        $row = $this->tokens->findUsable($this->hashToken($token), self::TOKEN_RESET);

        if ($row === null) {
            AuditService::log('password.reset_failed');

            return false;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        return Database::transaction(function () use ($row, $hash): bool {
            if (!$this->tokens->consume((int) $row['token_id'])) {
                return false;
            }

            $userId = (int) $row['user_id'];
            $this->tokens->revokeAll($userId, self::TOKEN_RESET);
            $this->users->update($userId, ['password_hash' => $hash, 'failed_logins' => 0, 'locked_until' => null]);
            AuditService::log('password.reset', 'user', $userId);

            return true;
        });
    }

    /** Only the hash is stored. A new token cancels older ones of the same type. */
    private function issueToken(int $userId, string $type, int $minutes = self::TOKEN_MINUTES): string
    {
        $this->tokens->revokeAll($userId, $type);

        $token = bin2hex(random_bytes(32));
        $this->tokens->insert([
            'user_id' => $userId,
            'type' => $type,
            'token_hash' => $this->hashToken($token),
            'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
        ]);

        return $token;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
