<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MemberRepository;
use App\Models\PositionRepository;
use App\Models\RoleRepository;
use App\Models\UserRepository;
use DomainException;

/**
 * A second standing committee (Article VI Section 4-A). Every member keeps one main position in
 * members.position_id. A Committee Member or a Year-Level Representative may also be a Committee Member of one other
 * standing committee, kept in member_committees. The Executive Board, Committee Heads and Deputies hold one office only.
 * The role stays the main position's, except that MSW committee work gives an MSW Member role to someone who would
 * otherwise be a General Member.
 */
final class MemberCommitteeService
{
    /** Two standing committees at most: the main one and one more. */
    public const MAX_EXTRA = 1;

    /** Main positions that may take a second committee. */
    private const MAY_ADD = ['committee_member'];

    /** Committees that are not standing committees (Article V): no second membership there. */
    private const NOT_STANDING = ['The Source Code', ElectedOfficerService::COUNCIL];

    /** @return list<array{committee_id: int, name: string, joined_at: string}> */
    public function extras(int $memberId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT mc.committee_id, c.name, mc.joined_at FROM member_committees mc JOIN committees c ON c.committee_id = mc.committee_id
             WHERE mc.member_id = ? ORDER BY c.name'
        );
        $statement->execute([$memberId]);

        return array_map(static fn (array $row): array => ['committee_id' => (int) $row['committee_id'], 'name' => (string) $row['name'], 'joined_at' => (string) $row['joined_at']], $statement->fetchAll());
    }

    /**
     * The standing committees this member could join as their second, or [] when their main position does not allow one.
     *
     * @return list<array{committee_id: int, name: string}>
     */
    public function options(int $memberId): array
    {
        $member = (new MemberRepository())->findDetailed($memberId);

        if ($member === null || !$this->mayHaveSecond($member) || count($this->extras($memberId)) >= self::MAX_EXTRA) {
            return [];
        }

        $marks = implode(',', array_fill(0, count(self::NOT_STANDING), '?'));
        $statement = Database::connection()->prepare("SELECT committee_id, name FROM committees WHERE name NOT IN ({$marks}) AND committee_id <> ? ORDER BY name");
        $statement->execute([...self::NOT_STANDING, (int) ($member['committee_id'] ?? 0)]);

        return array_map(static fn (array $row): array => ['committee_id' => (int) $row['committee_id'], 'name' => (string) $row['name']], $statement->fetchAll());
    }

    /** The MSW Committee Head adds a second committee from the member record. */
    public function add(int $memberId, int $committeeId, string $reason): void
    {
        authorize('admin.settings');

        if (trim($reason) === '') {
            throw new DomainException('Please write the reason, for example the approved application.');
        }

        Database::transaction(fn () => $this->join($memberId, $committeeId, trim($reason)));
    }

    /**
     * Approval of a Committee Member application from someone who already holds a main position: the second committee,
     * when the rules allow it. Returns false when they do not, and the caller leaves things as they are. The caller
     * holds the permission check and the transaction.
     */
    public function joinFromApplication(int $memberId, int $positionId, string $reason): bool
    {
        $position = (new PositionRepository())->findWithCommittee($positionId);

        if ($position === null || $position['role_type'] !== 'committee_member') {
            return false;
        }

        try {
            $this->join($memberId, (int) $position['committee_id'], $reason);
        } catch (DomainException) {
            return false;
        }

        return true;
    }

    public function remove(int $memberId, int $committeeId, string $reason): void
    {
        authorize('admin.settings');

        if (trim($reason) === '') {
            throw new DomainException('Please write the reason.');
        }

        Database::transaction(function () use ($memberId, $committeeId, $reason): void {
            $extra = array_values(array_filter($this->extras($memberId), static fn (array $row): bool => $row['committee_id'] === $committeeId))[0] ?? null;

            if ($extra === null) {
                throw new DomainException('This member is not in that committee as a second committee.');
            }

            Database::connection()->prepare('DELETE FROM member_committees WHERE member_id = ? AND committee_id = ?')->execute([$memberId, $committeeId]);
            $this->syncRole($memberId);
            (new MemberRepository())->addHistory($memberId, 'Committee Member, ' . $extra['name'], 'Left the ' . $extra['name'] . ' Committee', trim($reason), (int) current_user_id());
            AuditService::log('member.committee_removed', 'member', $memberId, ['committee_id' => $committeeId, 'reason' => mb_substr(trim($reason), 0, 255)]);
        });
    }

    /**
     * The role the main position gives, raised to MSW Member when a second committee is the MSW committee and the main
     * position would leave them a General Member.
     */
    public function roleFor(array $member, array $extras): string
    {
        $position = $member['position_id'] === null ? null : (new PositionRepository())->findWithCommittee((int) $member['position_id']);
        $slug = MembershipService::roleSlugFor($position);
        $msw = config('role_mapping.msw_committee');

        if ($slug === 'general_member' && in_array($msw, array_column($extras, 'name'), true)) {
            return 'msw_member';
        }

        return $slug;
    }

    /**
     * After the main position changes: a position that allows no second committee ends it, with a history line, and the
     * role is worked out again. The caller holds the transaction.
     */
    public function afterMainChange(int $memberId, string $reason): void
    {
        $member = (new MemberRepository())->findDetailed($memberId);

        if ($member === null || $this->extras($memberId) === []) {
            return;
        }

        if (!$this->mayHaveSecond($member)) {
            foreach ($this->extras($memberId) as $extra) {
                Database::connection()->prepare('DELETE FROM member_committees WHERE member_id = ? AND committee_id = ?')->execute([$memberId, $extra['committee_id']]);
                (new MemberRepository())->addHistory($memberId, 'Committee Member, ' . $extra['name'], 'Left the ' . $extra['name'] . ' Committee (one office only)', trim($reason), (int) current_user_id());
            }
        }

        $this->syncRole($memberId);
    }

    private function join(int $memberId, int $committeeId, string $reason): void
    {
        $members = new MemberRepository();
        $member = $members->findDetailed($memberId) ?? throw new DomainException('This member no longer exists.');

        if (!$this->mayHaveSecond($member)) {
            throw new DomainException('Only a Committee Member or a Year-Level Representative may join a second committee. The Executive Board, Committee Heads and Deputies hold one office (Article VI, Section 4-A).');
        }

        $allowed = array_column($this->options($memberId), 'name', 'committee_id');

        if (!isset($allowed[$committeeId])) {
            throw new DomainException(count($this->extras($memberId)) >= self::MAX_EXTRA
                ? 'This member is already in two committees, the most allowed.'
                : 'Please choose a standing committee other than their own.');
        }

        Database::connection()->prepare('INSERT INTO member_committees (member_id, committee_id, joined_at, added_by) VALUES (?, ?, ?, ?)')
            ->execute([$memberId, $committeeId, date('Y-m-d'), current_user_id()]);
        $this->syncRole($memberId);
        $members->addHistory($memberId, MembershipService::roleLabel($member), 'Also Committee Member, ' . $allowed[$committeeId], $reason, (int) current_user_id());
        AuditService::log('member.committee_added', 'member', $memberId, ['committee_id' => $committeeId]);
    }

    /** @param array<string, mixed> $member */
    private function mayHaveSecond(array $member): bool
    {
        if ($member['position_id'] === null) {
            return false;
        }

        return in_array($member['role_type'], self::MAY_ADD, true)
            || ($member['role_type'] === 'general' && ($member['committee_name'] ?? null) === ElectedOfficerService::COUNCIL);
    }

    private function syncRole(int $memberId): void
    {
        $member = (new MemberRepository())->findDetailed($memberId);

        if ($member === null || $member['user_id'] === null) {
            return;
        }

        $slug = $this->roleFor($member, $this->extras($memberId));
        (new UserRepository())->update((int) $member['user_id'], ['role_id' => (new RoleRepository())->findBySlug($slug)['role_id']]);
        RbacService::clearCache();
    }
}
