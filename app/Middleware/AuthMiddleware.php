<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SessionGuard;

final class AuthMiddleware
{
    /** SessionGuard has already checked idle time, account status and password for this request. */
    public function handle(Request $request, callable $next): Response
    {
        if (current_user_id() !== null) {
            return $next($request);
        }

        // Remembered so that, for example, Apply on a position keeps its vacancy through the login.
        if ($request->method() === 'GET' && !$request->isAjax()) {
            Session::set('intended_url', $request->pathWithQuery());
        }

        if (!SessionGuard::justEnded()) {
            flash('info', 'Please log in to continue.');
        }

        return redirect(route('login'));
    }
}
