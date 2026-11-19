<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\SearchService;

final class SearchController
{
    public function index(Request $request): Response
    {
        $query = $request->queryText('q');

        return Response::html(View::render('search/index', [
            'query' => $query,
            'groups' => (new SearchService())->search($query),
            'minLength' => SearchService::MIN_LENGTH,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Search']],
        ], 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
