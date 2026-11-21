<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\ApplicationStatus;
use App\Services\AppointmentService;
use App\Services\EvaluationService;
use App\Services\InterviewService;
use App\Services\MembershipService;
use DomainException;

/** Staff screens for processing applications. Rules and permission checks are in MembershipService. */
final class ApplicationsController
{
    private const SORTS = ['submitted_at', 'status', 'tracking_code', 'applicant'];

    public function __construct(private readonly MembershipService $membership = new MembershipService())
    {
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $sort = in_array($request->queryText('sort'), self::SORTS, true) ? $request->queryText('sort') : 'submitted_at';
        $direction = $request->queryText('dir') === 'asc' ? 'asc' : 'desc';

        return Response::html(View::render('applications/index', [
            'result' => $this->membership->listForStaff($filters['service'], $sort, $direction, max(1, (int) $request->queryText('page'))),
            'filters' => $filters['form'],
            'sort' => $sort,
            'direction' => $direction,
            'vacancies' => $this->membership->vacancyFilterOptions(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Applications']],
        ], 'layouts/dashboard'));
    }

    public function committee(Request $request): Response
    {
        $status = $request->queryText('status');
        $vacancy = $request->queryText('vacancy');
        $form = ['status' => in_array($status, ApplicationStatus::ALL, true) ? $status : '', 'vacancy' => ctype_digit($vacancy) ? $vacancy : ''];
        $filters = array_filter(['status' => $form['status']], static fn (string $value): bool => $value !== '');

        if ($form['vacancy'] !== '') {
            $filters['vacancy_id'] = (int) $form['vacancy'];
        }

        return Response::html(View::render('applications/committee', $this->membership->listForCommitteeHead($filters) + [
            'filters' => $form,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Applications to my committee']],
        ], 'layouts/dashboard'));
    }

    public function show(Request $request, string $id): Response
    {
        $application = ctype_digit($id) ? $this->membership->openForStaff((int) $id) : null;

        if ($application === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $interviewPanel = can('interview.manage') || $this->membership->isOwnCommitteeHead($application) ? (new InterviewService())->panel($application) : [];
        $appointments = can('appointment.prepare') ? new AppointmentService() : null;

        return Response::html(View::render('applications/show', [
            'application' => $application,
            'activeTab' => $request->queryText('tab') ?: 'overview',
            'appointment' => $appointments?->forApplication((int) $id),
            'canPrepareAppointment' => $appointments?->forPrepare((int) $id) !== null,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Applications', 'url' => route('applications')],
                ['label' => $application['tracking_code']],
            ],
        ] + $this->membership->staffDetails($application) + (new EvaluationService())->panel($application) + $interviewPanel, 'layouts/dashboard'));
    }

    /** A fragment for the list's side panel on desktop. Opening it counts as a view. */
    public function drawer(Request $request, string $id): Response
    {
        $application = ctype_digit($id) ? $this->membership->openForStaff((int) $id) : null;

        if ($application === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return Response::html(View::render('applications/_drawer', ['application' => $application] + $this->membership->staffDetails($application)));
    }

    public function verifyDocument(Request $request, string $id, string $documentId): Response
    {
        $decision = $request->text('decision');

        try {
            if (!in_array($decision, ['verified', 'needs_correction', 'rejected'], true)) {
                throw new DomainException('Please choose a decision for this document.');
            }

            $this->membership->verifyDocument((int) $id, (int) $documentId, $decision, $request->text('remarks'));
            flash('success', 'The document was marked as ' . str_replace('_', ' ', $decision) . '.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('applications.show', ['id' => (int) $id]) . '?tab=documents');
    }

    public function markUnderReview(Request $request, string $id): Response
    {
        return $this->act($request, $id, 'markUnderReview', 'The application is now Under Review.');
    }

    public function returnForRevision(Request $request, string $id): Response
    {
        return $this->act($request, $id, 'returnForRevision', 'The application was returned for revision, and the applicant was notified.');
    }

    public function approve(Request $request, string $id): Response
    {
        return $this->act($request, $id, 'approve', 'The application was approved. The applicant was notified.');
    }

    public function reject(Request $request, string $id): Response
    {
        return $this->act($request, $id, 'reject', 'The application was rejected, and the applicant was notified.');
    }

    public function waitlist(Request $request, string $id): Response
    {
        return $this->act($request, $id, 'waitlist', 'The application was waitlisted, and the applicant was notified. You can still approve or reject it later.');
    }

    private function act(Request $request, string $id, string $method, string $message): Response
    {
        $remarks = $request->text('remarks');
        $errors = Validator::check(['remarks' => $remarks], ['remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']]]);
        $back = route('applications.show', ['id' => (int) $id]);

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect($back);
        }

        try {
            $this->membership->$method((int) $id, $remarks);
            flash('success', $message);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($back);
    }

    /** @return array{service: array<string, mixed>, form: array<string, string>} */
    private function filters(Request $request): array
    {
        $status = $request->queryText('status');
        $type = $request->queryText('type');
        $from = $request->queryText('from');
        $to = $request->queryText('to');
        $search = $request->queryText('q');

        $validDate = static fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && strtotime($date) !== false;
        $form = [
            'status' => in_array($status, ApplicationStatus::ALL, true) && $status !== 'draft' ? $status : '',
            'type' => in_array($type, ['general', 'committee'], true) || ctype_digit($type) ? $type : '',
            'from' => $validDate($from) ? $from : '',
            'to' => $validDate($to) ? $to : '',
            'q' => $search,
        ];

        $service = array_filter([
            'status' => $form['status'],
            'type' => in_array($form['type'], ['general', 'committee'], true) ? $form['type'] : '',
            'from' => $form['from'],
            'to' => $form['to'],
            'q' => $form['q'],
        ], static fn (string $value): bool => $value !== '');

        if (ctype_digit($form['type'])) {
            $service['vacancy_id'] = (int) $form['type'];
        }

        return ['service' => $service, 'form' => $form];
    }
}
