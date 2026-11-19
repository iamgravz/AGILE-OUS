<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\AccountApprovalService;
use DomainException;

final class AccountApprovalsController
{
    public function __construct(private readonly AccountApprovalService $approvals = new AccountApprovalService())
    {
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('accounts/approvals', [
            'accounts' => $this->approvals->pending(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'New accounts for approval']],
        ], 'layouts/dashboard'));
    }

    public function approve(Request $request, string $id): Response
    {
        try {
            $this->approvals->approve((int) $id);
            flash('success', 'The account was approved, and the member was emailed.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('accounts.approvals'));
    }

    public function approveSelected(Request $request): Response
    {
        $ids = $request->input('ids', []);

        try {
            $count = $this->approvals->approveSelected(is_array($ids) ? array_map('intval', array_filter($ids, 'is_string')) : []);
            flash('success', $count === 1 ? '1 account was approved.' : "{$count} accounts were approved.");
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('accounts.approvals'));
    }

    public function reject(Request $request, string $id): Response
    {
        try {
            $this->approvals->reject((int) $id, $request->text('reason'));
            flash('success', 'The account was rejected, and the person was emailed the reason. They may register again.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('accounts.approvals'));
    }
}
