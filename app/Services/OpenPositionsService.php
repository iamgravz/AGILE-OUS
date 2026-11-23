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

    /**
     * Every position the MSW Committee Head can open for application, with AGILE's dashboard numbers: target, filled
     * (active holders) and remaining. Committees in name order, The Source Code last.
     *
     * @return list<array{position_id: int, title: string, role_type: string, committee_id: int, committee_name: string, is_open: bool, target_slots: ?int, filled: int, remaining: ?int, label: string}>
     */
    public static function all(): array
    {
        $marks = implode(',', array_fill(0, count(MembershipService::APPLICATION_ROLE_TYPES), '?'));
        $statement = Database::connection()->prepare(
            "SELECT p.position_id, p.title, p.role_type, p.is_open, p.target_slots, c.committee_id, c.name AS committee_name,
                    (SELECT COUNT(*) FROM members m WHERE m.position_id = p.position_id AND m.role_status = 'active')
                    + IF(p.role_type = 'committee_member', (SELECT COUNT(*) FROM member_committees mc WHERE mc.committee_id = p.committee_id), 0) AS filled
             FROM positions p JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.role_type IN ({$marks})
             ORDER BY p.role_type = 'source_code', c.name, FIELD(p.role_type, 'committee_head', 'deputy', 'committee_member'), p.position_id"
        );
        $statement->execute(MembershipService::APPLICATION_ROLE_TYPES);

        return array_map(static function (array $row): array {
            $target = $row['target_slots'] === null ? null : (int) $row['target_slots'];
            $filled = (int) $row['filled'];

            return [
                'position_id' => (int) $row['position_id'],
                'title' => (string) $row['title'],
                'role_type' => (string) $row['role_type'],
                'committee_id' => (int) $row['committee_id'],
                'committee_name' => (string) $row['committee_name'],
                'is_open' => (int) $row['is_open'] === 1,
                'target_slots' => $target,
                'filled' => $filled,
                'remaining' => $target === null ? null : max(0, $target - $filled),
                'label' => $row['title'] . ', ' . $row['committee_name'],
            ];
        }, $statement->fetchAll());
    }

    /**
     * What applicants can choose: positions that are open and not yet full (no target means no limit).
     *
     * @return list<array<string, mixed>> Rows of all().
     */
    public static function openNow(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $p): bool => $p['is_open'] && ($p['remaining'] === null || $p['remaining'] > 0)
        ));
    }

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
