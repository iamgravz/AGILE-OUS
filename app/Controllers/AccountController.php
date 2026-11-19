<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\FileRejectedException;
use App\Services\MemberRecordsService;
use App\Services\ProfileService;

final class AccountController
{
    public function __construct(private readonly ProfileService $profile = new ProfileService())
    {
    }

    public function show(Request $request): Response
    {
        return Response::html(View::render('account/index', [
            'account' => current_user(),
            'membership' => (new MemberRecordsService())->ownProfile(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Account']],
        ], 'layouts/dashboard'));
    }

    public function updateContact(Request $request): Response
    {
        $contactNo = $request->text('contact_no');
        $errors = Validator::check(['contact_no' => $contactNo], [
            'contact_no' => [
                'label' => 'Contact number',
                'rules' => ['required', 'regex:' . Validator::PHONE_PATTERN],
                'message' => 'Enter a valid contact number, such as 09171234567.',
            ],
        ]);

        if ($errors !== []) {
            return $this->backWithErrors($errors, ['contact_no' => $contactNo]);
        }

        $this->profile->updateContact($contactNo);
        flash('success', 'Your contact number has been updated.');

        return redirect(route('account'));
    }

    public function updatePhoto(Request $request): Response
    {
        $file = $request->files('photo');

        try {
            $this->profile->updatePhoto(is_array($file) ? $file : []);
        } catch (FileRejectedException $exception) {
            return $this->backWithErrors(['photo' => $exception->getMessage()]);
        }

        flash('success', 'Your profile photo has been updated.');

        return redirect(route('account'));
    }

    public function changePassword(Request $request): Response
    {
        $data = [
            'current_password' => $request->text('current_password', false),
            'new_password' => $request->text('new_password', false),
            'new_password_confirmation' => $request->text('new_password_confirmation', false),
        ];

        $errors = Validator::check($data, [
            'current_password' => ['label' => 'Current password', 'rules' => ['required']],
            'new_password' => ['label' => 'New password', 'rules' => ['required', 'password']],
            'new_password_confirmation' => ['label' => 'Password confirmation', 'rules' => ['required', 'same:new_password']],
        ]);

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        if (!$this->profile->changePassword($data['current_password'], $data['new_password'])) {
            return $this->backWithErrors(['current_password' => 'Your current password is incorrect, or there were too many attempts. Please try again later.']);
        }

        flash('success', 'Your password has been changed.');

        return redirect(route('account'));
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old Never include passwords.
     */
    private function backWithErrors(array $errors, array $old = []): Response
    {
        flash('_errors', $errors);
        flash('_old_input', $old);

        return redirect(route('account'));
    }
}
