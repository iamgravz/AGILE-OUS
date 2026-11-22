<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\ElectedOfficerService;
use DomainException;

/** Administration, Elected officers: accounts for the Executive Board and the Year-Level Representatives. */
final class ElectedOfficersController
{
    /** Article V Section 3 has four year levels. */
    private const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

    public function __construct(private readonly ElectedOfficerService $officers = new ElectedOfficerService())
    {
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/elected_officers', [
            'seats' => $this->officers->seats(),
            'chosen' => (int) $request->queryText('position'),
            'yearLevels' => self::YEAR_LEVELS,
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
            'contact_no' => $request->text('contact_no'),
            'reason' => $request->text('reason'),
            'year_level' => $request->text('year_level'),
            'academic_year' => $request->text('academic_year'),
        ];
        $ticked = $request->input('confirmations', []);
        $data['confirmations'] = is_array($ticked) ? array_values(array_filter($ticked, 'is_string')) : [];
        $errors = Validator::check($data, [
            'position_id' => ['label' => 'Position', 'rules' => ['required']],
            'first_name' => ['label' => 'First name', 'rules' => ['required', 'max:80']],
            'last_name' => ['label' => 'Last name', 'rules' => ['required', 'max:80']],
            'email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:150']],
            'student_no' => ['label' => 'PUP student number', 'rules' => ['required', 'max:20']],
            'reason' => ['label' => 'Election record', 'rules' => ['required', 'max:255']],
            'academic_year' => ['label' => 'Academic year', 'rules' => ['required', 'max:9']],
        ]);

        if (!in_array($data['year_level'], self::YEAR_LEVELS, true)) {
            $errors['year_level'] = 'Please choose the year level.';
        }

        if ($data['contact_no'] !== '' && preg_match(Validator::PHONE_PATTERN, $data['contact_no']) !== 1) {
            $errors['contact_no'] = 'Enter a valid contact number, such as 09171234567.';
        }

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
