<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ServiceRequestService;
use DomainException;

/** My requests: a member's service requests and their threads. Rules are in ServiceRequestService. */
final class ServiceRequestsController
{
    public function __construct(private readonly ServiceRequestService $requests = new ServiceRequestService())
    {
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('requests/index', [
            'requests' => $this->requests->mine(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Requests']],
        ], 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    public function store(Request $request): Response
    {
        $form = ['type' => $request->text('type'), 'subject' => $request->text('subject'), 'body' => $request->text('body')];
        $errors = ServiceRequestService::errors($form['type'], $form['subject'], $form['body']);

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', $form);

            return redirect(route('requests') . '#new-request');
        }

        $id = $this->requests->create($form['type'], $form['subject'], $form['body']);
        flash('success', 'Your request ' . ServiceRequestService::code($id) . ' was sent to the MSW Committee.');

        return redirect(route('requests.show', ['id' => $id]));
    }

    public function show(Request $request, string $id): Response
    {
        $thread = ctype_digit($id) ? $this->requests->open((int) $id) : null;

        if ($thread === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $code = ServiceRequestService::code((int) $id);

        return Response::html(View::render('requests/show', $thread + [
            'code' => $code,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Requests', 'url' => route('requests')], ['label' => $code]],
        ], 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    public function reply(Request $request, string $id): Response
    {
        try {
            $this->requests->reply((int) $id, $request->text('body'));
            flash('success', 'Your reply was added.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('requests.show', ['id' => (int) $id]) . '#reply');
    }

    public function cancel(Request $request, string $id): Response
    {
        try {
            $this->requests->cancel((int) $id);
            flash('success', 'The request was cancelled.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('requests.show', ['id' => (int) $id]));
    }
}
