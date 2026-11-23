<?php

declare(strict_types=1);

// Removes the seeded data before the system goes live: every @agile.test account and applicant and what belongs to them.
// Real records are never deleted, and files are moved to storage/removed-seed-data/ instead of deleted.
//
// Usage:
//   php database/remove_seed_data.php                 Shows what would be removed. Changes nothing.
//   php database/remove_seed_data.php --yes           Backs up the database, then removes the seeded data.
//
// Options for --yes:
//   --no-backup        Skip the backup (only if you made one yourself just now).
//   --allow-no-admin   Go ahead even if no real MSW Committee Head account would be left.
//   --any-env          Run even when APP_ENV is not production. Without APP_ENV=production, the next
//                      php database/setup.php puts the seeded data back.

use App\Core\Database;
use App\Services\BackupService;
use App\Services\SeedDataRemover;

require_once __DIR__ . '/bootstrap.php';

$options = array_slice($argv, 1);
$known = ['--yes', '--no-backup', '--allow-no-admin', '--any-env'];

foreach ($options as $option) {
    if (!in_array($option, $known, true)) {
        fwrite(STDERR, "Unknown option {$option}. Options: " . implode(' ', $known) . "\n");
        exit(1);
    }
}

$yes = in_array('--yes', $options, true);
$env = (string) config('app.env');

$remover = new SeedDataRemover(Database::connection());
$plan = $remover->plan(in_array('--allow-no-admin', $options, true));

$total = 0;
echo 'Database: ' . config('app.database.name') . " (APP_ENV={$env})\n\n";
echo "Rows to remove:\n";

foreach ($plan['delete'] as $table => $ids) {
    $made = isset($plan['madeBySeed'][$table]) ? "  ({$plan['madeBySeed'][$table]} made by a seeded account)" : '';
    printf("  %-30s %6d%s\n", $table, count($ids), $made);
    $total += count($ids);
}

foreach ($plan['deleteWhere'] as $entry) {
    printf("  %-30s %6d\n", $entry['table'], $entry['count']);
    $total += $entry['count'];
}

if ($total === 0) {
    echo "  none. There is no seeded data.\n";
}

if ($plan['clear'] !== []) {
    echo "\nReal records that keep their data, with the seeded account's name cleared:\n";

    foreach ($plan['clear'] as $entry) {
        printf("  %-30s %6d\n", "{$entry['table']}.{$entry['column']}", count($entry['ids']));
    }
}

printf("\nFiles to move to storage/removed-seed-data/ (only those nothing else uses): %d\n", count($plan['files']));

if ($plan['problems'] !== []) {
    echo "\nNothing can be removed until these are sorted out:\n";

    foreach ($plan['problems'] as $problem) {
        echo "  - {$problem}\n";
    }

    exit(1);
}

if (!$yes) {
    echo "\nNothing was changed. Run again with --yes to remove it.\n";
    exit(0);
}

if ($total === 0) {
    exit(0);
}

if ($env !== 'production' && !in_array('--any-env', $options, true)) {
    fwrite(STDERR, "\nAPP_ENV is {$env}. Set APP_ENV=production in .env first, or php database/setup.php will add the seeded data again.\n"
        . "To remove it anyway, add --any-env.\n");
    exit(1);
}

if (!in_array('--no-backup', $options, true)) {
    try {
        $backup = (new BackupService())->run();
        echo "\nBackup made: {$backup['file']}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "\nThe backup failed, so nothing was removed: {$exception->getMessage()}\n");
        exit(1);
    }
}

try {
    $result = $remover->apply($plan);
} catch (Throwable $exception) {
    fwrite(STDERR, "\n{$exception->getMessage()}\n");
    exit(1);
}

printf("\nRemoved %d rows, cleared %d names, moved %d files.\n", array_sum($result['removed']), $result['cleared'], count($result['moved']));

if ($result['moved'] !== []) {
    echo "The files are in storage/removed-seed-data/. Delete that folder yourself once you are sure.\n";
}

echo "Next: give every real account a new password if it was ever shared, and check php database/report.php.\n";
