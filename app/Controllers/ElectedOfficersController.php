<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\ElectedOfficerService;
use App\Services\StudentDetails;
use DomainException;

/** Administration, Elected officers: accounts for the Executive Board and the Year-Level Representatives. */
final class ElectedOfficersController
{
    public function __construct(private readonly ElectedOfficerService $officers = new ElectedOfficerService())
    {
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/elected_officers', [
            'seats' => $this->officers->seats(),
            'chosen' => (int) $request->queryText('position'),
            'academicYear' => $this->officers->defaultAcademicYear(),
            'confirmations' => config('elections.confirmations'),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Elected officers']],
        ], 'layouts/dashboard'));
    }

    public function store(Request $request): Response
    {
        $data = [
            'position_id' => $request->text('position_id'),
            'first_name' => $request->text('first_name'),
            'last_name' => $request->text('last_name'),
            'email' => $request->text('email'),
            'student_no' => strtoupper($request->text('student_no')),
            'reason' => $request->text('reason'),
            'academic_year' => $request->text('academic_year'),
        ] + StudentDetails::fromRequest($request);
        $errors = Validator::check($data, [
            'position_id' => ['label' => 'Position', 'rules' => ['required']],
            'first_name' => ['label' => 'First name', 'rules' => ['required', 'max:80']],
            'last_name' => ['label' => 'Last name', 'rules' => ['required', 'max:80']],
            'email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:150']],
            'student_no' => ['label' => 'PUP student number', 'rules' => ['required', 'max:20']],
            'reason' => ['label' => 'Election record', 'rules' => ['required', 'max:255']],
            'academic_year' => ['label' => 'Academic year', 'rules' => ['required', 'max:9']],
        ]);

        $errors += StudentDetails::errors($data);
        $ticked = $request->input('confirmations', []);
        $data['confirmations'] = is_array($ticked) ? array_values(array_filter($ticked, 'is_string')) : [];

        if ($errors === []) {
            try {
                $userId = $this->officers->record((int) $data['position_id'], $data);
                flash('success', 'Recorded. The officer now holds the position; a new account also gets an email with the link to set a password.');

                return redirect(route('admin.users.show', ['id' => $userId]));
            } catch (DomainException $exception) {
                $errors = ['position_id' => $exception->getMessage()];
            }
        }

        flash('_errors', $errors);
        flash('_old_input', $data);

        return redirect(route('admin.elected_officers'));
    }
}
