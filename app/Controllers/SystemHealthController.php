<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\SystemHealthService;

/** The System health page. Rules and permission checks are in SystemHealthService. */
final class SystemHealthController
{
    public function __construct(private readonly SystemHealthService $health = new SystemHealthService())
    {
    }

    public function show(Request $request): Response
    {
        return Response::html(View::render('admin/system_health', $this->health->overview() + [
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'System health']],
        ], 'layouts/dashboard'));
    }

    public function retryFailed(Request $request): Response
    {
        $count = $this->health->retryFailed();
        flash('success', $count === 0 ? 'No failed emails could be retried.' : ($count === 1 ? '1 email is back in the queue.' : "{$count} emails are back in the queue."));

        return redirect(route('admin.system_health'));
    }
}
