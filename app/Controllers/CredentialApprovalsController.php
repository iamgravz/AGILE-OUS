<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\CredentialService;
use DomainException;

/** BR-19: the MSW Committee Head approves or returns new digital IDs and certificates. */
final class CredentialApprovalsController
{
    public function __construct(private readonly CredentialService $credentials = new CredentialService())
    {
    }

    public function index(Request $request): Response
    {
        $type = $request->queryText('type');
        $reason = $request->queryText('reason');
        $filters = [
            'type' => isset(CredentialService::TYPES[$type]) ? $type : '',
            'reason' => isset(CredentialService::REASONS[$reason]) ? $reason : '',
        ];

        return Response::html(View::render('credentials/approvals', [
            'filters' => $filters,
            'totalPending' => $this->credentials->pendingCount(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'IDs and certificates for approval']],
        ] + $this->credentials->forReview(array_filter($filters, static fn (string $value): bool => $value !== '')), 'layouts/dashboard'));
    }

    public function approve(Request $request): Response
    {
        $ids = $request->input('ids', []);

        return $this->act(fn (): int => $this->credentials->approve(is_array($ids) ? array_map('intval', array_filter($ids, 'is_string')) : [], (int) current_user_id()));
    }

    public function approveAll(Request $request): Response
    {
        return $this->act(fn (): int => $this->credentials->approveAllPending((int) current_user_id()));
    }

    public function returnForCorrection(Request $request, string $id): Response
    {
        try {
            $this->credentials->returnForCorrection((int) $id, $request->text('remarks'), (int) current_user_id());
            flash('success', 'It was returned. Fix the cause, then choose Regenerate.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('credentials.approvals'));
    }

    public function regenerate(Request $request, string $id): Response
    {
        try {
            $this->credentials->regenerate((int) $id, (int) current_user_id());
            flash('success', 'A new file was generated and is waiting for approval.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('credentials.approvals'));
    }

    private function act(callable $approve): Response
    {
        try {
            $count = $approve();
            flash('success', $count === 1 ? '1 credential was approved.' : "{$count} credentials were approved.");
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('credentials.approvals'));
    }
}
