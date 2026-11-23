<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\AuthService;
use App\Services\RoleMatrixService;
use App\Services\UserAdminService;
use DomainException;

/** Users, and the role and permission matrix. Rules and permission checks are in the services. */
final class AdminUsersController
{
    public function __construct(private readonly UserAdminService $users = new UserAdminService())
    {
    }

    public function index(Request $request): Response
    {
        $form = ['q' => $request->queryText('q'), 'role' => $request->queryText('role'), 'status' => $request->queryText('status')];

        return $this->page('admin/users/index', [
            'result' => $this->users->list(array_filter($form, static fn (string $value): bool => $value !== ''), max(1, (int) $request->queryText('page'))),
            'filters' => $form,
            'roles' => $this->users->roles(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Users']],
        ]);
    }

    public function create(Request $request): Response
    {
        authorize('admin.users');

        return $this->page('admin/users/create', [
            'roles' => $this->users->roles(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Users', 'url' => route('admin.users')], ['label' => 'Create account']],
        ]);
    }

    public function store(Request $request): Response
    {
        $data = ['first_name' => $request->text('first_name'), 'last_name' => $request->text('last_name'), 'email' => $request->text('email'), 'student_no' => $request->text('student_no'), 'role' => $request->text('role')];
        $errors = Validator::check($data, [
            'first_name' => ['label' => 'First name', 'rules' => ['required', 'max:80']],
            'last_name' => ['label' => 'Last name', 'rules' => ['required', 'max:80']],
            'email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:150']],
            'student_no' => ['label' => 'PUP student number', 'rules' => ['max:20']],
            'role' => ['label' => 'Role', 'rules' => ['required']],
        ]);

        if ($errors !== []) {
            return $this->back(route('admin.users.create'), $errors, $data);
        }

        try {
            $id = (new AuthService())->createAccount($data['email'], $data['first_name'], $data['last_name'], $data['role'], $data['student_no'] === '' ? null : $data['student_no']);
        } catch (DomainException $exception) {
            return $this->back(route('admin.users.create'), ['email' => $exception->getMessage()], $data);
        }

        flash('success', 'The account was created, and the link to set a password was emailed.');

        return redirect(route('admin.users.show', ['id' => $id]));
    }

    public function show(Request $request, string $id): Response
    {
        try {
            $detail = $this->users->detail((int) $id);
        } catch (DomainException) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $name = $detail['user']['first_name'] . ' ' . $detail['user']['last_name'];

        return $this->page('admin/users/show', $detail + [
            'roles' => $this->users->roles(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Users', 'url' => route('admin.users')], ['label' => $name]],
        ]);
    }

    public function details(Request $request, string $id): Response
    {
        $data = ['first_name' => $request->text('first_name'), 'last_name' => $request->text('last_name'), 'email' => $request->text('email'), 'student_no' => strtoupper($request->text('student_no'))]
            + \App\Services\StudentDetails::fromRequest($request);
        $errors = Validator::check($data, [
            'first_name' => ['label' => 'First name', 'rules' => ['required', 'max:80']],
            'last_name' => ['label' => 'Last name', 'rules' => ['required', 'max:80']],
            'email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:150']],
            'student_no' => ['label' => 'PUP student number', 'rules' => ['max:20']],
        ]) + \App\Services\StudentDetails::errors($data);
        $back = route('admin.users.show', ['id' => (int) $id]) . '#user-edit';

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', $data);

            return redirect($back);
        }

        try {
            $this->users->updateDetails((int) $id, $data, $request->text('details_reason'));
            flash('success', 'The details were saved.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            flash('_old_input', $data);
        }

        return redirect($back);
    }

    public function role(Request $request, string $id): Response
    {
        return $this->act((int) $id, fn () => $this->users->changeRole((int) $id, $request->text('role'), $request->text('reason')), 'The role was changed.');
    }

    public function status(Request $request, string $id): Response
    {
        $enable = $request->text('action') === 'enable';

        return $this->act((int) $id, fn () => $this->users->setEnabled((int) $id, $enable, $request->text('reason')), $enable ? 'The account was enabled.' : 'The account was disabled.');
    }

    public function unlock(Request $request, string $id): Response
    {
        return $this->act((int) $id, fn () => $this->users->unlock((int) $id), 'The account was unlocked.');
    }

    public function resetPassword(Request $request, string $id): Response
    {
        return $this->act((int) $id, fn () => $this->users->forcePasswordReset((int) $id), 'The old password no longer works, and a link to set a new one was emailed.');
    }

    public function permissions(Request $request, string $id): Response
    {
        $codes = $request->input('permissions', []);

        return $this->act((int) $id, fn () => $this->users->setExtraPermissions((int) $id, is_array($codes) ? array_values(array_filter($codes, 'is_string')) : []), 'The extra permissions were saved.');
    }

    public function roles(Request $request): Response
    {
        return $this->page('admin/roles', (new RoleMatrixService())->matrix() + [
            'locked' => RoleMatrixService::LOCKED_FOR_MSW_HEAD,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Roles and permissions']],
        ]);
    }

    public function saveRoles(Request $request): Response
    {
        $submitted = $request->input('granted', []);
        $changed = (new RoleMatrixService())->save(is_array($submitted) ? $submitted : []);
        flash('success', $changed === 0 ? 'Nothing changed.' : 'The permissions were saved.');

        return redirect(route('admin.roles'));
    }

    private function act(int $id, callable $action, string $done): Response
    {
        try {
            $action();
            flash('success', $done);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('admin.users.show', ['id' => $id]));
    }

    /** @param array<string, string> $errors @param array<string, mixed> $data */
    private function back(string $to, array $errors, array $data): Response
    {
        flash('_errors', $errors);
        flash('_old_input', $data);

        return redirect($to);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'));
    }
}
