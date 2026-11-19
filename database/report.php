<?php

declare(strict_types=1);

// Prints what is in the database: table and foreign key counts, the role x permission table, and demo users.
// Usage: php database/report.php

use App\Core\Database;

require_once __DIR__ . '/bootstrap.php';

$db = Database::connection();

$counts = $db->query(
    "SELECT
        (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()) AS tables_count,
        (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()) AS fk_count"
)->fetch();

echo "\nTables: {$counts['tables_count']}   Foreign keys: {$counts['fk_count']}\n";

$roles = $db->query('SELECT role_id, slug FROM roles ORDER BY role_id')->fetchAll();
$permissions = $db->query('SELECT permission_id, code FROM permissions ORDER BY permission_id')->fetchAll();
$granted = [];

foreach ($db->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll() as $row) {
    $granted[$row['role_id']][$row['permission_id']] = true;
}

$width = max(array_map(static fn (array $permission): int => strlen($permission['code']), $permissions));
$short = ['general_member' => 'gen_member', 'committee_head' => 'cmte_head'];
$columnWidth = 11;

echo "\nRole x permission table (from role_permissions, Y = granted)\n\n";
echo str_pad('permission', $width + 2);

foreach ($roles as $role) {
    echo str_pad($short[$role['slug']] ?? $role['slug'], $columnWidth);
}

echo "\n" . str_repeat('-', $width + 2 + $columnWidth * count($roles)) . "\n";

foreach ($permissions as $permission) {
    echo str_pad($permission['code'], $width + 2);

    foreach ($roles as $role) {
        echo str_pad(isset($granted[$role['role_id']][$permission['permission_id']]) ? 'Y' : '.', $columnWidth);
    }

    echo "\n";
}

echo "\nPermissions: " . count($permissions) . '   Role-permission rows: '
    . $db->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn() . "\n";

$users = $db->query(
    "SELECT u.email, r.slug AS role, u.status, m.member_no, p.title AS position, c.name AS committee
     FROM users u
     JOIN roles r ON r.role_id = u.role_id
     LEFT JOIN members m ON m.user_id = u.user_id
     LEFT JOIN positions p ON p.position_id = m.position_id
     LEFT JOIN committees c ON c.committee_id = p.committee_id
     ORDER BY u.user_id"
)->fetchAll();

echo "\nDemo users: " . count($users) . " (password for all: Password123!)\n\n";
printf("%-26s %-16s %-10s %-17s %s\n", 'email', 'role', 'status', 'member_no', 'position');
echo str_repeat('-', 100) . "\n";

foreach ($users as $user) {
    $position = trim(($user['committee'] ?? '') . ' ' . ($user['position'] ?? ''));
    printf(
        "%-26s %-16s %-10s %-17s %s\n",
        $user['email'],
        $user['role'],
        $user['status'],
        $user['member_no'] ?? '-',
        $position === '' ? '-' : $position
    );
}

// Integrity checks the schema cannot express as a constraint. Each should print 0.
// committees.head_user_id is a deliberate copy of "who holds this committee's Committee Head position", kept so the
// decision rules (BR-20) need one lookup. MembershipService::changeRole() updates both; this catches any drift.
$checks = [
    'Committees whose head is not the member holding their Committee Head position' =>
        "SELECT COUNT(*) FROM committees c
         WHERE c.head_user_id IS NOT NULL AND NOT EXISTS (
             SELECT 1 FROM members m JOIN positions p ON p.position_id = m.position_id
             WHERE m.user_id = c.head_user_id AND p.committee_id = c.committee_id AND p.role_type = 'committee_head')",
    'Committee Head positions held by someone the committee does not list as head' =>
        "SELECT COUNT(*) FROM members m
         JOIN positions p ON p.position_id = m.position_id AND p.role_type = 'committee_head'
         JOIN committees c ON c.committee_id = p.committee_id
         WHERE m.user_id IS NOT NULL AND m.role_status = 'active' AND (c.head_user_id IS NULL OR c.head_user_id <> m.user_id)",
    'Welfare cases with a guest email that is not encrypted' =>
        "SELECT COUNT(*) FROM welfare_cases WHERE guest_email LIKE '%@%'",
];

echo "\nIntegrity checks\n";

foreach ($checks as $label => $sql) {
    printf("%-80s %s\n", $label, $db->query($sql)->fetchColumn());
}
