<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\AuthService;
use App\Services\MemberRegistrationService;
use DomainException;

final class AuthController
{
    private const GENERIC_LOGIN_ERROR = 'We could not log you in. Check your email and password and try again. After 5 failed attempts, an account is locked for 15 minutes.';
    private const CHECK_EMAIL = 'If that email can receive this message, we have sent you a link. It expires in 60 minutes.';

    private const REGISTER_KEY = 'register_pending';
    private const REGISTER_MINUTES = 30;
    private const WAITING_FOR_APPROVAL = "Your account is waiting for approval by the MSW Committee Head. We'll email you once it's approved.";
    private const TOO_MANY = 'Too many tries. Please wait 15 minutes, then try again.';

    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly MemberRegistrationService $registration = new MemberRegistrationService(),
    ) {
    }

    public function showRegister(Request $request): Response
    {
        $state = Session::get(self::REGISTER_KEY);

        if ($state === null) {
            return $this->robotPage('auth/register');
        }

        if ($state['member_id'] === null) {
            return $this->robotPage('auth/register_verify');
        }

        if ($state['verified_at'] + self::REGISTER_MINUTES * 60 < time()) {
            Session::set(self::REGISTER_KEY, null);
            flash('warning', 'That took a while, so please start again.');

            return redirect(route('register'));
        }

        return $this->robotPage('auth/register_password');
    }

    /** One reply for every student number, so this page cannot be used to test them. */
    public function requestRegisterCode(Request $request): Response
    {
        $studentNo = strtoupper($request->text('student_no'));
        $errors = Validator::check(['student_no' => $studentNo], [
            'student_no' => [
                'label' => 'PUP student number',
                'rules' => ['required', 'regex:' . Validator::STUDENT_NO_PATTERN],
                'message' => 'Enter your student number in this format: 2024-xxxxx-MN-0.',
            ],
        ]);

        if ($errors !== []) {
            return $this->backWithErrors(route('register'), $errors, ['student_no' => $studentNo]);
        }

        // A bot that filled the hidden field sees the normal next page, but no email is sent.
        if ($request->text('website') === '' && !$this->registration->requestCode($studentNo, $request->ip())) {
            flash('error', self::TOO_MANY);
            flash('_old_input', ['student_no' => $studentNo]);

            return redirect(route('register'));
        }

        Session::set(self::REGISTER_KEY, ['student_no' => $studentNo, 'member_id' => null, 'verified_at' => null]);

        return redirect(route('register'));
    }

    public function resendRegister(Request $request): Response
    {
        $state = Session::get(self::REGISTER_KEY);

        if ($state !== null) {
            flash(...($this->registration->requestCode($state['student_no'], $request->ip()) ? ['success', 'If this student number has an approved AGILE membership, we sent a new code. The earlier one no longer works.'] : ['error', self::TOO_MANY]));
        }

        return redirect(route('register'));
    }

    public function verifyRegister(Request $request): Response
    {
        $state = Session::get(self::REGISTER_KEY);

        if ($state === null) {
            return redirect(route('register'));
        }

        $code = $request->text('code');
        $memberId = preg_match('/^\d{6}$/', $code) === 1 ? $this->registration->confirmCode($state['student_no'], $code) : null;

        if ($memberId === null) {
            flash('_errors', ['code' => 'That code is not correct or has expired. Please check it, or ask for a new one.']);

            return redirect(route('register'));
        }

        Session::regenerate();
        Session::set(self::REGISTER_KEY, ['member_id' => $memberId, 'verified_at' => time()] + $state);

        return redirect(route('register'));
    }

    public function registerPassword(Request $request): Response
    {
        $state = Session::get(self::REGISTER_KEY);

        if (($state['member_id'] ?? null) === null || $state['verified_at'] + self::REGISTER_MINUTES * 60 < time()) {
            Session::set(self::REGISTER_KEY, null);

            return redirect(route('register'));
        }

        $data = [
            'password' => $request->text('password', false),
            'password_confirmation' => $request->text('password_confirmation', false),
            'privacy_consent' => $request->text('privacy_consent'),
        ];
        $errors = Validator::check($data, [
            'password' => ['label' => 'Password', 'rules' => ['required', 'password']],
            'password_confirmation' => ['label' => 'Password confirmation', 'rules' => ['required', 'same:password']],
            'privacy_consent' => ['label' => 'Your consent', 'rules' => ['accepted']],
        ]);

        if ($errors !== []) {
            return $this->backWithErrors(route('register'), $errors, []);
        }

        try {
            $this->auth->registerMember($state['member_id'], $data['password']);
            flash('success', 'Your registration was received. ' . self::WAITING_FOR_APPROVAL);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        Session::set(self::REGISTER_KEY, null);

        return redirect(route('login'));
    }

    public function restartRegister(Request $request): Response
    {
        Session::set(self::REGISTER_KEY, null);

        return redirect(route('register'));
    }

    public function showLogin(Request $request): Response
    {
        // The site's own layout, so login looks like the other public pages. The registration steps keep layouts/auth.
        return Response::html(View::render('auth/login', [], 'layouts/public'));
    }

    public function login(Request $request): Response
    {
        $email = $request->text('email');
        $password = $request->text('password', false);

        $errors = Validator::check(['email' => $email, 'password' => $password], [
            'email' => ['label' => 'Email', 'rules' => ['required']],
            'password' => ['label' => 'Password', 'rules' => ['required']],
        ]);

        if ($errors !== []) {
            return $this->backWithErrors(route('login'), $errors, ['email' => $email]);
        }

        $result = $this->auth->attempt($email, $password, $request->ip());

        if ($result === AuthService::LOGIN_OK) {
            $intended = Session::get('intended_url');
            Session::set('intended_url', null);

            // Only ever a path on this site: it comes from pathWithQuery(), and "//" would leave the site.
            return redirect(is_string($intended) && !str_starts_with($intended, '//') ? $intended : route('dashboard'));
        }

        flash('_old_input', ['email' => $email]);

        flash(...($result === AuthService::LOGIN_PENDING ? ['warning', self::WAITING_FOR_APPROVAL] : ['error', self::GENERIC_LOGIN_ERROR]));

        return redirect(route('login'));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        flash('success', 'You have been logged out.');

        return redirect(route('login'));
    }

    public function showForgot(Request $request): Response
    {
        return $this->robotPage('auth/forgot_password');
    }

    public function forgot(Request $request): Response
    {
        $email = $request->text('email');
        $errors = Validator::check(['email' => $email], ['email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:150']]]);

        if ($errors !== []) {
            return $this->backWithErrors(route('password.forgot'), $errors, ['email' => $email]);
        }

        $this->auth->requestPasswordReset($email, $request->ip());
        flash('success', self::CHECK_EMAIL);

        return redirect(route('login'));
    }

    public function showReset(Request $request): Response
    {
        $token = $request->query('token');

        if (!is_string($token) || !$this->auth->isResetTokenValid($token)) {
            return $this->invalidResetLink();
        }

        return $this->robotPage('auth/reset_password', ['token' => $token]);
    }

    public function reset(Request $request): Response
    {
        $token = $request->text('token');
        $data = [
            'password' => $request->text('password', false),
            'password_confirmation' => $request->text('password_confirmation', false),
        ];

        $errors = Validator::check($data, [
            'password' => ['label' => 'Password', 'rules' => ['required', 'password']],
            'password_confirmation' => ['label' => 'Password confirmation', 'rules' => ['required', 'same:password']],
        ]);

        if ($errors !== []) {
            return $this->backWithErrors(route('password.reset') . '?token=' . rawurlencode($token), $errors, []);
        }

        if (!$this->auth->resetPassword($token, $data['password'])) {
            return $this->invalidResetLink();
        }

        flash('success', 'Your password has been changed. You can now log in.');

        return redirect(route('login'));
    }

    /** The link from the ACCOUNT_SETUP email (BR-18). */
    public function showSetup(Request $request): Response
    {
        $token = $request->query('token');

        if (!is_string($token) || !$this->auth->isSetupTokenValid($token)) {
            return $this->invalidSetupLink();
        }

        return $this->robotPage('auth/set_password', ['token' => $token]);
    }

    public function setup(Request $request): Response
    {
        $token = $request->text('token');
        $data = [
            'password' => $request->text('password', false),
            'password_confirmation' => $request->text('password_confirmation', false),
        ];

        $errors = Validator::check($data, [
            'password' => ['label' => 'Password', 'rules' => ['required', 'password']],
            'password_confirmation' => ['label' => 'Password confirmation', 'rules' => ['required', 'same:password']],
        ]);

        if ($errors !== []) {
            return $this->backWithErrors(route('account.setup') . '?token=' . rawurlencode($token), $errors, []);
        }

        if (!$this->auth->setPassword($token, $data['password'])) {
            return $this->invalidSetupLink();
        }

        flash('success', 'Your password is set. You can now log in.');

        return redirect(route('login'));
    }

    private function invalidSetupLink(): Response
    {
        flash('error', 'This link is invalid, expired or already used. Enter your email below and we will send you a new one.');

        return redirect(route('password.forgot'));
    }

    private function invalidResetLink(): Response
    {
        flash('error', 'This reset link is invalid, expired or already used. You can request a new one below.');

        return redirect(route('password.forgot'));
    }

    /** @param array<string, mixed> $data */
    /** The password pages, in the site layout like the login page, with Iskobot on the card. */
    private function robotPage(string $view, array $data = []): Response
    {
        return Response::html(View::render($view, $data, 'layouts/public'));
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old Never include passwords.
     */
    private function backWithErrors(string $to, array $errors, array $old): Response
    {
        flash('_errors', $errors);
        flash('_old_input', $old);

        return redirect($to);
    }
}
