<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use Dotenv\Dotenv;
use RuntimeException;

/**
 * Keeps a test run away from real data. It uses its own database, rebuilt before every run, and its own storage
 * folder in the system temp directory, so uploads, credentials, mail and logs written by tests never reach storage/.
 */
final class TestEnvironment
{
    private const STORAGE_PREFIX = 'agile_test_storage_';
    private const STORAGE_FOLDERS = ['uploads', 'credentials', 'mail', 'logs', 'cache', 'backups'];

    private static string $storage = '';

    public static function prepare(): void
    {
        $envFile = Config::basePath('.env');
        $values = is_file($envFile) ? Dotenv::parse((string) file_get_contents($envFile)) : [];
        $name = self::testDatabaseName((string) ($values['DB_NAME'] ?? 'agile_ams'));

        self::$storage = self::createStorage();
        $_ENV['DB_NAME'] = $name;
        $_ENV['STORAGE_DIR'] = self::$storage;
        $_ENV['BACKUP_DIR'] = self::$storage . DIRECTORY_SEPARATOR . 'backups';
        Dotenv::createImmutable(Config::basePath())->safeLoad();

        self::rebuild($name);
    }

    /** The database the access tests' web server uses, so its requests cannot affect the service tests. */
    public static function secondDatabase(): string
    {
        $name = $_ENV['DB_NAME'] . '_http_test';
        self::assertSafeName($name, '');
        self::rebuild($name);

        return $name;
    }

    /** @return array<string, string> Environment for a child process that must use the test setup. */
    public static function childEnvironment(string $database): array
    {
        $values = array_filter(array_merge(getenv(), $_ENV), 'is_scalar');
        $values['DB_NAME'] = $database;

        return array_map('strval', $values);
    }

    private static function testDatabaseName(string $realName): string
    {
        $name = (string) ($_ENV['DB_NAME_TEST'] ?? '');
        self::assertSafeName($name, $realName);

        return $name;
    }

    /** Rebuilding drops the database, so the name is checked first and the real one can never be used. */
    private static function assertSafeName(string $name, string $realName): void
    {
        if (preg_match('/^[A-Za-z0-9_]+_test$/', $name) !== 1 || $name === $realName) {
            throw new RuntimeException("Refusing to use \"{$name}\" for tests. DB_NAME_TEST must end in _test and differ from DB_NAME.");
        }
    }

    private static function rebuild(string $name): void
    {
        $process = proc_open(
            [PHP_BINARY, '-d', 'variables_order=EGPCS', Config::basePath('database/setup.php'), '--fresh'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            Config::basePath(),
            self::childEnvironment($name),
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start database/setup.php.');
        }

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException("Could not rebuild the test database {$name}:\n{$output}");
        }
    }

    private static function createStorage(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::STORAGE_PREFIX . bin2hex(random_bytes(6));

        foreach (self::STORAGE_FOLDERS as $folder) {
            mkdir($root . DIRECTORY_SEPARATOR . $folder, 0777, true);
        }

        register_shutdown_function(static fn () => self::removeStorage($root));

        return $root;
    }

    /** Only ever removes the folder this run created. Nothing under the project is touched. */
    private static function removeStorage(string $root): void
    {
        $temp = rtrim((string) realpath(sys_get_temp_dir()), '\\/');
        $real = (string) realpath($root);

        if ($real === '' || !str_starts_with($real, $temp . DIRECTORY_SEPARATOR . self::STORAGE_PREFIX)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($real);
    }
}
