<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\CredentialService;

/** The public page a credential's QR code opens. */
final class VerifyController
{
    public function show(Request $request, string $token): Response
    {
        $credentials = new CredentialService();

        if ($credentials->verifyBlocked($request->ip())) {
            return $this->page(['blocked' => true], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $result = $credentials->verify($token);

        return $this->page(['result' => $result], $result === null ? Response::HTTP_NOT_FOUND : Response::HTTP_OK);
    }

    /** @param array<string, mixed> $data */
    private function page(array $data, int $status): Response
    {
        return Response::html(View::render('portal/verify', $data, 'layouts/public'), $status, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }
}
