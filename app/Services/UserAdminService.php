<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CommitteeRepository;
use App\Models\MemberRepository;
use App\Models\PermissionRepository;
use App\Models\RoleRepository;
use App\Models\UserRepository;
use DomainException;

/**
 * Users, for admin.users. The system always keeps at least one active MSW Committee Head, and an MSW Committee Head
 * cannot take away their own admin access.
 */
final class UserAdminService
{
    public const STATUSES = ['pending', 'active', 'locked', 'disabled'];

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly PermissionRepository $permissions = new PermissionRepository(),
    ) {
    }

    /**
     * @param array{q?: string, role?: string, status?: string} $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int}
     */
    public function list(array $filters, int $page): array
    {
        authorize('admin.users');

        return $this->users->search($filters, $page);
    }

    /** @return list<array<string, mixed>> Every role an account can have. */
    public function roles(): array
    {
        return array_values(array_filter($this->roles->findBy([]), static fn (array $role): bool => $role['slug'] !== 'guest'));
    }

    /** @return array<string, mixed> */
    public function detail(int $userId): array
    {
        authorize('admin.users');

        $user = $this->users->findWithRole($userId) ?? throw new DomainException('This account no longer exists.');

        return [
            'user' => $user,
            'member' => (new MemberRepository())->findByUser($userId),
            'isLocked' => $user['locked_until'] !== null && strtotime($user['locked_until']) > time(),
            'permissions' => $this->permissions->allByCode(),
            'fromRole' => $this->permissions->codesForRole((int) $user['role_id']),
            'extra' => $this->permissions->extraForUser($userId),
        ];
    }

    public function changeRole(int $userId, string $roleSlug, string $reason): void
    {
        authorize('admin.users');

        if (trim($reason) === '') {
            throw new DomainException('Please write why the role is changing.');
        }

        $role = $this->roles->findBySlug($roleSlug);

        if ($role === null || $roleSlug === 'guest') {
            throw new DomainException('Please choose a role.');
        }

        Database::transaction(function () use ($userId, $role, $roleSlug, $reason): void {
            $this->users->lockRow($userId);
            $user = $this->users->findWithRole($userId) ?? throw new DomainException('This account no longer exists.');

            if ($user['role_slug'] === $roleSlug) {
                throw new DomainException('This account already has that role.');
            }

            if ($user['role_slug'] === 'msw_head') {
                $this->assertKeepsAnMswHead($userId, 'change this role');
                $this->assertNotSelf($userId, 'You cannot remove your own admin access. Ask another MSW Committee Head to change your role.');
            }

            if (in_array($user['role_slug'], ['committee_head', 'msw_head'], true)) {
                (new CommitteeRepository())->clearHead($userId);
            }

            $this->users->update($userId, ['role_id' => $role['role_id']]);
            RbacService::clearCache();
            AuditService::log('user.role_changed', 'user', $userId, ['before' => $user['role_slug'], 'after' => $roleSlug, 'reason' => mb_substr(trim($reason), 0, 255)]);
        });
    }

    /** Active to disabled and back. Pending accounts are decided on the approvals page (BR-21). */
    public function setEnabled(int $userId, bool $enabled, string $reason): void
    {
        authorize('admin.users');

        if (trim($reason) === '') {
            throw new DomainException('Please write the reason.');
        }

        Database::transaction(function () use ($userId, $enabled, $reason): void {
            $this->users->lockRow($userId);
            $user = $this->users->findWithRole($userId) ?? throw new DomainException('This account no longer exists.');
            $from = $enabled ? 'disabled' : 'active';

            if ($user['status'] !== $from) {
                throw new DomainException($user['status'] === 'pending' ? 'This account is waiting for approval. Decide it on New accounts for approval.' : "This account is already {$user['status']}.");
            }

            if ($enabled && str_ends_with($user['email'], '.invalid')) {
                throw new DomainException('This account was rejected and its email was released, so it cannot be enabled. The person can register again.');
            }

            if (!$enabled) {
                $this->assertNotSelf($userId, 'You cannot disable your own account.');

                if ($user['role_slug'] === 'msw_head') {
                    $this->assertKeepsAnMswHead($userId, 'disable this account');
                }
            }

            $this->users->update($userId, ['status' => $enabled ? 'active' : 'disabled']);
            AuditService::log($enabled ? 'user.enabled' : 'user.disabled', 'user', $userId, ['before' => $from, 'after' => $enabled ? 'active' : 'disabled', 'reason' => mb_substr(trim($reason), 0, 255)]);
        });
    }

    public function unlock(int $userId): void
    {
        authorize('admin.users');

        $this->users->update($userId, ['failed_logins' => 0, 'locked_until' => null]);
        AuditService::log('user.unlocked', 'user', $userId);
    }

    public function forcePasswordReset(int $userId): void
    {
        authorize('admin.users');
        $this->assertNotSelf($userId, 'To change your own password, use My account.');

        (new AuthService())->forcePasswordReset($userId);
    }

    /** @param list<string> $codes The extra permissions this user should have. */
    public function setExtraPermissions(int $userId, array $codes): void
    {
        authorize('admin.users');

        $known = array_column($this->permissions->allByCode(), 'code');
        $codes = array_values(array_intersect(array_unique($codes), $known));

        Database::transaction(function () use ($userId, $codes): void {
            $before = $this->permissions->extraForUser($userId);
            $this->permissions->setExtraForUser($userId, $codes);
            RbacService::clearCache();

            if (array_diff($before, $codes) !== [] || array_diff($codes, $before) !== []) {
                AuditService::log('user.permissions_changed', 'user', $userId, ['before' => $before, 'after' => $codes]);
            }
        });
    }

    private function assertKeepsAnMswHead(int $userId, string $action): void
    {
        if (array_diff($this->users->activeIdsByRole('msw_head'), [$userId]) === []) {
            throw new DomainException("This is the only active MSW Committee Head, so you cannot {$action}. Please appoint another MSW Committee Head first.");
        }
    }

    private function assertNotSelf(int $userId, string $message): void
    {
        if ($userId === current_user_id()) {
            throw new DomainException($message);
        }
    }
}
