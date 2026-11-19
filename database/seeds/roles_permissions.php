<?php

declare(strict_types=1);

use App\Core\Config;

return static function (PDO $db): void {
    $roles = [
        'guest' => ['Guest / Applicant (no account)', 'The public in the role matrix: no account and no permissions. Also holds disabled accounts retired by migration 003, kept for the audit trail.'],
        'general_member' => ['General Member', 'Active member with no officer role. First role with an account. Excluded from eligibility screening.'],
        'committee_head' => ['Committee Head', 'Files HR requests, evaluates applicants, reviews evaluations, drafts content.'],
        'execomm' => ['Executive Committee', 'View-only dashboards.'],
        'msw_member' => ['MSW Deputy Head / MSW Member', 'Processes applications, evaluations, interviews and appointment records.'],
        'msw_head' => ['MSW Committee Head', 'System administrator. Final decisions and the only role that sees all data.'],
        'president' => ['President', 'View-only dashboards and access to welfare cases.'],
    ];

    $roleIds = [];

    foreach ($roles as $slug => [$name, $description]) {
        $roleIds[$slug] = seed_row($db, 'roles', 'role_id', ['slug' => $slug], [
            'role_name' => $name,
            'description' => $description,
        ]);
    }

    /** @var array<string, array{description: string, roles: list<string>}> $permissions */
    $permissions = require Config::basePath('config/permissions.php');
    $grant = $db->prepare(
        'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE role_id = role_id'
    );

    foreach ($permissions as $code => $permission) {
        $permissionId = seed_row($db, 'permissions', 'permission_id', ['code' => $code], [
            'description' => $permission['description'],
        ]);

        foreach ($permission['roles'] as $slug) {
            $grant->execute([$roleIds[$slug], $permissionId]);
        }
    }
};
