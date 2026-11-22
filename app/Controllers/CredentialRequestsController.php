<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\CredentialRequestService;
use App\Services\FileRejectedException;
use App\Services\FileStorageService;
use DomainException;

/** ID and certificate requests: members ask, and the MSW Committee Head uploads the file made from AGILE's template. */
final class CredentialRequestsController
{
    public function __construct(private readonly CredentialRequestService $requests = new CredentialRequestService())
    {
    }

    public function request(Request $request): Response
    {
        try {
            $this->requests->request($request->text('type'), $request->text('note'));
            flash('success', 'Your request was sent. We will let you know when it is ready to download.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('account') . '#credential-requests');
    }

    public function cancel(Request $request, string $id): Response
    {
        try {
            $this->requests->cancel((int) $id);
            flash('success', 'Your request was cancelled.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('account') . '#credential-requests');
    }

    public function index(Request $request): Response
    {
        $status = $request->queryText('status') === '' ? 'requested' : $request->queryText('status');

        return Response::html(View::render('credentials/requests', [
            'rows' => $this->requests->list($status === 'all' ? '' : $status),
            'status' => $status,
            'maxMb' => (new FileStorageService())->maxUploadMb(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'ID and certificate requests']],
        ], 'layouts/dashboard'));
    }

    public function upload(Request $request, string $id): Response
    {
        try {
            $this->requests->upload((int) $id, $request->files('file') ?? []);
            flash('success', 'Uploaded. The member can download it from My Account now.');
        } catch (FileRejectedException | DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('credentials.requests'));
    }

    /** The member's QR code as a PNG, to put on the ID made from AGILE's template. */
    public function qr(Request $request, string $memberId): Response
    {
        $member = ctype_digit($memberId) ? (new \App\Models\MemberRepository())->find((int) $memberId) : null;

        if ($member === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $png = (new \App\Services\MemberVerificationService())->qrPng((int) $memberId);

        return new Response($png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
            'Content-Disposition' => 'attachment; filename="QR-' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $member['member_no']) . '.png"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function decline(Request $request, string $id): Response
    {
        try {
            $this->requests->decline((int) $id, $request->text('remarks'));
            flash('success', 'The request was declined, and the member was told why.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('credentials.requests'));
    }
}
