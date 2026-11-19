<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\AiExtractionService;
use App\Services\EligibilityService;
use App\Services\FileRejectedException;
use App\Services\FileStorageService;
use App\Services\WelfareService;
use DomainException;

/** Eligibility screening. Rules and permission checks are in EligibilityService. */
final class EligibilityController
{
    public function __construct(private readonly EligibilityService $eligibility = new EligibilityService())
    {
    }

    public function index(Request $request): Response
    {
        return $this->page('eligibility/index', [
            'terms' => $this->eligibility->terms(),
            'current' => $this->eligibility->currentTerm(),
            'defaultGrace' => $this->eligibility->defaultGraceDays(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Eligibility Screening']],
        ]);
    }

    public function storeTerm(Request $request): Response
    {
        $data = [
            'academic_year' => $request->text('academic_year'),
            'semester' => $request->text('semester'),
            'screening_deadline' => $request->text('screening_deadline'),
            'grace_days' => $request->text('grace_days'),
        ];
        $errors = EligibilityService::termErrors($data);

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', $data);

            return redirect(route('eligibility'));
        }

        try {
            $this->eligibility->createTerm($data, $request->text('make_current') !== '');
            flash('success', 'The term was created.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            flash('_old_input', $data);
        }

        return redirect(route('eligibility'));
    }

    public function makeCurrent(Request $request, string $id): Response
    {
        try {
            $this->eligibility->setCurrentTerm((int) $id);
            flash('success', 'That term is now the current one.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('eligibility'));
    }

    public function showStart(Request $request): Response
    {
        $term = $this->eligibility->currentTerm();

        if ($term === null) {
            flash('warning', 'Please set a current term first.');

            return redirect(route('eligibility'));
        }

        return $this->page('eligibility/start', [
            'term' => $term,
            'covered' => $this->eligibility->coveredNotYetStarted((int) $term['term_id']),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Eligibility Screening', 'url' => route('eligibility')],
                ['label' => 'Start screening'],
            ],
        ]);
    }

    public function start(Request $request): Response
    {
        try {
            $count = $this->eligibility->startScreening();
            flash('success', "Screening started. {$count} officer(s) were asked for their documents.");
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('eligibility.review'));
    }

    public function mine(Request $request): Response
    {
        return $this->page('eligibility/mine', [
            'mine' => $this->eligibility->myScreening(),
            'maxMb' => (new FileStorageService())->maxUploadMb(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Eligibility']],
        ]);
    }

    public function submitDocuments(Request $request): Response
    {
        if ($request->text('privacy_consent') === '') {
            flash('_errors', ['privacy_consent' => 'Your consent is required before submitting.']);

            return redirect(route('my_eligibility'));
        }

        try {
            $this->eligibility->submitDocuments(WelfareService::uploadsFrom($request->files('documents')));
            flash('success', 'Your documents were submitted.');
        } catch (FileRejectedException $exception) {
            flash('_errors', ['documents' => $exception->getMessage()]);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('my_eligibility'));
    }

    public function reviews(Request $request): Response
    {
        $terms = $this->eligibility->terms();
        $selected = $request->queryText('term');
        $termIds = array_map(static fn (array $term): string => (string) $term['term_id'], $terms);
        $current = $this->eligibility->currentTerm();
        $termId = in_array($selected, $termIds, true) ? (int) $selected : (int) ($current['term_id'] ?? ($terms[0]['term_id'] ?? 0));
        $result = $request->queryText('result');
        $filter = in_array($result, EligibilityService::STATUSES, true) ? $result : '';

        return $this->page('eligibility/reviews', [
            'terms' => $terms,
            'termId' => $termId,
            'filter' => $filter,
            'result' => $this->eligibility->reviewList(['term_id' => $termId] + ($filter === '' ? [] : ['result' => $filter]), max(1, (int) $request->queryText('page'))),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Screening reviews']],
        ]);
    }

    public function review(Request $request, string $id): Response
    {
        $details = ctype_digit($id) ? $this->eligibility->openReview((int) $id) : null;

        if ($details === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $ai = new AiExtractionService();

        return $this->page('eligibility/review', $details + [
            'aiEnabled' => $ai->isEnabled(),
            // A suggestion saved before AI was turned off is no longer offered, and the President has no AI (section 6).
            'suggestion' => $ai->isEnabled() && can('eligibility.manage') ? $ai->currentSuggestion((int) $id) : null,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Screening reviews', 'url' => route('eligibility.review')],
                ['label' => $details['screening']['officer_name']],
            ],
        ]);
    }

    public function verify(Request $request, string $id): Response
    {
        $failed = $request->text('has_failed_subject');
        $enrolled = $request->text('is_officially_enrolled');
        $back = route('eligibility.review.show', ['id' => (int) $id]);
        $errors = [];

        foreach (['has_failed_subject' => $failed, 'is_officially_enrolled' => $enrolled] as $field => $value) {
            if (!in_array($value, ['yes', 'no'], true)) {
                $errors[$field] = 'Please answer yes or no.';
            }
        }

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect($back);
        }

        try {
            $result = $this->eligibility->verify((int) $id, $failed === 'yes', $enrolled === 'yes', $request->text('used_suggestion') === '1');
            flash('success', $result === 'eligible' ? 'The officer is Eligible and was notified.' : 'The officer was flagged and notified. A decision is now needed.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($back);
    }

    public function suggest(Request $request, string $id): Response
    {
        try {
            (new AiExtractionService())->suggest((int) $id);
            flash('success', 'Suggestions are ready. Nothing is saved until you press Save verification.');
        } catch (DomainException $exception) {
            flash('warning', $exception->getMessage());
        }

        return redirect(route('eligibility.review.show', ['id' => (int) $id]));
    }

    public function decide(Request $request, string $id): Response
    {
        $remarks = $request->text('remarks');
        $back = route('eligibility.review.show', ['id' => (int) $id]);
        $errors = Validator::check(['remarks' => $remarks], ['remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']]]);

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect($back);
        }

        try {
            $this->eligibility->decide((int) $id, $request->text('decision'), $remarks);
            flash('success', 'The decision was saved, and the officer was notified.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($back);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
