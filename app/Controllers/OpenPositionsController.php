<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\OpenPositionsService;
use DomainException;

/** Administration, Open for application. Rules and the permission check are in OpenPositionsService. */
final class OpenPositionsController
{
    public function __construct(private readonly OpenPositionsService $service = new OpenPositionsService())
    {
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/open_positions', $this->service->overview() + [
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Open for application']],
        ], 'layouts/dashboard'));
    }

    public function save(Request $request): Response
    {
        $open = $request->input('open', []);
        $targets = $request->input('target', []);

        try {
            $this->service->save(is_array($open) ? array_values($open) : [], is_array($targets) ? $targets : []);
            flash('success', 'Saved. The apply page now lists the open positions that still have slots.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('admin.open_positions'));
    }
}
