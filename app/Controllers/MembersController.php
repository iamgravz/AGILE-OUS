<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\MemberRecordsService;
use DomainException;

/** Staff screens for member records. Rules and permission checks are in MemberRecordsService. */
final class MembersController
{
    public function __construct(private readonly MemberRecordsService $records = new MemberRecordsService())
    {
    }

    public function index(Request $request): Response
    {
        $type = $request->queryText('type');
        $committee = $request->queryText('committee');
        $roleStatus = $request->queryText('role_status');
        $form = [
            'q' => $request->queryText('q'),
            'type' => in_array($type, ['general', 'officer'], true) ? $type : '',
            'committee' => ctype_digit($committee) ? $committee : '',
            'role_status' => in_array($roleStatus, ['active', 'expired', 'none'], true) ? $roleStatus : '',
            'registration' => $request->queryText('registration') === 'unregistered' ? 'unregistered' : '',
        ];
        $filters = array_filter(['q' => $form['q'], 'type' => $form['type'], 'role_status' => $form['role_status'], 'registration' => $form['registration']], static fn (string $value): bool => $value !== '');

        if ($form['committee'] !== '') {
            $filters['committee_id'] = (int) $form['committee'];
        }

        return Response::html(View::render('members/index', [
            'result' => $this->records->list($filters, max(1, (int) $request->queryText('page'))),
            'filters' => $form,
            'committees' => $this->records->committeeOptions(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Members']],
        ], 'layouts/dashboard'));
    }

    public function show(Request $request, string $id): Response
    {
        $profile = ctype_digit($id) ? $this->records->open((int) $id) : null;

        if ($profile === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $committees = new \App\Services\MemberCommitteeService();

        return Response::html(View::render('members/show', $profile + [
            'extraCommittees' => $committees->extras((int) $id),
            'committeeOptions' => can('admin.settings') ? $committees->options((int) $id) : [],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => route('members')],
                ['label' => $profile['member']['member_no']],
            ],
        ], 'layouts/dashboard'));
    }

    public function resendRegistration(Request $request, string $id): Response
    {
        try {
            $this->records->resendRegistration((int) $id);
            flash('success', 'The registration instructions were emailed.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('members.show', ['id' => (int) $id]));
    }

    /** P4: the MSW Committee Head adds or removes a member's second standing committee. */
    public function committee(Request $request, string $id): Response
    {
        $service = new \App\Services\MemberCommitteeService();
        $committeeId = (int) $request->text('committee_id');
        $reason = $request->text('reason');

        try {
            if ($request->text('action') === 'remove') {
                $service->remove((int) $id, $committeeId, $reason);
                flash('success', 'The second committee was removed.');
            } else {
                $service->add((int) $id, $committeeId, $reason);
                flash('success', 'The second committee was added.');
            }
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('members.show', ['id' => (int) $id]));
    }

    public function reissue(Request $request, string $id): Response
    {
        $reason = $request->text('reason');
        $errors = Validator::check(['reason' => $reason], ['reason' => ['label' => 'Reason', 'rules' => ['required', 'max:255']]]);
        $back = route('members.show', ['id' => (int) $id]);

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect($back);
        }

        try {
            $this->records->reissueCredentials((int) $id, $reason);
            flash('success', 'New credentials are waiting for approval. Any approved ones stay valid until then.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($back);
    }
}
