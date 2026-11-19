<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\FileRejectedException;
use App\Services\FileStorageService;
use App\Services\WelfareService;
use DomainException;

/**
 * The logged-in submitter's side of welfare. A failed form is shown again from this request instead of a redirect,
 * so what someone wrote about their concern is never copied into the session.
 */
final class WelfareController
{
    public function __construct(private readonly WelfareService $welfare = new WelfareService())
    {
    }

    public function create(Request $request): Response
    {
        $related = $request->queryText('related');

        return $this->form(['related_case_id' => ctype_digit($related) ? $related : ''], []);
    }

    public function store(Request $request): Response
    {
        $input = [
            'category_id' => $request->text('category_id'),
            'subject' => $request->text('subject'),
            'description' => $request->text('description'),
            'urgent' => $request->text('urgent') !== '',
        ];
        $related = $request->text('related_case_id');
        $errors = $this->welfare->concernErrors($input);

        if ($request->text('privacy_consent') === '') {
            $errors['privacy_consent'] = 'Your consent is required before submitting.';
        }

        if ($errors === []) {
            try {
                $case = $this->welfare->submit($input, ctype_digit($related) ? (int) $related : null, WelfareService::uploadsFrom($request->files('attachments')));
                flash('success', "We received your concern. Your case code is {$case['case_code']}.");

                return redirect(route('my_welfare_cases.show', ['id' => (int) $case['case_id']]));
            } catch (FileRejectedException $exception) {
                $errors['attachments'] = $exception->getMessage();
            } catch (DomainException $exception) {
                $errors['related'] = $exception->getMessage();
            }
        }

        return $this->form($input + ['related_case_id' => $related], $errors);
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('welfare/index', [
            'result' => $this->welfare->myCases(max(1, (int) $request->queryText('page'))),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Welfare Cases']],
        ], 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    public function show(Request $request, string $id): Response
    {
        $details = ctype_digit($id) ? $this->welfare->openOwn((int) $id) : null;

        if ($details === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return Response::html(View::render('welfare/show', $details + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'My Welfare Cases', 'url' => route('my_welfare_cases')],
                ['label' => $details['case']['case_code']],
            ],
        ], 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private function form(array $input, array $errors): Response
    {
        return Response::html(View::render('welfare/new', [
            'input' => $input,
            'errors' => $errors,
            'categories' => $this->welfare->categories(),
            'closedCases' => $this->welfare->myClosedCases(),
            'maxMb' => (new FileStorageService())->maxUploadMb(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Ask for Help']],
        ], 'layouts/dashboard'), $errors === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE, ['Cache-Control' => 'no-store']);
    }
}
