<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\PermissionRepository;
use App\Models\RoleRepository;

/** The role and permission matrix, for admin.users. Changes are audit-logged with before and after. */
final class RoleMatrixService
{
    /** The MSW Committee Head always keeps these, so nobody can lock the system out of administration. */
    public const LOCKED_FOR_MSW_HEAD = ['admin.users', 'admin.settings', 'audit.view', 'backup.manage'];

    public function __construct(
        private readonly PermissionRepository $permissions = new PermissionRepository(),
        private readonly RoleRepository $roles = new RoleRepository(),
    ) {
    }

    /** @return array{roles: list<array<string, mixed>>, permissions: list<array<string, mixed>>, granted: array<int, list<string>>} */
    public function matrix(): array
    {
        authorize('admin.users');

        $roles = array_values(array_filter($this->roles->findBy([]), static fn (array $role): bool => $role['slug'] !== 'guest'));
        $granted = [];

        foreach ($roles as $role) {
            $granted[(int) $role['role_id']] = $this->permissions->codesForRole((int) $role['role_id']);
        }

        return ['roles' => $roles, 'permissions' => $this->permissions->allByCode(), 'granted' => $granted];
    }

    /**
     * @param array<int|string, mixed> $submitted role id => list of permission codes
     * @return int How many roles changed.
     */
    public function save(array $submitted): int
    {
        authorize('admin.users');

        $known = array_column($this->permissions->allByCode(), 'code');
        $changed = 0;

        Database::transaction(function () use ($submitted, $known, &$changed): void {
            foreach ($this->roles->findBy([]) as $role) {
                if ($role['slug'] === 'guest') {
                    continue;
                }

                $roleId = (int) $role['role_id'];
                $codes = is_array($submitted[$roleId] ?? null) ? array_values(array_filter($submitted[$roleId], 'is_string')) : [];
                $codes = array_values(array_intersect(array_unique($codes), $known));

                if ($role['slug'] === 'msw_head') {
                    $codes = array_values(array_unique([...$codes, ...self::LOCKED_FOR_MSW_HEAD]));
                }

                $before = $this->permissions->codesForRole($roleId);

                if (array_diff($before, $codes) === [] && array_diff($codes, $before) === []) {
                    continue;
                }

                $this->permissions->setForRole($roleId, $codes);
                AuditService::log('role.permissions_changed', 'role', $roleId, [
                    'role' => $role['slug'],
                    'before' => $before,
                    'after' => $codes,
                ]);
                $changed++;
            }
        });

        RbacService::clearCache();

        return $changed;
    }
}
