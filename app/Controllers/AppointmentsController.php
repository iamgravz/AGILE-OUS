<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\AppointmentService;
use DateTimeImmutable;
use DomainException;

/** Appointments. Rules and permission checks are in AppointmentService. */
final class AppointmentsController
{
    public function __construct(private readonly AppointmentService $appointments = new AppointmentService())
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->queryText('status');
        $filter = in_array($status, AppointmentService::STATUSES, true) ? $status : '';

        return Response::html(View::render('recruitment/appointments/index', [
            'result' => $this->appointments->list($filter === '' ? [] : ['status' => $filter], max(1, (int) $request->queryText('page'))),
            'ready' => $this->appointments->readyToPrepare(),
            'filter' => $filter,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Appointments']],
        ], 'layouts/dashboard'));
    }

    public function showPrepare(Request $request): Response
    {
        $applicationId = $request->queryText('application');
        $data = ctype_digit($applicationId) ? $this->appointments->forPrepare((int) $applicationId) : null;

        if ($data === null) {
            flash('warning', 'An appointment can only be prepared for an approved position application with a free slot and no appointment yet.');

            return redirect(route('appointments'));
        }

        return Response::html(View::render('recruitment/appointments/prepare', $data + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Appointments', 'url' => route('appointments')],
                ['label' => 'Prepare appointment'],
            ],
        ], 'layouts/dashboard'));
    }

    public function prepare(Request $request): Response
    {
        $applicationId = (int) $request->text('application_id');
        $form = ['effective_date' => $request->text('effective_date'), 'remarks' => $request->text('remarks')];
        $back = route('appointments.prepare') . '?application=' . $applicationId;
        $errors = Validator::check($form, [
            'effective_date' => ['label' => 'Effective date', 'rules' => ['required']],
            'remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']],
        ]);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $form['effective_date']);

        if (!isset($errors['effective_date']) && ($date === false || $date->format('Y-m-d') !== $form['effective_date'])) {
            $errors['effective_date'] = 'Enter a valid date.';
        } elseif (!isset($errors['effective_date']) && $form['effective_date'] < date('Y-m-d')) {
            $errors['effective_date'] = 'The effective date cannot be in the past.';
        }

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', $form);

            return redirect($back);
        }

        try {
            $id = $this->appointments->prepare($applicationId, $form['effective_date'], $form['remarks']);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());

            return redirect(route('appointments'));
        }

        flash('success', 'The appointment was prepared. Nothing changes until the MSW Committee Head approves it.');

        return redirect(route('appointments.show', ['id' => $id]));
    }

    public function show(Request $request, string $id): Response
    {
        $appointment = ctype_digit($id) ? $this->appointments->find((int) $id) : null;

        if ($appointment === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return Response::html(View::render('recruitment/appointments/show', ['appointment' => $appointment, 'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Appointments', 'url' => route('appointments')],
            ['label' => $appointment['tracking_code']],
        ]] + $this->appointments->details($appointment), 'layouts/dashboard'));
    }

    public function approve(Request $request, string $id): Response
    {
        $form = [
            'remarks' => $request->text('remarks'),
            'role_override' => $request->text('role_override'),
            'override_reason' => $request->text('override_reason'),
        ];
        $back = route('appointments.show', ['id' => (int) $id]);
        $errors = Validator::check($form, [
            'remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']],
            'override_reason' => ['label' => 'Reason', 'rules' => ['max:255']],
        ]);

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', $form);

            return redirect($back);
        }

        try {
            $this->appointments->approve((int) $id, $form['remarks'], $form['role_override'], $form['override_reason']);
            flash('success', 'The appointment was approved. The member\'s role, credentials and the vacancy were updated.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            flash('_old_input', $form);
        }

        return redirect($back);
    }

    public function returnAppointment(Request $request, string $id): Response
    {
        $remarks = $request->text('remarks');
        $back = route('appointments.show', ['id' => (int) $id]);
        $errors = Validator::check(['remarks' => $remarks], ['remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']]]);

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect($back);
        }

        try {
            $this->appointments->returnAppointment((int) $id, $remarks);
            flash('success', 'The appointment was returned. A new one can be prepared.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($back);
    }
}
