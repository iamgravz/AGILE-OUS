<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ForbiddenException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\DashboardService;

final class DashboardController
{
    private const VIEWS = [
        'general_member' => 'dashboard/general_member',
        'committee_head' => 'dashboard/committee_head',
        'execomm' => 'dashboard/execomm',
        'msw_member' => 'dashboard/msw_member',
        'msw_head' => 'dashboard/msw_head',
        'president' => 'dashboard/president',
    ];

    public function index(Request $request): Response
    {
        $user = current_user();
        $view = self::VIEWS[$user['role_slug']] ?? throw new ForbiddenException('dashboard');

        return Response::html(View::render($view, ['user' => $user, 'dash' => (new DashboardService())->forCurrentUser($user)], 'layouts/dashboard'));
    }

    /** All Modules, for dashboard.view_all. */
    public function overview(Request $request): Response
    {
        return Response::html(View::render('dashboard/overview', [
            'dash' => (new DashboardService())->overview(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'All Modules']],
        ], 'layouts/dashboard'));
    }
}
