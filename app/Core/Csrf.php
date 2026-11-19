<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public const FIELD = '_token';
    public const HEADER = 'X-CSRF-Token';
    private const SESSION_KEY = '_csrf_token';

    /** The token for this session, created on first use. */
    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            return self::rotate();
        }

        return $token;
    }

    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);

        return $token;
    }

    public static function isValid(mixed $token): bool
    {
        $expected = Session::get(self::SESSION_KEY);

        return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }
}
