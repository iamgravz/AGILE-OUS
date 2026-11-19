<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\WelfareService;
use DateTimeImmutable;
use DomainException;

/**
 * The confidential case workspace. Rules and permission checks are in WelfareService. Pages are never cached, and
 * what a handler typed is not copied into the session when a form fails.
 */
final class WelfareCasesController
{
    public function __construct(private readonly WelfareService $welfare = new WelfareService())
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->queryText('status');
        $category = $request->queryText('category');
        $priority = $request->queryText('priority');
        $assigned = $request->queryText('assigned');
        $validDate = static fn (string $date): bool => DateTimeImmutable::createFromFormat('!Y-m-d', $date) !== false;
        $form = [
            'status' => in_array($status, WelfareService::STATUSES, true) ? $status : '',
            'category' => ctype_digit($category) ? $category : '',
            'priority' => in_array($priority, WelfareService::PRIORITIES, true) ? $priority : '',
            'assigned' => in_array($assigned, ['me', 'unassigned'], true) ? $assigned : '',
            'from' => $validDate($request->queryText('from')) ? $request->queryText('from') : '',
            'to' => $validDate($request->queryText('to')) ? $request->queryText('to') : '',
            'q' => $request->queryText('q'),
        ];
        $filters = array_filter(['status' => $form['status'], 'priority' => $form['priority'], 'assigned' => $form['assigned'], 'from' => $form['from'], 'to' => $form['to'], 'q' => $form['q']], static fn (string $value): bool => $value !== '');

        if ($form['category'] !== '') {
            $filters['category_id'] = (int) $form['category'];
        }

        return $this->page('welfare/cases/index', [
            'result' => $this->welfare->queue($filters, max(1, (int) $request->queryText('page'))),
            'filters' => $form,
            'categories' => $this->welfare->categories(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Welfare Queue']],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $details = ctype_digit($id) ? $this->welfare->openCase((int) $id) : null;

        if ($details === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->page('welfare/cases/show', $details + [
            'activeTab' => $request->queryText('tab') ?: 'overview',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Welfare Queue', 'url' => route('welfare.queue')],
                ['label' => $details['case']['case_code']],
            ],
        ]);
    }

    public function acknowledge(Request $request, string $id): Response
    {
        return $this->act($id, 'overview', 'The case was acknowledged.', fn () => $this->welfare->acknowledge((int) $id));
    }

    public function classify(Request $request, string $id): Response
    {
        $remarks = $request->text('classify_remarks');

        return $this->act($id, 'overview', 'The classification was changed.', fn () => $this->welfare->classify((int) $id, (int) $request->text('category_id'), $request->text('priority'), $remarks), ['classify_remarks' => [$remarks, 'Remarks', 2000]]);
    }

    public function assign(Request $request, string $id): Response
    {
        return $this->act($id, 'overview', 'The handler was changed.', fn () => $this->welfare->assign((int) $id, (int) $request->text('handler_id')));
    }

    public function addNote(Request $request, string $id): Response
    {
        $note = $request->text('note');
        $visible = $request->text('visible_to_student') !== '';

        return $this->act($id, 'notes', $visible ? 'The note was added, and the submitter was told about the update.' : 'The note was added.', fn () => $this->welfare->addNote((int) $id, $note, $visible), ['note' => [$note, 'Note', 5000]]);
    }

    public function addReferral(Request $request, string $id): Response
    {
        $to = $request->text('referred_to');
        $remarks = $request->text('referral_remarks');

        return $this->act($id, 'referrals', 'The referral was added.', fn () => $this->welfare->addReferral((int) $id, $to, $remarks), [
            'referred_to' => [$to, 'Referred to', 150],
            'referral_remarks' => [$remarks, 'Remarks', 2000],
        ]);
    }

    public function updateReferral(Request $request, string $id, string $referralId): Response
    {
        return $this->act($id, 'referrals', 'The referral was updated.', fn () => $this->welfare->updateReferral((int) $id, (int) $referralId, $request->text('status')));
    }

    public function addFollowup(Request $request, string $id): Response
    {
        $due = $request->text('due_date');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $due);

        if ($date === false || $date->format('Y-m-d') !== $due) {
            flash('_errors', ['due_date' => 'Enter a valid due date.']);

            return redirect($this->caseUrl($id, 'followups'));
        }

        return $this->act($id, 'followups', 'The follow-up was added.', fn () => $this->welfare->addFollowup((int) $id, $due, (int) $request->text('handler_id')));
    }

    public function completeFollowup(Request $request, string $id, string $followupId): Response
    {
        $remarks = $request->text('followup_remarks');

        return $this->act($id, 'followups', 'The follow-up was completed.', fn () => $this->welfare->completeFollowup((int) $id, (int) $followupId, $remarks), ['followup_remarks' => [$remarks, 'Remarks', 2000]]);
    }

    public function resolve(Request $request, string $id): Response
    {
        $actions = $request->text('actions_taken');
        $summary = $request->text('summary');

        return $this->act($id, 'closure', 'The case is now Resolved.', fn () => $this->welfare->resolve((int) $id, $actions, $request->text('outcome'), $summary), [
            'actions_taken' => [$actions, 'Actions taken', 5000],
            'summary' => [$summary, 'Summary', 5000],
        ]);
    }

    public function close(Request $request, string $id): Response
    {
        $remarks = $request->text('closing_remarks');

        return $this->act($id, 'closure', 'The case is closed, and the submitter was notified.', fn () => $this->welfare->close((int) $id, $remarks), ['closing_remarks' => [$remarks, 'Closing remarks', 2000]]);
    }

    public function setDisposition(Request $request, string $id, string $attachmentId): Response
    {
        return $this->act($id, 'closure', 'The disposition was saved.', fn () => $this->welfare->setDisposition((int) $id, (int) $attachmentId, $request->text('disposition')));
    }

    /** @param array<string, array{string, string, int}> $required field => [value, label, max length] */
    private function act(string $id, string $tab, string $message, callable $action, array $required = []): Response
    {
        $errors = [];

        foreach ($required as $field => [$value, $label, $max]) {
            $errors += Validator::check([$field => $value], [$field => ['label' => $label, 'rules' => ['required', "max:{$max}"]]]);
        }

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect($this->caseUrl($id, $tab));
        }

        try {
            $action();
            flash('success', $message);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($this->caseUrl($id, $tab));
    }

    private function caseUrl(string $id, string $tab): string
    {
        return route('welfare.show', ['id' => (int) $id]) . "?tab={$tab}";
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
