<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

/**
 * Daily database backups: mysqldump, gzipped, into storage/backups. A new file replaces today's only once it is
 * complete. Pruning only ever touches files this service named, and only runs after a good backup.
 */
final class BackupService
{
    public const KEEP_DAYS = 30;
    private const FILE_PATTERN = '/^agile_(\d{4}-\d{2}-\d{2})\.sql\.gz$/';

    /** Common places mysqldump lives, for when it is not on the PATH. */
    private const WINDOWS_PATHS = [
        'C:/xampp/mysql/bin/mysqldump.exe',
        'C:/Program Files/MySQL/MySQL Server 8.4/bin/mysqldump.exe',
        'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe',
    ];

    private readonly string $directory;

    public function __construct(?string $directory = null, private readonly ?string $mysqldumpPath = null)
    {
        $this->directory = rtrim($directory ?? ((string) config('app.backup.directory') ?: Config::basePath('storage/backups')), '/\\');
    }

    /** @return array{file: string, size: int} */
    public function run(): array
    {
        if (!extension_loaded('zlib')) {
            throw new RuntimeException('The PHP zlib extension is needed to compress backups.');
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Could not create the backup folder.');
        }

        $file = 'agile_' . date('Y-m-d') . '.sql.gz';
        $temporary = "{$this->directory}/{$file}.tmp";

        try {
            $this->dumpTo($temporary);

            if (!rename($temporary, "{$this->directory}/{$file}")) {
                throw new RuntimeException('Could not save the finished backup.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return ['file' => $file, 'size' => (int) filesize("{$this->directory}/{$file}")];
    }

    /** @return list<string> The names of the backups removed: only this service's own files, past the limit. */
    public function prune(?int $today = null): array
    {
        $cutoff = date('Y-m-d', ($today ?? time()) - self::KEEP_DAYS * 86400);
        $removed = [];

        foreach (scandir($this->directory) ?: [] as $name) {
            if (preg_match(self::FILE_PATTERN, $name, $match) === 1 && $match[1] < $cutoff && unlink("{$this->directory}/{$name}")) {
                $removed[] = $name;
            }
        }

        return $removed;
    }

    /** @return array{file: string, size: int, modified: int}|null */
    public function latest(): ?array
    {
        $names = array_values(array_filter(scandir($this->directory) ?: [], static fn (string $name): bool => preg_match(self::FILE_PATTERN, $name) === 1));

        if ($names === []) {
            return null;
        }

        rsort($names);
        $path = "{$this->directory}/{$names[0]}";

        return ['file' => $names[0], 'size' => (int) filesize($path), 'modified' => (int) filemtime($path)];
    }

    private function dumpTo(string $path): void
    {
        $database = config('app.database');
        $command = [
            $this->mysqldump(),
            '--single-transaction', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4',
            '-h', (string) $database['host'], '-P', (string) $database['port'], '-u', (string) $database['user'],
            (string) $database['name'],
        ];
        // The password goes through the environment, so it never shows in a process list.
        $environment = getenv();

        if ((string) $database['pass'] !== '') {
            $environment['MYSQL_PWD'] = (string) $database['pass'];
        }

        $errors = tempnam(sys_get_temp_dir(), 'agile');
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']], $pipes, null, $environment);

        if (!is_resource($process)) {
            unlink($errors);

            throw new RuntimeException('Could not start mysqldump.');
        }

        $gzip = gzopen($path, 'wb6');
        $tail = '';

        while (!feof($pipes[1])) {
            $chunk = (string) fread($pipes[1], 1 << 16);
            gzwrite($gzip, $chunk);
            $tail = substr($tail . $chunk, -200);
        }

        gzclose($gzip);
        fclose($pipes[1]);
        $code = proc_close($process);
        $message = trim((string) file_get_contents($errors));
        unlink($errors);

        if ($code !== 0 || !str_contains($tail, 'Dump completed')) {
            throw new RuntimeException('mysqldump did not finish (exit ' . $code . '): ' . mb_substr($message, 0, 300));
        }
    }

    private function mysqldump(): string
    {
        $configured = $this->mysqldumpPath ?? (string) config('app.backup.mysqldump');

        if ($configured !== '') {
            return $configured;
        }

        foreach ([...self::WINDOWS_PATHS, ...(glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe') ?: [])] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }
}
