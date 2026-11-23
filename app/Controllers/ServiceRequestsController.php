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

    public function queue(Request $request): Response
    {
        $status = $request->queryText('status');

        return Response::html(View::render('requests/queue', [
            'requests' => $this->requests->queue($status),
            'counts' => $this->requests->counts(),
            'status' => isset(ServiceRequestService::STATUSES[$status]) ? $status : '',
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Service requests']],
        ], 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    public function manage(Request $request, string $id): Response
    {
        $assignee = $request->text('assigned_to');

        try {
            $this->requests->manage((int) $id, $request->text('status'), ctype_digit($assignee) ? (int) $assignee : null);
            flash('success', 'The request was updated.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('requests.show', ['id' => (int) $id]));
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
        $isOwner = (int) $thread['request']['user_id'] === current_user_id();
        $back = $isOwner ? ['label' => 'My Requests', 'url' => route('requests')] : ['label' => 'Service requests', 'url' => route('requests.queue')];

        return Response::html(View::render('requests/show', $thread + [
            'code' => $code,
            'handlers' => !$isOwner && can('service_request.manage') ? $this->requests->handlers() : [],
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], $back, ['label' => $code]],
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
