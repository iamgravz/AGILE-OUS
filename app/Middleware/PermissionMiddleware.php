<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Created by the router for 'perm:code'. Runs the full login check first, so a permission route is
 * safe on its own. A denial is handled by the router, like one raised inside a service.
 */
final class PermissionMiddleware
{
    public function __construct(private readonly string $permission)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        return (new AuthMiddleware())->handle($request, function (Request $request) use ($next): Response {
            authorize($this->permission);

            return $next($request);
        });
    }
}
