<?php

declare(strict_types=1);

// Seeds reference data and, outside production, seeded users. Safe to run again: existing rows are left as they are.
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

/**
 * A fixed 12-character code in the format MembershipService::generateTrackingCode() uses, so made-up applications and
 * welfare cases look like real ones and running the seeder again finds the same rows. $key names the row.
 */
function seed_code(string $key): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $hash = hash('sha256', 'agile-seed:' . $key, true);
    $code = '';

    for ($i = 0; $i < 12; $i++) {
        $code .= $alphabet[ord($hash[$i]) % strlen($alphabet)];
    }

    return $code;
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

// Seeded accounts share a published password, so they never go into production.
if (config('app.env') !== 'production') {
    $seeders['seeded users'] = 'seed_users';
    $seeders['seeded applicants'] = 'seed_applicants';
    $seeders['seeded organization'] = 'seed_organization';
    $seeders['seeded content posts'] = 'content_posts';
    $seeders['seeded open positions'] = 'seed_open_positions';
    $seeders['seeded activity'] = 'seed_activity';

    // 100 more seeded people (SEED_BULK=true in .env). Tests keep it off, because they count the smaller set.
    if (filter_var(config('app.seed_bulk'), FILTER_VALIDATE_BOOL)) {
        $seeders['100 more seeded people'] = 'seed_bulk';
    }
}

Database::transaction(static function (PDO $db) use ($seeders): void {
    foreach ($seeders as $label => $file) {
        (require __DIR__ . "/seeds/{$file}.php")($db);
        echo "Seeded {$label}.\n";
    }
});
