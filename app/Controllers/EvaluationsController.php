<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\EvaluationService;
use DomainException;

/** Rubric evaluations, the evaluator's queue and the committee head's read-only view. */
final class EvaluationsController
{
    public function __construct(private readonly EvaluationService $evaluations = new EvaluationService())
    {
    }

    public function index(Request $request): Response
    {
        $search = $request->queryText('q');

        return Response::html(View::render('recruitment/evaluations/index', [
            'result' => $this->evaluations->queue($search === '' ? [] : ['q' => $search], max(1, (int) $request->queryText('page'))),
            'q' => $search,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Evaluations']],
        ], 'layouts/dashboard'));
    }

    public function committee(Request $request): Response
    {
        return Response::html(View::render('recruitment/evaluations/committee', [
            'vacancies' => $this->evaluations->committeeVacancies(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Committee']],
        ], 'layouts/dashboard'));
    }

    public function submit(Request $request, string $id): Response
    {
        $scores = $request->input('scores', []);
        $recommendation = $request->text('recommendation');
        $remarks = $request->text('evaluation_remarks');
        $back = route('applications.show', ['id' => (int) $id]) . '?tab=evaluations';
        $errors = Validator::check(['evaluation_remarks' => $remarks], ['evaluation_remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']]]);

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', ['scores' => is_array($scores) ? $scores : [], 'recommendation' => $recommendation, 'evaluation_remarks' => $remarks]);

            return redirect($back);
        }

        try {
            $this->evaluations->submit((int) $id, is_array($scores) ? $scores : [], $recommendation, $remarks);
            flash('success', 'Your evaluation was saved.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            flash('_old_input', ['scores' => is_array($scores) ? $scores : [], 'recommendation' => $recommendation, 'evaluation_remarks' => $remarks]);
        }

        return redirect($back);
    }
}
