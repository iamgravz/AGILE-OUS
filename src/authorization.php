<?php
declare(strict_types=1);

/**
 * Default-deny RBAC. Every new endpoint must call requirePermission().
 * President sees aggregate dashboards only; welfare case records are private.
 */
function hasPermission(string $role, string $permission): bool {
    $permissions = [
        'admin' => ['dashboard.view','users.manage','membership.view','membership.create','membership.update','welfare.summary','welfare.view','welfare.create','welfare.update','audit.view'],
        'president' => ['dashboard.view','membership.summary','welfare.summary'],
        'membership_head' => ['dashboard.view','membership.view','membership.create','membership.update'],
        'membership_member' => ['dashboard.view','membership.view','membership.create','membership.update'],
        'welfare_head' => ['dashboard.view','welfare.summary','welfare.view','welfare.create','welfare.update'],
        'welfare_member' => ['dashboard.view','welfare.summary','welfare.view','welfare.create','welfare.update']
    ];
    return in_array($permission, $permissions[$role] ?? [], true);
}
function requirePermission(string $permission): array {
    $user = authUser();
    if (!$user) {
        http_response_code(401);
        exit('Authentication required');
    }
    if (!hasPermission((string)$user['role'], $permission)) {
        http_response_code(403);
        exit('Access denied');
    }
    return $user;
}
