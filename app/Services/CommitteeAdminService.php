<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CommitteeRepository;
use App\Models\MemberRepository;
use App\Models\PositionRepository;
use App\Models\UserRepository;
use DomainException;

/** Committees and positions, for admin.settings. Anything in use cannot be deleted. */
final class CommitteeAdminService
{
    public const ROLE_TYPES = ['officer', 'committee_head', 'deputy', 'committee_member', 'source_code', 'general'];

    public function __construct(
        private readonly CommitteeRepository $committees = new CommitteeRepository(),
        private readonly PositionRepository $positions = new PositionRepository(),
    ) {
    }

    /** @return array{committees: list<array<string, mixed>>, positions: list<array<string, mixed>>} */
    public function overview(): array
    {
        authorize('admin.settings');

        return ['committees' => $this->committees->withHeads(), 'positions' => $this->positions->withUsage()];
    }

    /** @return array<string, mixed> */
    public function committee(int $id): array
    {
        authorize('admin.settings');

        return $this->committees->find($id) ?? throw new DomainException('This committee no longer exists.');
    }

    /** @return array<string, mixed> */
    public function position(int $id): array
    {
        authorize('admin.settings');

        $position = $this->positions->findWithCommittee($id) ?? throw new DomainException('This position no longer exists.');
        $position['in_use'] = $this->positions->usageCount($id) > 0;

        return $position;
    }

    /** @return list<array<string, mixed>> Who can be made a committee head. */
    public function headCandidates(): array
    {
        authorize('admin.settings');

        return (new UserRepository())->activeForHead();
    }

    public function createCommittee(string $name, string $description): int
    {
        authorize('admin.settings');
        $this->assertNameFree($name, null);

        $id = $this->committees->insert(['name' => trim($name), 'description' => trim($description)]);
        AuditService::log('committee.created', 'committee', $id, ['name' => trim($name)]);

        return $id;
    }

    public function updateCommittee(int $id, string $name, string $description): void
    {
        authorize('admin.settings');

        $before = $this->committee($id);
        $this->assertNameFree($name, $id);

        if ($before['name'] === config('role_mapping.msw_committee') && trim($name) !== $before['name']) {
            throw new DomainException('The Membership and Student Welfare committee cannot be renamed, because its decision rules follow that name.');
        }

        $this->committees->update($id, ['name' => trim($name), 'description' => trim($description)]);
        AuditService::log('committee.updated', 'committee', $id, ['before' => $before['name'], 'after' => trim($name)]);
    }

    public function deleteCommittee(int $id): void
    {
        authorize('admin.settings');

        $committee = $this->committee($id);

        if ($committee['head_user_id'] !== null || $this->committees->usageCount($id) > 0) {
            throw new DomainException('This committee has a head, positions or HR requests, so it cannot be deleted.');
        }

        $this->committees->delete($id);
        AuditService::log('committee.deleted', 'committee', $id, ['name' => $committee['name']]);
    }

    /** Gives the person the committee's head position, through the one way a role changes. The previous head's role ends. */
    public function setHead(int $committeeId, int $userId, string $reason): void
    {
        authorize('admin.settings');

        if (trim($reason) === '') {
            throw new DomainException('Please write why the head is changing.');
        }

        Database::transaction(function () use ($committeeId, $userId, $reason): void {
            $committee = $this->committee($committeeId);
            $position = $this->positions->headOf($committeeId) ?? throw new DomainException('Add a position with the type Committee head to this committee first.');
            $user = (new UserRepository())->find($userId);

            if ($user === null || $user['status'] !== 'active') {
                throw new DomainException('Please choose an active account.');
            }

            $membership = new MembershipService();
            $members = new MemberRepository();
            $actorId = (int) current_user_id();
            $previous = $committee['head_user_id'] === null ? null : (int) $committee['head_user_id'];

            $member = $membership->ensureMember($userId, trim($reason));
            $membership->changeRole($member['member_id'], (int) $position['position_id'], trim($reason), $actorId);

            if ($previous !== null && $previous !== $userId) {
                $old = $members->findByUser($previous);

                if ($old !== null) {
                    $membership->changeRole((int) $old['member_id'], null, 'Replaced as head: ' . trim($reason), $actorId);
                }
            }

            AuditService::log('committee.head_set', 'committee', $committeeId, ['before' => $previous, 'after' => $userId, 'reason' => mb_substr(trim($reason), 0, 255)]);
        });
    }

    public function createPosition(?int $committeeId, string $title, string $roleType): int
    {
        authorize('admin.settings');
        $this->assertRoleType($roleType);

        $id = $this->positions->insert(['committee_id' => $committeeId, 'title' => trim($title), 'role_type' => $roleType]);
        AuditService::log('position.created', 'position', $id, ['title' => trim($title), 'role_type' => $roleType]);

        return $id;
    }

    /** A position people hold keeps its type, because their roles were set from it. */
    public function updatePosition(int $id, string $title, string $roleType): void
    {
        authorize('admin.settings');
        $this->assertRoleType($roleType);

        $before = $this->position($id);

        if ($before['in_use'] && $before['role_type'] !== $roleType) {
            throw new DomainException('This position is in use, so its type cannot change. Add a new position instead.');
        }

        $this->positions->update($id, ['title' => trim($title), 'role_type' => $roleType]);
        AuditService::log('position.updated', 'position', $id, ['before' => ['title' => $before['title'], 'role_type' => $before['role_type']], 'after' => ['title' => trim($title), 'role_type' => $roleType]]);
    }

    public function deletePosition(int $id): void
    {
        authorize('admin.settings');

        $position = $this->position($id);

        if ($position['in_use']) {
            throw new DomainException('This position has members, HR requests, vacancies or appointments, so it cannot be deleted.');
        }

        $this->positions->delete($id);
        AuditService::log('position.deleted', 'position', $id, ['title' => $position['title']]);
    }

    private function assertRoleType(string $roleType): void
    {
        if (!in_array($roleType, self::ROLE_TYPES, true)) {
            throw new DomainException('Please choose a type.');
        }
    }

    private function assertNameFree(string $name, ?int $exceptId): void
    {
        foreach ($this->committees->findBy(['name' => trim($name)]) as $row) {
            if ((int) $row['committee_id'] !== $exceptId) {
                throw new DomainException('A committee with this name already exists.');
            }
        }
    }
}
