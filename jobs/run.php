<?php

declare(strict_types=1);

// Runs the scheduled jobs one after another, each in its own process with its own lock.
// Usage: php jobs/run.php list
//        php jobs/run.php all
//        php jobs/run.php send_email_queue close_vacancies

require __DIR__ . '/_bootstrap.php';

$available = array_map(
    static fn (string $path): string => basename($path, '.php'),
    array_filter(glob(__DIR__ . '/*.php') ?: [], static fn (string $path): bool => !in_array(basename($path), ['_bootstrap.php', 'run.php', 'create_user.php', 'generate_key.php', 'test_notify.php'], true))
);
$available[] = 'welfare_followups_urgent';
sort($available);
$asked = array_slice($argv, 1);

if ($asked === ['list']) {
    echo implode("\n", $available), "\n";
    exit(0);
}

$jobs = $asked === ['all'] ? $available : $asked;

if ($jobs === [] || array_diff($jobs, $available) !== []) {
    fwrite(STDERR, 'Usage: php jobs/run.php list, php jobs/run.php all, or any of: ' . implode(', ', $available) . "\n");
    exit(1);
}

job_lock('run');

$failed = 0;

foreach ($jobs as $job) {
    $file = $job === 'welfare_followups_urgent' ? 'welfare_followups' : $job;
    $flags = $job === 'welfare_followups_urgent' ? ' --urgent' : '';
    passthru(sprintf('%s -d variables_order=%s %s%s', escapeshellarg(PHP_BINARY), escapeshellarg((string) ini_get('variables_order')), escapeshellarg(__DIR__ . "/{$file}.php"), $flags), $code);

    if ($code !== 0) {
        $failed++;
        job_log("{$job} exited with code {$code}.");
    }
}

exit($failed > 0 ? 1 : 0);
