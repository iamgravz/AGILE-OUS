<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

final class GuestMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        return current_user_id() === null ? $next($request) : redirect(route('dashboard'));
    }
}
