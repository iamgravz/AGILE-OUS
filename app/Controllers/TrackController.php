<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\TrackingService;

final class TrackController
{
    private const NOT_FOUND = 'We could not find a match for that code and email. Please check both and try again.';
    private const BLOCKED = 'Too many tries. Please wait 15 minutes, then try again.';

    public function __construct(private readonly TrackingService $tracking = new TrackingService())
    {
    }

    public function show(Request $request): Response
    {
        return $this->page();
    }

    /** Answers directly instead of redirecting, so the result and email never appear in a URL or session. */
    public function lookup(Request $request): Response
    {
        $code = $request->text('code');
        $email = $request->text('email');

        if ($this->tracking->isBlocked($request->ip())) {
            return $this->page(['error' => self::BLOCKED, 'code' => $code, 'email' => $email], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $errors = Validator::check(['code' => $code, 'email' => $email], [
            'code' => ['label' => 'Code', 'rules' => ['required', 'max:12']],
            'email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:150']],
        ]);

        if ($errors !== []) {
            return $this->page(['fieldErrors' => $errors, 'code' => $code, 'email' => $email]);
        }

        $result = $this->tracking->lookup($code, $email, $request->ip());

        if ($result === null) {
            return $this->page(['error' => self::NOT_FOUND, 'code' => $code, 'email' => $email]);
        }

        return $this->page(['result' => $result, 'email' => $email]);
    }

    /** Asks for an emailed link to edit or withdraw (BR-18). The reply is the same whether or not one was sent. */
    public function editLink(Request $request): Response
    {
        $code = $request->text('code');
        $email = $request->text('email');

        if ($this->tracking->isBlocked($request->ip())) {
            return $this->page(['error' => self::BLOCKED, 'code' => $code, 'email' => $email], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $this->tracking->emailEditLink($code, $email, $request->ip());
        $result = $this->tracking->refresh($code, $email);

        if ($result === null) {
            return $this->page(['error' => self::NOT_FOUND, 'code' => $code, 'email' => $email]);
        }

        return $this->page(['result' => $result, 'email' => $email, 'notice' => 'If this application can be changed, we emailed a link to the address on it. The link works once and expires in 72 hours.']);
    }

    /** @param array<string, mixed> $data */
    private function page(array $data = [], int $status = Response::HTTP_OK): Response
    {
        return Response::html(View::render('portal/track', $data, 'layouts/public'), $status, ['Cache-Control' => 'no-store']);
    }
}
