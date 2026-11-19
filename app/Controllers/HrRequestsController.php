<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\RecruitmentService;
use DomainException;

/** HR requests. Rules and permission checks are in RecruitmentService. */
final class HrRequestsController
{
    public function __construct(private readonly RecruitmentService $recruitment = new RecruitmentService())
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->queryText('status');
        $form = [
            'status' => in_array($status, RecruitmentService::REQUEST_STATUSES, true) ? $status : '',
            'q' => $request->queryText('q'),
        ];

        return $this->page('recruitment/hr_requests/index', [
            'result' => $this->recruitment->listRequests(array_filter($form, static fn (string $value): bool => $value !== ''), max(1, (int) $request->queryText('page'))),
            'filters' => $form,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'HR Requests']],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $data = $this->postData($request);
        $errors = $this->errors($data);

        if ($errors !== []) {
            return $this->backWithErrors(route('hr_requests.create'), $errors, $data);
        }

        try {
            $id = $this->recruitment->submitRequest($this->values($data));
        } catch (DomainException $exception) {
            return $this->backWithErrors(route('hr_requests.create'), ['position_id' => $exception->getMessage()], $data);
        }

        flash('success', 'Your HR request was submitted, and the MSW Committee Head was notified.');

        return redirect(route('hr_requests.show', ['id' => $id]));
    }

    public function show(Request $request, string $id): Response
    {
        $hrRequest = ctype_digit($id) ? $this->recruitment->findRequest((int) $id) : null;

        if ($hrRequest === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->page('recruitment/hr_requests/show', ['hrRequest' => $hrRequest, 'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Requests', 'url' => route('hr_requests')],
            ['label' => 'Request ' . (int) $hrRequest['request_id']],
        ]] + $this->recruitment->requestDetails($hrRequest));
    }

    public function edit(Request $request, string $id): Response
    {
        $hrRequest = ctype_digit($id) ? $this->recruitment->findRequest((int) $id) : null;

        if ($hrRequest === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        if ($hrRequest['status'] !== 'returned' || (int) $hrRequest['requested_by'] !== current_user_id()) {
            flash('warning', 'Only the person who filed a request can edit it, and only after it is returned.');

            return redirect(route('hr_requests.show', ['id' => (int) $id]));
        }

        return $this->form($hrRequest);
    }

    public function update(Request $request, string $id): Response
    {
        $data = $this->postData($request);
        $errors = $this->errors($data);
        $back = route('hr_requests.edit', ['id' => (int) $id]);

        if ($errors !== []) {
            return $this->backWithErrors($back, $errors, $data);
        }

        try {
            $this->recruitment->resubmitRequest((int) $id, $this->values($data));
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());

            return redirect(route('hr_requests.show', ['id' => (int) $id]));
        }

        flash('success', 'Your HR request was resubmitted, and the MSW Committee Head was notified.');

        return redirect(route('hr_requests.show', ['id' => (int) $id]));
    }

    public function approve(Request $request, string $id): Response
    {
        return $this->decide($request, $id, 'approveRequest', 'The HR request was approved. You can now create its vacancy.');
    }

    public function returnToRequester(Request $request, string $id): Response
    {
        return $this->decide($request, $id, 'returnRequest', 'The HR request was returned to the person who filed it.');
    }

    public function cancel(Request $request, string $id): Response
    {
        try {
            $this->recruitment->cancelRequest((int) $id);
            flash('success', 'The HR request was cancelled.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('hr_requests.show', ['id' => (int) $id]));
    }

    private function decide(Request $request, string $id, string $method, string $message): Response
    {
        $remarks = $request->text('remarks');
        $errors = Validator::check(['remarks' => $remarks], ['remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']]]);

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect(route('hr_requests.show', ['id' => (int) $id]));
        }

        try {
            $this->recruitment->$method((int) $id, $remarks);
            flash('success', $message);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('hr_requests.show', ['id' => (int) $id]));
    }

    /** @param array<string, mixed>|null $hrRequest */
    private function form(?array $hrRequest): Response
    {
        $heading = $hrRequest === null ? 'New HR request' : 'Edit HR request';

        return $this->page('recruitment/hr_requests/form', [
            'hrRequest' => $hrRequest,
            'positions' => $this->recruitment->ownPositions(),
            'heading' => $heading,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'HR Requests', 'url' => route('hr_requests')],
                ['label' => $heading],
            ],
        ]);
    }

    /** @return array<string, string> */
    private function postData(Request $request): array
    {
        return [
            'position_id' => $request->text('position_id'),
            'slots' => $request->text('slots'),
            'justification' => $request->text('justification'),
            'qualifications' => $request->text('qualifications'),
        ];
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function errors(array $data): array
    {
        $errors = Validator::check($data, [
            'position_id' => ['label' => 'Position', 'rules' => ['required', 'regex:/^\d{1,10}$/']],
            'slots' => ['label' => 'Slots', 'rules' => ['required', 'regex:/^\d{1,3}$/'], 'message' => 'Enter a whole number of slots.'],
            'justification' => ['label' => 'Justification', 'rules' => ['required', 'max:2000']],
            'qualifications' => ['label' => 'Qualifications', 'rules' => ['required', 'max:2000']],
        ]);

        if (!isset($errors['slots']) && ((int) $data['slots'] < 1 || (int) $data['slots'] > RecruitmentService::MAX_SLOTS)) {
            $errors['slots'] = 'Slots must be between 1 and ' . RecruitmentService::MAX_SLOTS . '.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $data
     * @return array{position_id: int, slots: int, justification: string, qualifications: string}
     */
    private function values(array $data): array
    {
        return ['position_id' => (int) $data['position_id'], 'slots' => (int) $data['slots']] + $data;
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $data
     */
    private function backWithErrors(string $to, array $errors, array $data): Response
    {
        flash('_errors', $errors);
        flash('_old_input', $data);

        return redirect($to);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'));
    }
}
