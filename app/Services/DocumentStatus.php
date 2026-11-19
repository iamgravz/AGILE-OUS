<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

/** Allowed applicant document transitions from PROJECT_CONTEXT.md section 8. */
final class DocumentStatus
{
    private const TRANSITIONS = [
        'pending' => ['verified', 'needs_correction', 'rejected'],
        'needs_correction' => ['pending'],
        'verified' => [],
        'rejected' => [],
    ];

    public const ALL = ['pending', 'verified', 'needs_correction', 'rejected'];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new DomainException("A document cannot move from {$from} to {$to}.");
        }
    }
}
