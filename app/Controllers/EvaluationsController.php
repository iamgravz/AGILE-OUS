<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\EvaluationService;

/** My Committee: the committee head's read-only view of their vacancies. (Rubric scoring was removed on Nov 23, 2026.) */
final class EvaluationsController
{
    public function __construct(private readonly EvaluationService $evaluations = new EvaluationService())
    {
    }

    public function committee(Request $request): Response
    {
        return Response::html(View::render('recruitment/evaluations/committee', [
            'vacancies' => $this->evaluations->committeeVacancies(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Committee']],
        ], 'layouts/dashboard'));
    }
}
