<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\CandidacyService;
use App\Services\FileRejectedException;
use App\Services\FileStorageService;
use App\Services\StudentDetails;
use DomainException;

/** Run for office: members file a candidacy; the MSW Committee Head decides and marks the winners (Article VI). */
final class CandidaciesController
{
    public function __construct(private readonly CandidacyService $candidacies = new CandidacyService())
    {
    }

    public function form(Request $request): Response
    {
        return $this->memberPage([], []);
    }

    public function file(Request $request): Response
    {
        $data = ['position_id' => $request->text('position_id'), 'statement' => $request->text('statement')] + StudentDetails::fromRequest($request);
        $errors = StudentDetails::errors($data);

        if ($data['position_id'] === '') {
            $errors['position_id'] = 'Please choose the position you are running for.';
        }

        if (mb_strlen($data['statement']) > CandidacyService::STATEMENT_MAX) {
            $errors['statement'] = 'Please keep your statement to ' . CandidacyService::STATEMENT_MAX . ' characters.';
        }

        if ($errors === []) {
            try {
                $this->candidacies->file($data, ['cor' => $request->files('cor') ?? [], 'grades' => $request->files('grades') ?? []]);
                flash('success', 'Your candidacy was filed. The MSW Committee checks your COR and grades, then tells you if you are qualified.');

                return redirect(route('candidacy'));
            } catch (FileRejectedException $exception) {
                $errors['files'] = $exception->getMessage();
            } catch (DomainException $exception) {
                $errors['position_id'] = $exception->getMessage();
            }
        }

        return $this->memberPage($data, $errors);
    }

    public function withdraw(Request $request, string $id): Response
    {
        try {
            $this->candidacies->withdraw((int) $id);
            flash('success', 'Your candidacy was withdrawn.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('candidacy'));
    }

    public function document(Request $request, string $id, string $kind): Response
    {
        $row = ctype_digit($id) ? $this->candidacies->find((int) $id) : null;

        if ($row === null || !in_array($kind, ['cor', 'grades'], true) || !$this->candidacies->canView($row)) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $name = ($kind === 'cor' ? 'COR' : 'Grades') . ' - ' . $row['last_name'] . '.' . pathinfo((string) $row[$kind . '_path'], PATHINFO_EXTENSION);

        return (new FileStorageService())->stream((string) $row[$kind . '_path'], $name, true);
    }

    public function index(Request $request): Response
    {
        $filters = ['year' => $request->queryText('year'), 'status' => $request->queryText('status')];

        return Response::html(View::render('admin/candidacies', [
            'rows' => $this->candidacies->list($filters),
            'filters' => $filters,
            'filingOpen' => $this->candidacies->filingOpen(),
            'academicYear' => $this->candidacies->academicYear(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Candidacies']],
        ], 'layouts/dashboard'));
    }

    public function filing(Request $request): Response
    {
        $open = $request->text('open') === '1';
        $this->candidacies->setFilingOpen($open);
        flash('success', $open ? 'Filing of candidacy is open. Members can file under Run for office.' : 'Filing of candidacy is closed.');

        return redirect(route('admin.candidacies'));
    }

    public function decide(Request $request, string $id): Response
    {
        return $this->act(fn () => $this->candidacies->decide((int) $id, $request->text('decision'), $request->text('remarks')), 'The decision was saved.');
    }

    public function elected(Request $request, string $id): Response
    {
        return $this->act(fn () => $this->candidacies->markElected((int) $id, $request->text('remarks')), 'Marked elected. They now hold the position, and the term is recorded.');
    }

    private function act(callable $action, string $done): Response
    {
        try {
            $action();
            flash('success', $done);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('admin.candidacies'));
    }

    /**
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function memberPage(array $input, array $errors): Response
    {
        $account = current_user() ?? [];

        return Response::html(View::render('elections/candidacy', [
            'input' => $input + ['year_level' => (string) ($account['year_level'] ?? ''), 'section' => (string) ($account['section'] ?? ''), 'contact_no' => (string) ($account['contact_no'] ?? '')],
            'errors' => $errors,
            'seats' => $this->candidacies->seats(),
            'mine' => $this->candidacies->mine(),
            'filingOpen' => $this->candidacies->filingOpen(),
            'canFile' => $this->candidacies->canFile(),
            'academicYear' => $this->candidacies->academicYear(),
            'maxMb' => (new FileStorageService())->maxUploadMb(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Run for office']],
        ], 'layouts/dashboard'), $errors === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE);
    }
}
