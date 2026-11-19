<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\UserRepository;

/**
 * Runs on every request, because public routes such as /apply also act for whoever is logged in (section 11). Ends a
 * login that has gone idle, whose account stopped being active, or whose password changed since it started.
 */
final class SessionGuard
{
    public const REASON_IDLE = 'idle';
    public const REASON_NOT_ACTIVE = 'account_not_active';
    public const REASON_PASSWORD_CHANGED = 'password_changed';

    private const FINGERPRINT_KEY = 'password_fingerprint';
    private const ACTIVITY_KEY = 'last_activity';

    private static bool $ended = false;

    public static function check(): void
    {
        $userId = current_user_id();

        if ($userId === null) {
            return;
        }

        $user = (new UserRepository())->find($userId);
        $reason = self::reasonToEnd(
            $user,
            (int) Session::get(self::ACTIVITY_KEY, 0),
            session_idle_minutes() * 60,
            Session::get(self::FINGERPRINT_KEY),
            time(),
        );

        if ($reason === null) {
            Session::set(self::ACTIVITY_KEY, time());

            return;
        }

        if ($reason === self::REASON_IDLE) {
            AuditService::log('session.timeout', 'user', $userId);
        } else {
            AuditService::log('session.ended', 'user', $userId, ['reason' => $reason]);
        }

        Session::destroy();
        self::$ended = true;
        flash('warning', match ($reason) {
            self::REASON_IDLE => 'You were logged out because your session was idle. Please log in again.',
            self::REASON_PASSWORD_CHANGED => 'Your password was changed, so please log in again.',
            default => 'Please log in to continue.',
        });
    }

    /** @param array<string, mixed>|null $user */
    public static function reasonToEnd(?array $user, int $lastActivity, int $idleSeconds, mixed $fingerprint, int $now): ?string
    {
        if ($user === null || $user['status'] !== 'active') {
            return self::REASON_NOT_ACTIVE;
        }

        if (!is_string($fingerprint) || !hash_equals(self::fingerprint((string) $user['password_hash']), $fingerprint)) {
            return self::REASON_PASSWORD_CHANGED;
        }

        return $now - $lastActivity > $idleSeconds ? self::REASON_IDLE : null;
    }

    /** True when this request's login was just ended, so the page can send the person to log in. */
    public static function justEnded(): bool
    {
        return self::$ended;
    }

    /** Ties this session to the current password. A new hash, from any device or a forced reset, ends every other login. */
    public static function remember(string $passwordHash): void
    {
        Session::set(self::FINGERPRINT_KEY, self::fingerprint($passwordHash));
    }

    private static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }
}
