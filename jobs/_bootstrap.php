<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(Config::basePath())->safeLoad();
date_default_timezone_set((string) config('app.timezone'));

/** Prints the line and appends it to storage/logs/jobs.log. */
function job_log(string $message): void
{
    $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), basename((string) ($_SERVER['argv'][0] ?? 'job')), $message);

    echo $line;
    file_put_contents(Config::basePath('storage/logs/jobs.log'), $line, FILE_APPEND | LOCK_EX);
}

/**
 * Runs a job: lock, log the start, do the work, log the result, record it for System health, and exit 0 or 1.
 * $work returns a summary line and how many items failed.
 *
 * @param callable(): array{summary: string, failed: int} $work
 */
function job_run(string $name, callable $work): never
{
    job_lock($name);
    job_log('Started.');

    $runs = new App\Models\JobRunRepository();
    $runId = null;

    try {
        $runId = $runs->start($name);
    } catch (Throwable $exception) {
        // A job must still do its work if the record cannot be written, for example before setup has added the table.
        job_log('Could not record this run: ' . $exception->getMessage());
    }

    try {
        ['summary' => $summary, 'failed' => $failed] = $work();
        job_log('Done: ' . $summary);
        $ok = $failed === 0;
    } catch (Throwable $exception) {
        $summary = 'Stopped: ' . $exception->getMessage();
        job_log($summary);
        $ok = false;
    }

    try {
        if ($runId !== null) {
            $runs->finish($runId, $ok, $summary);
        }
    } catch (Throwable $exception) {
        job_log('Could not record the result: ' . $exception->getMessage());
    }

    exit($ok ? 0 : 1);
}

/**
 * Exits quietly when another run holds this job's lock. The operating system releases the lock when
 * the process ends, so a crashed run cannot block later ones.
 */
function job_lock(string $name): void
{
    static $held = [];

    $handle = fopen(Config::basePath("storage/cache/{$name}.lock"), 'c');

    if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
        job_log('Another run is still in progress, so this run is exiting.');
        exit(0);
    }

    $held[$name] = $handle;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    job_log(sprintf('Stopped by %s: %s', $exception::class, $exception->getMessage()));
    exit(1);
});

Database::connection();
