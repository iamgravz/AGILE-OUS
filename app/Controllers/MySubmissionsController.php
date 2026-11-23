<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\MembershipApplicationRepository;
use App\Services\CandidacyService;
use App\Services\CredentialRequestService;
use App\Services\WelfareService;

/**
 * My Submissions (Nov 23, 2026; was a placeholder): everything the signed-in person sent, in one place, newest first,
 * with its status and a link to its own page. Only their own records.
 */
final class MySubmissionsController
{
    public function index(Request $request): Response
    {
        $userId = (int) current_user_id();

        return Response::html(View::render('account/submissions', [
            // Drafts too: they are what the person still has to finish.
            'applications' => (new MembershipApplicationRepository())->forUser($userId),
            'cases' => (new WelfareService())->myCases(1)['data'],
            'candidacies' => (new CandidacyService())->mine(),
            'requests' => (new CredentialRequestService())->mine(),
            'events' => (new \App\Services\EventRegistrationService())->mine(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Submissions']],
        ], 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
