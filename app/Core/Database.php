<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    /** @var list<callable> */
    private static array $rollbackHooks = [];

    /** The shared connection to the application database. */
    public static function connection(): PDO
    {
        return self::$connection ??= self::connect((string) config('app.database.name'));
    }

    /** Opens a new connection. Pass null to connect to the server without selecting a database. */
    public static function connect(?string $databaseName): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', config('app.database.host'), config('app.database.port'));

        if ($databaseName !== null) {
            $dsn .= ";dbname={$databaseName}";
        }

        $pdo = new PDO($dsn, (string) config('app.database.user'), (string) config('app.database.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec("SET time_zone = '+08:00'");

        return $pdo;
    }

    /** Runs $fn in a transaction. When one is already open, $fn joins it. */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::connection();

        if ($pdo->inTransaction()) {
            return $fn($pdo);
        }

        $pdo->beginTransaction();

        try {
            $result = $fn($pdo);
            $pdo->commit();
            self::$rollbackHooks = [];

            return $result;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $hooks = self::$rollbackHooks;
            self::$rollbackHooks = [];

            foreach ($hooks as $hook) {
                $hook();
            }

            throw $exception;
        }
    }

    /** Runs $fn if the open transaction rolls back, to undo what the database cannot, such as a saved file. */
    public static function onRollback(callable $fn): void
    {
        self::$rollbackHooks[] = $fn;
    }
}
