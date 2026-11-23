<?php

declare(strict_types=1);

// Seeds reference data and, outside production, demo users. Safe to run again: existing rows are left as they are.
// Usage: php database/seed.php

use App\Core\Database;

require_once __DIR__ . '/bootstrap.php';

/**
 * Inserts a row unless one matching $match exists, and returns its id.
 *
 * @param array<string, mixed> $match
 * @param array<string, mixed> $values
 */
function seed_row(PDO $db, string $table, string $idColumn, array $match, array $values = []): int
{
    $conditions = [];
    $params = [];

    foreach ($match as $column => $value) {
        $conditions[] = $value === null ? "`{$column}` IS NULL" : "`{$column}` = ?";

        if ($value !== null) {
            $params[] = $value;
        }
    }

    $find = $db->prepare("SELECT `{$idColumn}` FROM `{$table}` WHERE " . implode(' AND ', $conditions) . ' LIMIT 1');
    $find->execute($params);
    $existing = $find->fetchColumn();

    if ($existing !== false) {
        return (int) $existing;
    }

    $row = $match + $values;
    $columns = implode(', ', array_map(static fn (string $column): string => "`{$column}`", array_keys($row)));
    $placeholders = implode(', ', array_fill(0, count($row), '?'));

    $insert = $db->prepare("INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})");
    $insert->execute(array_values($row));

    return (int) $db->lastInsertId();
}

$seeders = [
    'roles and permissions' => 'roles_permissions',
    'welfare categories' => 'welfare_categories',
    'committees and positions' => 'committees_positions',
    'email templates' => 'email_templates',
    'settings' => 'settings',
    'application questions' => 'application_questions',
    'interview question bank' => 'interview_questions',
];

// Demo accounts share a published password, so they never go into production.
if (config('app.env') !== 'production') {
    $seeders['demo users'] = 'demo_users';
    $seeders['demo applicants'] = 'demo_applicants';
    $seeders['demo organization'] = 'demo_organization';
    $seeders['demo content posts'] = 'content_posts';
    $seeders['demo open positions'] = 'demo_open_positions';
    $seeders['demo activity'] = 'demo_activity';

    // 100 more sample people (DEMO_BULK=true in .env). Tests keep it off, because they count the smaller set.
    if (filter_var(config('app.demo_bulk'), FILTER_VALIDATE_BOOL)) {
        $seeders['100 more demo people'] = 'demo_bulk';
    }
}

Database::transaction(static function (PDO $db) use ($seeders): void {
    foreach ($seeders as $label => $file) {
        (require __DIR__ . "/seeds/{$file}.php")($db);
        echo "Seeded {$label}.\n";
    }
});
