<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\RecruitmentService;

/** My Committee: a committee head's read-only view of their vacancies and who applied. */
final class MyCommitteeController
{
    public function show(Request $request): Response
    {
        return Response::html(View::render('recruitment/committee', [
            'vacancies' => (new RecruitmentService())->committeeVacancies(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Committee']],
        ], 'layouts/dashboard'));
    }
}
