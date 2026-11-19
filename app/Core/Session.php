<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private const FLASH_NEXT = '_flash_next';
    private const FLASH_NOW = '_flash_now';

    public static function start(bool $secure): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        // Keeps personal pages out of browser and proxy caches, whatever php.ini says.
        session_cache_limiter('nocache');

        session_name('agile_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Values flashed by the previous request are readable now and gone after this one.
        $_SESSION[self::FLASH_NOW] = $_SESSION[self::FLASH_NEXT] ?? [];
        $_SESSION[self::FLASH_NEXT] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_NEXT][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::FLASH_NOW][$key] ?? $default;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Destroys the session and starts an empty one, so a message can still be flashed afterward. */
    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
        session_id(session_create_id());
        session_start();
    }
}
