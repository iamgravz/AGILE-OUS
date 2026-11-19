<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\GuestVerificationRepository;

/** The 6-digit email code a guest enters before a concern or application (BR-17, BR-18). Only its hash is kept. */
final class GuestVerificationService
{
    public const RESULT_OK = 'ok';
    public const RESULT_WRONG = 'wrong';
    public const RESULT_EXPIRED = 'expired';
    public const RESULT_LOCKED = 'locked';

    public const PURPOSE_WELFARE = 'welfare_submit';
    public const PURPOSE_APPLICATION = 'application_submit';
    public const PURPOSE_MEMBER = 'member_registration';

    private const EXPIRY_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;
    private const MAX_REQUESTS = 5;
    private const MAX_REQUESTS_PER_IP = 20;
    private const MAX_SUBMISSIONS = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(
        private readonly GuestVerificationRepository $verifications = new GuestVerificationRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Sends a new code, which replaces any earlier one. Returns false when the rate limit stops it: per IP and
     * email as section 11 asks, plus a wider limit per IP so one address cannot mail many strangers.
     */
    public function requestCode(string $email, string $ip, string $purpose = self::PURPOSE_WELFARE): bool
    {
        $blockedForEmail = $this->limiter->hit("guest_code:{$ip}:{$email}", self::MAX_REQUESTS, self::WINDOW_MINUTES);
        $blockedForIp = $this->limiter->hit("guest_code_ip:{$ip}", self::MAX_REQUESTS_PER_IP, self::WINDOW_MINUTES);

        if ($blockedForEmail || $blockedForIp) {
            AuditService::log($this->action($purpose, 'limited'));

            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Database::transaction(function () use ($email, $code, $purpose): void {
            $id = $this->verifications->insert([
                'email' => $email,
                'code_hash' => self::hash($code),
                'purpose' => $purpose,
                'expires_at' => date('Y-m-d H:i:s', time() + self::EXPIRY_MINUTES * 60),
            ]);
            $this->notifications->notifyEmail($email, $purpose === self::PURPOSE_MEMBER ? 'MEMBER_REGISTRATION_CODE' : 'GUEST_EMAIL_CODE', ['code' => $code]);
            AuditService::log($this->action($purpose, 'sent'), 'guest_verification', $id);
        });

        return true;
    }

    /** Checks the email's latest code. A wrong code is saved as an attempt, so it must not roll back. */
    public function verify(string $email, string $code, string $purpose = self::PURPOSE_WELFARE): string
    {
        return Database::transaction(function () use ($email, $code, $purpose): string {
            $row = $this->verifications->lockLatest($email, $purpose);

            if ($row === null || $row['verified_at'] !== null || $row['expires_at'] <= date('Y-m-d H:i:s')) {
                return self::RESULT_EXPIRED;
            }

            if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
                return self::RESULT_LOCKED;
            }

            $id = (int) $row['verification_id'];

            if (!hash_equals($row['code_hash'], self::hash($code))) {
                $attempts = (int) $row['attempts'] + 1;
                $this->verifications->update($id, ['attempts' => $attempts]);
                AuditService::log($this->action($purpose, 'failed'), 'guest_verification', $id);

                return $attempts >= self::MAX_ATTEMPTS ? self::RESULT_LOCKED : self::RESULT_WRONG;
            }

            $this->verifications->update($id, ['verified_at' => date('Y-m-d H:i:s')]);
            AuditService::log($this->action($purpose, 'verified'), 'guest_verification', $id);

            return self::RESULT_OK;
        });
    }

    private function action(string $purpose, string $event): string
    {
        return match ($purpose) {
            self::PURPOSE_WELFARE => 'welfare.guest_code_',
            self::PURPOSE_MEMBER => 'register.code_',
            default => 'application.guest_code_',
        } . $event;
    }

    /** Counts one guest submission and returns true when this address and email are over the limit. */
    public function submissionBlocked(string $email, string $ip): bool
    {
        return $this->limiter->hit("guest_welfare:{$ip}:{$email}", self::MAX_SUBMISSIONS, self::WINDOW_MINUTES);
    }
}
