<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\UserRepository;
use App\Services\FileRejectedException;
use App\Services\FileStorageService;
use App\Services\GuestVerificationService;
use App\Services\PortalService;
use App\Services\WelfareService;
use DomainException;

/**
 * A guest's concern, in three steps (BR-17): details, emailed code, form. The session holds the guest's name and
 * email, never the concern. A failed form is shown again from this request, like WelfareController.
 */
final class GuestWelfareController
{
    private const SESSION_KEY = 'welfare_guest';
    private const RELATED_KEY = 'welfare_guest_related';
    private const RESULT_KEY = 'welfare_guest_result';
    private const VERIFIED_MINUTES = 30;
    private const TOO_MANY = 'Too many tries. Please wait 15 minutes, then try again.';

    public function __construct(
        private readonly WelfareService $welfare = new WelfareService(),
        private readonly GuestVerificationService $verification = new GuestVerificationService(),
    ) {
    }

    public function show(Request $request): Response
    {
        $blocked = $this->unavailable();

        if ($blocked !== null) {
            return $blocked;
        }

        $related = strtoupper($request->queryText('related'));

        if (preg_match('/^[A-Z0-9]{12}$/', $related) === 1) {
            Session::set(self::RELATED_KEY, $related);
        }

        $guest = Session::get(self::SESSION_KEY);

        if ($guest === null) {
            return $this->page('welfare/guest/start', []);
        }

        if (!$this->isVerified($guest)) {
            return $this->page('welfare/guest/verify', ['email' => $guest['email']]);
        }

        return $this->concernForm($guest, ['related_code' => (string) Session::get(self::RELATED_KEY, '')], []);
    }

    public function requestCode(Request $request): Response
    {
        $blocked = $this->unavailable();

        if ($blocked !== null) {
            return $blocked;
        }

        $data = [
            'name' => $request->text('name'),
            'email' => strtolower($request->text('email')),
            'student_no' => strtoupper($request->text('student_no')),
            'privacy_consent' => $request->text('privacy_consent'),
        ];
        $errors = Validator::check($data, [
            'name' => ['label' => 'Full name', 'rules' => ['required', 'max:160']],
            'email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:150']],
            'student_no' => [
                'label' => 'PUP student number',
                'rules' => ['regex:/^\d{4}-\d{5}-[A-Z]{2}-\d$/'],
                'message' => 'Enter your student number in this format: 2024-00101-OU-0.',
            ],
            'privacy_consent' => ['label' => 'Your consent', 'rules' => ['accepted']],
        ]);

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', array_diff_key($data, ['privacy_consent' => 0]));

            return redirect(route('welfare.guest'));
        }

        // A bot that filled the hidden field sees the normal next page, but no email is sent.
        if ($request->text('website') === '' && !$this->verification->requestCode($data['email'], $request->ip())) {
            flash('error', self::TOO_MANY);
            flash('_old_input', array_diff_key($data, ['privacy_consent' => 0]));

            return redirect(route('welfare.guest'));
        }

        Session::set(self::SESSION_KEY, ['name' => $data['name'], 'email' => $data['email'], 'student_no' => $data['student_no'], 'verified_at' => null]);
        flash('success', 'We sent a 6-digit code to your email.');

        return redirect(route('welfare.guest'));
    }

    public function resend(Request $request): Response
    {
        $guest = Session::get(self::SESSION_KEY);

        if ($guest !== null) {
            flash(...($this->verification->requestCode($guest['email'], $request->ip()) ? ['success', 'We sent a new code to your email. The earlier one no longer works.'] : ['error', self::TOO_MANY]));
        }

        return redirect(route('welfare.guest'));
    }

    public function verify(Request $request): Response
    {
        $guest = Session::get(self::SESSION_KEY);

        if ($guest === null) {
            return redirect(route('welfare.guest'));
        }

        $code = $request->text('code');
        $result = preg_match('/^\d{6}$/', $code) === 1 || $code === '' ? $this->verification->verify($guest['email'], $code) : GuestVerificationService::RESULT_WRONG;

        if ($result === GuestVerificationService::RESULT_OK) {
            Session::regenerate();
            $guest['verified_at'] = time();
            Session::set(self::SESSION_KEY, $guest);
            flash('success', 'Your email is confirmed. You have 30 minutes to submit your concern.');

            return redirect(route('welfare.guest'));
        }

        flash('_errors', ['code' => match ($result) {
            GuestVerificationService::RESULT_EXPIRED => 'This code has expired. Please ask for a new one.',
            GuestVerificationService::RESULT_LOCKED => 'Too many wrong tries. Please ask for a new code.',
            default => 'That code is not correct. Please check it and try again.',
        }]);

        return redirect(route('welfare.guest'));
    }

    public function restart(Request $request): Response
    {
        Session::set(self::SESSION_KEY, null);

        return redirect(route('welfare.guest'));
    }

    public function submit(Request $request): Response
    {
        $blocked = $this->unavailable();

        if ($blocked !== null) {
            return $blocked;
        }

        $guest = Session::get(self::SESSION_KEY);

        if ($guest === null || !$this->isVerified($guest)) {
            Session::set(self::SESSION_KEY, null);
            flash('warning', 'Please confirm your email again before submitting.');

            return redirect(route('welfare.guest'));
        }

        $input = [
            'category_id' => $request->text('category_id'),
            'subject' => $request->text('subject'),
            'description' => $request->text('description'),
            'urgent' => $request->text('urgent') !== '',
        ];
        $relatedCode = $request->text('related_code');
        $errors = $this->welfare->concernErrors($input);

        if ($errors === []) {
            if ($this->verification->submissionBlocked($guest['email'], $request->ip())) {
                return $this->concernForm($guest, $input + ['related_code' => $relatedCode], ['form' => self::TOO_MANY], Response::HTTP_TOO_MANY_REQUESTS);
            }

            try {
                $case = $this->welfare->submitAsGuest($guest, $input, $relatedCode, WelfareService::uploadsFrom($request->files('attachments')));
                Session::set(self::RELATED_KEY, null);
                Session::set(self::RESULT_KEY, ['code' => $case['case_code'], 'email' => $guest['email']]);

                return redirect(route('welfare.guest.submitted'));
            } catch (FileRejectedException $exception) {
                $errors['attachments'] = $exception->getMessage();
            } catch (DomainException $exception) {
                $errors['related'] = $exception->getMessage();
            }
        }

        return $this->concernForm($guest, $input + ['related_code' => $relatedCode], $errors);
    }

    public function submitted(Request $request): Response
    {
        $result = Session::get(self::RESULT_KEY);

        if ($result === null) {
            return redirect(route('welfare.guest'));
        }

        return $this->page('welfare/guest/submitted', $result);
    }

    /**
     * @param array<string, mixed> $guest
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private function concernForm(array $guest, array $input, array $errors, ?int $status = null): Response
    {
        return $this->page('welfare/guest/form', [
            'input' => $input,
            'errors' => $errors,
            'email' => $guest['email'],
            'categories' => $this->welfare->categories(),
            'maxMb' => (new FileStorageService())->maxUploadMb(),
            // Only now, after the code proved the email is theirs, so nobody can use this to test whose email has an account.
            'hasAccount' => (new UserRepository())->findByEmail($guest['email']) !== null,
        ], $status ?? ($errors === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE));
    }

    /** @param array<string, mixed> $guest */
    private function isVerified(array $guest): bool
    {
        return $guest['verified_at'] !== null && time() - (int) $guest['verified_at'] < self::VERIFIED_MINUTES * 60;
    }

    private function unavailable(): ?Response
    {
        if (current_user_id() !== null) {
            return redirect(route('welfare.new'));
        }

        return (new PortalService())->guestWelfareEnabled() ? null : redirect(route('ask_for_help'));
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data, int $status = Response::HTTP_OK): Response
    {
        return Response::html(View::render($view, $data, 'layouts/public'), $status, ['Cache-Control' => 'no-store']);
    }
}
