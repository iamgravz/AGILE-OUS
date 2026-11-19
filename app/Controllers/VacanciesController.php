<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\RecruitmentService;
use DateTimeImmutable;
use DomainException;

/** Vacancies, for the MSW Committee Head. Rules and permission checks are in RecruitmentService. */
final class VacanciesController
{
    public function __construct(private readonly RecruitmentService $recruitment = new RecruitmentService())
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->queryText('status');
        $form = [
            'status' => in_array($status, RecruitmentService::VACANCY_STATUSES, true) ? $status : '',
            'q' => $request->queryText('q'),
        ];

        return $this->page('recruitment/vacancies/index', [
            'result' => $this->recruitment->listVacancies(array_filter($form, static fn (string $value): bool => $value !== ''), max(1, (int) $request->queryText('page'))),
            'filters' => $form,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Vacancies']],
        ]);
    }

    /** Without ?request= this lists the approved requests to choose from, because a vacancy needs one (BR-04). */
    public function create(Request $request): Response
    {
        $requestId = $request->queryText('request');
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Vacancies', 'url' => route('vacancies')],
            ['label' => 'Create vacancy'],
        ];

        if ($requestId === '') {
            return $this->page('recruitment/vacancies/choose_request', ['requests' => $this->recruitment->requestsReadyForVacancy(), 'breadcrumbs' => $breadcrumbs]);
        }

        $hrRequest = ctype_digit($requestId) ? $this->recruitment->requestForNewVacancy((int) $requestId) : null;

        if ($hrRequest === null) {
            flash('warning', 'A vacancy can only be created from an approved HR request that does not have one yet.');

            return redirect(route('vacancies.create'));
        }

        return $this->page('recruitment/vacancies/form', ['vacancy' => null, 'hrRequest' => $hrRequest, 'breadcrumbs' => $breadcrumbs]);
    }

    public function store(Request $request): Response
    {
        $requestId = $request->text('request_id');
        $publish = $request->text('intent') === 'publish';
        $data = $this->postData($request);
        $back = route('vacancies.create') . '?request=' . (int) $requestId;
        $errors = $this->errors($data, $publish);

        if ($errors !== []) {
            return $this->backWithErrors($back, $errors, $request);
        }

        try {
            $id = $this->recruitment->createVacancy((int) $requestId, $this->values($data), $publish);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());

            return redirect(route('vacancies.create'));
        }

        flash('success', $publish ? 'The vacancy is published.' : 'The vacancy was saved as a draft.');

        return redirect(route('vacancies.show', ['id' => $id]));
    }

    public function show(Request $request, string $id): Response
    {
        $vacancy = ctype_digit($id) ? $this->recruitment->findVacancy((int) $id) : null;

        if ($vacancy === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->page('recruitment/vacancies/show', ['vacancy' => $vacancy, 'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Vacancies', 'url' => route('vacancies')],
            ['label' => $vacancy['title']],
        ]] + $this->recruitment->vacancyDetails((int) $id));
    }

    public function edit(Request $request, string $id): Response
    {
        $vacancy = ctype_digit($id) ? $this->recruitment->findVacancy((int) $id) : null;

        if ($vacancy === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        if ($vacancy['status'] !== 'draft') {
            flash('warning', 'Only a draft vacancy can be edited.');

            return redirect(route('vacancies.show', ['id' => (int) $id]));
        }

        return $this->page('recruitment/vacancies/form', ['vacancy' => $vacancy, 'hrRequest' => null, 'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Vacancies', 'url' => route('vacancies')],
            ['label' => 'Edit vacancy'],
        ]]);
    }

    public function update(Request $request, string $id): Response
    {
        $publish = $request->text('intent') === 'publish';
        $data = $this->postData($request);
        $errors = $this->errors($data, $publish);
        $show = route('vacancies.show', ['id' => (int) $id]);

        if ($errors !== []) {
            return $this->backWithErrors(route('vacancies.edit', ['id' => (int) $id]), $errors, $request);
        }

        try {
            $this->recruitment->updateDraft((int) $id, $this->values($data), $publish);
            flash('success', $publish ? 'The vacancy is published.' : 'The draft was saved.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($show);
    }

    public function publish(Request $request, string $id): Response
    {
        try {
            $this->recruitment->publishVacancy((int) $id);
            flash('success', 'The vacancy is published.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('vacancies.show', ['id' => (int) $id]));
    }

    public function reopen(Request $request, string $id): Response
    {
        $closesAt = $this->parseDate($request->text('closes_at'));
        $show = route('vacancies.show', ['id' => (int) $id]);

        if ($closesAt === null) {
            flash('_errors', ['closes_at' => 'Enter a new closing date and time.']);

            return redirect($show);
        }

        try {
            $this->recruitment->reopenVacancy((int) $id, $closesAt);
            flash('success', 'The vacancy is open again.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($show);
    }

    public function cancel(Request $request, string $id): Response
    {
        $remarks = $request->text('remarks');
        $errors = Validator::check(['remarks' => $remarks], ['remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']]]);
        $show = route('vacancies.show', ['id' => (int) $id]);

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect($show);
        }

        try {
            $this->recruitment->cancelVacancy((int) $id, $remarks);
            flash('success', 'The vacancy was cancelled.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($show);
    }

    /** @return array<string, string> */
    private function postData(Request $request): array
    {
        return [
            'title' => $request->text('title'),
            'description' => $request->text('description'),
            'opens_at' => $request->text('opens_at'),
            'closes_at' => $request->text('closes_at'),
        ];
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function errors(array $data, bool $publish): array
    {
        $errors = Validator::check($data, [
            'title' => ['label' => 'Title', 'rules' => ['required', 'max:150']],
            'description' => ['label' => 'Description', 'rules' => ['required', 'max:5000']],
            'opens_at' => ['label' => 'Opening date', 'rules' => ['required']],
            'closes_at' => ['label' => 'Closing date', 'rules' => ['required']],
        ]);

        foreach (['opens_at', 'closes_at'] as $field) {
            if (!isset($errors[$field]) && $this->parseDate($data[$field]) === null) {
                $errors[$field] = 'Enter a valid date and time.';
            }
        }

        if (!isset($errors['opens_at']) && !isset($errors['closes_at'])) {
            $errors += RecruitmentService::scheduleErrors((string) $this->parseDate($data['opens_at']), (string) $this->parseDate($data['closes_at']), $publish, date('Y-m-d H:i:s'));
        }

        return $errors;
    }

    /**
     * @param array<string, string> $data
     * @return array{title: string, description: string, opens_at: string, closes_at: string}
     */
    private function values(array $data): array
    {
        return ['opens_at' => (string) $this->parseDate($data['opens_at']), 'closes_at' => (string) $this->parseDate($data['closes_at'])] + $data;
    }

    private function parseDate(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);

        return $date === false ? null : $date->format('Y-m-d H:i:s');
    }

    /** @param array<string, string> $errors */
    private function backWithErrors(string $to, array $errors, Request $request): Response
    {
        flash('_errors', $errors);
        flash('_old_input', $this->postData($request));

        return redirect($to);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'));
    }
}
