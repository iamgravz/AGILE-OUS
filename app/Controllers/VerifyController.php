<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\CredentialService;
use App\Services\FileStorageService;
use App\Services\MemberVerificationService;

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

    /** A member's own QR code (manual IDs): their current ID and whether they are an active member. */
    public function member(Request $request, string $token): Response
    {
        if ((new CredentialService())->verifyBlocked($request->ip())) {
            return $this->page(['blocked' => true], Response::HTTP_TOO_MANY_REQUESTS, 'portal/verify_member');
        }

        $result = (new MemberVerificationService())->verify($token);

        return $this->page(['result' => $result, 'token' => $token], $result === null ? Response::HTTP_NOT_FOUND : Response::HTTP_OK, 'portal/verify_member');
    }

    /** The current ID itself, shown on that page. Only reachable with the member's token. */
    public function memberId(Request $request, string $token): Response
    {
        if ((new CredentialService())->verifyBlocked($request->ip())) {
            return Response::error(Response::HTTP_TOO_MANY_REQUESTS);
        }

        $path = (new MemberVerificationService())->currentIdPath($token);

        if ($path === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return (new FileStorageService())->streamPath($path, 'AGILE-ID.' . pathinfo($path, PATHINFO_EXTENSION), true, true);
    }

    /** @param array<string, mixed> $data */
    private function page(array $data, int $status, string $view = 'portal/verify'): Response
    {
        return Response::html(View::render($view, $data, 'layouts/public'), $status, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }
}
