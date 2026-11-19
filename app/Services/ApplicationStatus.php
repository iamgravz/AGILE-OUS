<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

/** Allowed membership application transitions from PROJECT_CONTEXT.md section 8. */
final class ApplicationStatus
{
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['under_review', 'withdrawn'],
        'under_review' => ['approved', 'rejected', 'for_revision'],
        'for_revision' => ['submitted'],
        'approved' => [],
        'rejected' => [],
        'withdrawn' => [],
    ];

    public const ALL = ['draft', 'submitted', 'under_review', 'for_revision', 'approved', 'rejected', 'withdrawn'];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new DomainException("An application cannot move from {$from} to {$to}.");
        }
    }
}
