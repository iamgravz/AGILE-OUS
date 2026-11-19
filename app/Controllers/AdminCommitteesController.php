<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Models\CommitteeRepository;
use App\Services\CommitteeAdminService;
use DomainException;

/** Committees and positions. Rules and permission checks are in CommitteeAdminService. */
final class AdminCommitteesController
{
    public function __construct(private readonly CommitteeAdminService $service = new CommitteeAdminService())
    {
    }

    public function index(Request $request): Response
    {
        return $this->page('admin/committees/index', $this->service->overview() + [
            'options' => (new CommitteeRepository())->options(),
            'roleTypes' => CommitteeAdminService::ROLE_TYPES,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Committees and positions']],
        ]);
    }

    public function store(Request $request): Response
    {
        $name = $request->text('name');

        if ($this->nameError($name, 'name', ['name' => $name, 'description' => $request->text('description')], route('admin.committees')) !== null) {
            return redirect(route('admin.committees'));
        }

        return $this->act(fn () => $this->service->createCommittee($name, $request->text('description')), 'The committee was added.', route('admin.committees'));
    }

    public function show(Request $request, string $id): Response
    {
        try {
            $committee = $this->service->committee((int) $id);
        } catch (DomainException) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->page('admin/committees/show', [
            'committee' => $committee,
            'candidates' => $this->service->headCandidates(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Committees and positions', 'url' => route('admin.committees')], ['label' => $committee['name']]],
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $to = route('admin.committees.show', ['id' => (int) $id]);
        $name = $request->text('name');

        if ($this->nameError($name, 'name', ['name' => $name, 'description' => $request->text('description')], $to) !== null) {
            return redirect($to);
        }

        return $this->act(fn () => $this->service->updateCommittee((int) $id, $name, $request->text('description')), 'The committee was saved.', $to);
    }

    public function delete(Request $request, string $id): Response
    {
        return $this->act(fn () => $this->service->deleteCommittee((int) $id), 'The committee was deleted.', route('admin.committees'));
    }

    public function head(Request $request, string $id): Response
    {
        $to = route('admin.committees.show', ['id' => (int) $id]);

        return $this->act(fn () => $this->service->setHead((int) $id, (int) $request->text('user_id'), $request->text('reason')), 'The committee head was set. New ID and certificate are waiting for approval.', $to);
    }

    public function storePosition(Request $request): Response
    {
        $title = $request->text('title');
        $committee = $request->text('committee_id');

        if ($this->nameError($title, 'title', ['title' => $title, 'committee_id' => $committee, 'role_type' => $request->text('role_type')], route('admin.committees')) !== null) {
            return redirect(route('admin.committees'));
        }

        return $this->act(fn () => $this->service->createPosition($committee === '' ? null : (int) $committee, $title, $request->text('role_type')), 'The position was added.', route('admin.committees'));
    }

    public function showPosition(Request $request, string $id): Response
    {
        try {
            $position = $this->service->position((int) $id);
        } catch (DomainException) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->page('admin/committees/position', [
            'position' => $position,
            'roleTypes' => CommitteeAdminService::ROLE_TYPES,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Committees and positions', 'url' => route('admin.committees')], ['label' => $position['title']]],
        ]);
    }

    public function updatePosition(Request $request, string $id): Response
    {
        $to = route('admin.positions.show', ['id' => (int) $id]);
        $title = $request->text('title');

        if ($this->nameError($title, 'title', ['title' => $title, 'role_type' => $request->text('role_type')], $to) !== null) {
            return redirect($to);
        }

        return $this->act(fn () => $this->service->updatePosition((int) $id, $title, $request->text('role_type')), 'The position was saved.', $to);
    }

    public function deletePosition(Request $request, string $id): Response
    {
        return $this->act(fn () => $this->service->deletePosition((int) $id), 'The position was deleted.', route('admin.committees'));
    }

    /** @param array<string, mixed> $data */
    private function nameError(string $value, string $field, array $data, string $to): ?string
    {
        $errors = Validator::check([$field => $value], [$field => ['label' => $field === 'name' ? 'Name' : 'Title', 'rules' => ['required', 'max:100']]]);

        if ($errors === []) {
            return null;
        }

        flash('_errors', $errors);
        flash('_old_input', $data);

        return $errors[$field];
    }

    private function act(callable $action, string $done, string $to): Response
    {
        try {
            $action();
            flash('success', $done);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($to);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'));
    }
}
