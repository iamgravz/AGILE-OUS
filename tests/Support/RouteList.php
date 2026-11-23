<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Router;
use ReflectionProperty;

/** The routes in config/routes.php, and the section 6 table read directly from PROJECT_CONTEXT.md. */
final class RouteList
{
    /** Everything reachable without a login. A new route must be added here on purpose, or given a login check. */
    public const PUBLIC_ROUTES = [
        'GET /', 'GET /about', 'GET /committees', 'GET /committees/{id}', 'GET /news', 'GET /events', 'GET /publications', 'GET /announcements', 'GET /positions', 'GET /positions/{id}',
        'GET /ask-for-help', 'GET /privacy', 'GET /track', 'POST /track', 'POST /track/edit-link', 'GET /verify/{token}', 'GET /verify/member/{token}', 'GET /verify/member/{token}/id', 'GET /posts/{slug}', 'GET /media/{id}',
        'GET /welfare/guest', 'POST /welfare/guest/code', 'POST /welfare/guest/resend', 'POST /welfare/guest/verify', 'POST /welfare/guest/restart', 'POST /welfare/guest/submit', 'GET /welfare/guest/submitted',
        'GET /register', 'POST /register', 'POST /register/verify', 'POST /register/password', 'POST /register/resend', 'POST /register/restart',
        'GET /login', 'POST /login', 'GET /forgot-password', 'POST /forgot-password', 'GET /reset-password', 'POST /reset-password', 'GET /account/setup', 'POST /account/setup',
        'GET /apply', 'POST /apply/code', 'POST /apply/resend', 'POST /apply/verify', 'POST /apply/restart', 'GET /apply/continue', 'POST /apply/continue', 'GET /apply/withdraw', 'POST /apply/withdraw',
        'GET /apply/new', 'POST /apply/new', 'GET /apply/{id}/step/{step}', 'POST /apply/{id}/step/{step}', 'POST /apply/{id}/documents/{type}', 'POST /apply/{id}/documents/{documentId}/remove',
        'GET /apply/files/{id}', 'POST /apply/{id}/submit', 'GET /apply/{id}/submitted',
        'GET /styleguide', 'GET /styleguide/dashboard', 'GET /styleguide/auth', 'GET /styleguide/upload', 'POST /styleguide/upload',
    ];

    /** @return list<array{method: string, path: string, middleware: list<string>}> The first declaration of each, because the first match wins. */
    public static function routes(): array
    {
        $router = new Router();
        (require Config::basePath('config/routes.php'))($router);
        $seen = [];

        foreach ((new ReflectionProperty(Router::class, 'routes'))->getValue($router) as $route) {
            $seen["{$route['method']} {$route['path']}"] ??= $route;
        }

        return array_values($seen);
    }

    /** @param array{middleware: list<string>} $route The section 6 code on this route, if it has one. */
    public static function permission(array $route): ?string
    {
        foreach ($route['middleware'] as $middleware) {
            if (str_starts_with($middleware, 'perm:')) {
                return substr($middleware, 5);
            }
        }

        return null;
    }

    /** @param array{method: string, path: string} $route */
    public static function name(array $route): string
    {
        return "{$route['method']} {$route['path']}";
    }

    /** @return array<string, list<string>> Permission code => roles with a tick in section 6. */
    public static function sectionSix(): array
    {
        $document = (string) file_get_contents(Config::basePath('docs/PROJECT_CONTEXT.md'));
        preg_match('/## §6 Permissions.*?\n(\| Code .*?)\n\n/su', $document, $section);

        $lines = explode("\n", $section[1]);
        $roles = array_map('trim', array_slice(explode('|', $lines[0]), 2, -1));
        $table = [];

        foreach (array_slice($lines, 2) as $line) {
            $cells = array_map('trim', array_slice(explode('|', $line), 1, -1));
            $granted = [];

            foreach ($roles as $index => $role) {
                if ($cells[$index + 1] !== '') {
                    $granted[] = $role;
                }
            }

            preg_match_all('/`([a-z_.]+)`/', $cells[0], $codes);

            foreach ($codes[1] as $code) {
                $table[$code] = $granted;
            }
        }

        return $table;
    }
}
