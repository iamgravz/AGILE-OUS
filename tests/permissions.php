<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/authorization.php';
$cases = [
 ['president','welfare.summary',true],
 ['president','welfare.view',false],
 ['president','membership.update',false],
 ['welfare_member','welfare.update',true],
 ['welfare_member','users.manage',false],
 ['membership_member','membership.update',true],
 ['membership_member','welfare.view',false],
 ['admin','users.manage',true],
 ['unknown','dashboard.view',false]
];
foreach ($cases as [$role,$permission,$expected]) {
    if (hasPermission($role,$permission) !== $expected) {
        fwrite(STDERR, "FAIL: $role -> $permission\n");
        exit(1);
    }
}
echo "RBAC smoke tests passed\n";
