<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

/** Allowed notification transitions from PROJECT_CONTEXT.md section 8. */
final class NotificationStatus
{
    public const QUEUED = 'queued';
    public const SENT = 'sent';
    public const FAILED = 'failed';

    private const TRANSITIONS = [
        self::QUEUED => [self::SENT, self::FAILED],
        self::FAILED => [self::QUEUED],
        self::SENT => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new DomainException("Notification cannot move from {$from} to {$to}.");
        }
    }
}
