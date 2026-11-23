<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function basePath(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);
        $relative = str_replace('\\', '/', ltrim($path, '/\\'));

        // Set only by the test suite, so tests never write to the real storage folder.
        if (!empty($_ENV['STORAGE_DIR']) && str_starts_with($relative, 'storage/')) {
            return rtrim($_ENV['STORAGE_DIR'], '/\\') . DIRECTORY_SEPARATOR . substr($relative, strlen('storage/'));
        }

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    /** Replaces one loaded value, for tests that try another setting. */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);
        self::get($file . '.__load');
        $target = &self::$items[$file];

        foreach ($segments as $segment) {
            $target = &$target[$segment];
        }

        $target = $value;
    }

    /** Reads "file.key" from config/file.php, loading the file on first use. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!array_key_exists($file, self::$items)) {
            $path = self::basePath("config/{$file}.php");
            self::$items[$file] = is_file($path) ? require $path : [];
        }

        $value = self::$items[$file];

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
