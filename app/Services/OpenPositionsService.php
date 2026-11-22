<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DomainException;

/**
 * Open for application: the MSW Committee Head decides which positions, from Committee Head to The Source Code,
 * applicants can choose on the first apply page, and sets each one's target headcount (AGILE's Positions &
 * Recruitment Dashboard). A position that reaches its target leaves the list by itself.
 */
final class OpenPositionsService
{
    public const MAX_TARGET = 99;

    public function __construct(private readonly MembershipService $membership = new MembershipService())
    {
    }

    /**
     * @return array{positions: list<array<string, mixed>>, totals: array{target: int, filled: int, remaining: int, open: int}}
     */
    public function overview(): array
    {
        authorize('admin.settings');
        $positions = $this->membership->applicationPositions();

        return [
            'positions' => $positions,
            'totals' => [
                'target' => array_sum(array_map(static fn (array $p): int => (int) $p['target_slots'], $positions)),
                'filled' => array_sum(array_column($positions, 'filled')),
                'remaining' => array_sum(array_map(static fn (array $p): int => (int) $p['remaining'], $positions)),
                'open' => count($this->membership->openPositions()),
            ],
        ];
    }

    /**
     * Saves every position on the page at once.
     *
     * @param list<int|string> $openIds the positions ticked as open
     * @param array<int|string, mixed> $targets position_id => target headcount ('' for no target)
     * @throws DomainException
     */
    public function save(array $openIds, array $targets): void
    {
        authorize('admin.settings');

        $open = array_map('intval', $openIds);
        $changes = [];

        Database::transaction(function () use ($open, $targets, &$changes): void {
            $update = Database::connection()->prepare('UPDATE positions SET is_open = ?, target_slots = ? WHERE position_id = ?');

            foreach ($this->membership->applicationPositions() as $position) {
                $id = $position['position_id'];
                $raw = trim((string) ($targets[$id] ?? $targets[(string) $id] ?? ''));

                if ($raw !== '' && (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw > self::MAX_TARGET)) {
                    throw new DomainException("The target for {$position['label']} must be a whole number from 0 to " . self::MAX_TARGET . ', or empty for no limit.');
                }

                $target = $raw === '' ? null : (int) $raw;
                $isOpen = in_array($id, $open, true);

                if ($isOpen !== $position['is_open'] || $target !== $position['target_slots']) {
                    $update->execute([$isOpen ? 1 : 0, $target, $id]);
                    $changes[] = ['position' => $position['label'], 'open' => $isOpen, 'target' => $target];
                }
            }
        });

        if ($changes !== []) {
            AuditService::log('positions.open_updated', 'position', null, ['changes' => $changes]);
        }
    }
}
