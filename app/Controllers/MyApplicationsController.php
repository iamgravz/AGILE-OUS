<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\MembershipService;
use DomainException;

final class MyApplicationsController
{
    public function __construct(private readonly MembershipService $membership = new MembershipService())
    {
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('membership/index', [
            'applications' => $this->membership->myApplications(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Applications']],
        ], 'layouts/dashboard'));
    }

    public function show(Request $request, string $id): Response
    {
        $application = ctype_digit($id) ? $this->membership->findOwn((int) $id) : null;

        if ($application === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return Response::html(View::render('membership/show', [
            'application' => $application,
            'timeline' => $this->membership->timeline((int) $application['application_id']),
            'user' => current_user(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'My Applications', 'url' => route('my_applications')],
                ['label' => $application['status'] === 'draft' ? 'Draft' : $application['tracking_code']],
            ],
        ] + $this->membership->details($application), 'layouts/dashboard'));
    }

    public function withdraw(Request $request, string $id): Response
    {
        try {
            $this->membership->withdraw((int) $id);
            flash('success', 'Your application was withdrawn.');
        } catch (DomainException) {
            flash('error', 'Only an application that is still Submitted can be withdrawn.');
        }

        return redirect(route('my_applications.show', ['id' => (int) $id]));
    }
}
