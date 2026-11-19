<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\SessionGuard;

final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if ($request->exceedsPostLimit()) {
            return $this->tooLarge($request);
        }

        // The login ended on this request and took the token with it, so explain that instead of "page expired". Nothing was processed.
        if (SessionGuard::justEnded()) {
            return redirect(route('login'));
        }

        $token = $request->input(Csrf::FIELD) ?? $request->header(Csrf::HEADER);

        if (Csrf::isValid($token)) {
            return $next($request);
        }

        return $request->isAjax()
            ? Response::json(['error' => 'Your session has expired. Refresh the page and try again.'], Response::HTTP_PAGE_EXPIRED)
            : Response::error(Response::HTTP_PAGE_EXPIRED);
    }

    /** Nothing in the request was processed, so this is safe to answer without a token. */
    private function tooLarge(Request $request): Response
    {
        $message = 'That upload is too large for the server to accept. Please choose a smaller file.';

        if ($request->isAjax()) {
            return Response::json(['error' => $message], Response::HTTP_PAYLOAD_TOO_LARGE);
        }

        flash('error', $message);

        return redirect($request->path());
    }
}
