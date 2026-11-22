<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\ApplicantAccessService;
use App\Services\ApplicationIncompleteException;
use App\Services\FileRejectedException;
use App\Services\GuestVerificationService;
use App\Services\MembershipService;
use DomainException;

/**
 * The application wizard, for a logged-in member applying for a position and for someone without an account applying
 * for general membership (BR-18). Rules live in MembershipService.
 */
final class ApplyController
{
    private const VACANCY_KEY = 'apply_vacancy';
    private const PENDING_KEY = 'apply_pending';
    /** What they chose on the first page ("general" or "position:<id>"), preselected on step 3. */
    private const CHOICE_KEY = 'apply_choice';
    /** Documents uploaded on the first page, doc_type => stored file, attached when the draft is created. */
    private const DOCUMENTS_KEY = 'apply_documents';
    private const TOO_MANY = 'Too many tries. Please wait 15 minutes, then try again.';
    private const BAD_LINK = 'This link is invalid, expired or already used. If your application was returned for revision, ask for a new link on this page. To continue a draft, start again from Apply and confirm your email.';

    public function __construct(
        private readonly MembershipService $membership = new MembershipService(),
        private readonly GuestVerificationService $verification = new GuestVerificationService(),
        private readonly ApplicantAccessService $access = new ApplicantAccessService(),
    ) {
    }

    /** Resumes the draft at its last step, or starts a new one. ?vacancy= preselects step 3. */
    public function start(Request $request): Response
    {
        $vacancy = $request->queryText('vacancy');

        if (current_user_id() === null && ApplicantAccessService::current() === null) {
            $pending = Session::get(self::PENDING_KEY);

            return $pending === null
                ? $this->publicPage('membership/apply/start', ['forPosition' => ctype_digit($vacancy), 'positions' => $this->membership->openPositions()])
                : $this->publicPage('membership/apply/verify', ['email' => $pending['email']]);
        }

        if (ctype_digit($vacancy) && current_user_id() !== null) {
            Session::set(self::VACANCY_KEY, (int) $vacancy);
        }

        $draft = $this->membership->currentDraft();

        return redirect($draft === null ? route('apply.new') : $this->stepUrl((int) $draft['application_id'], (int) $draft['wizard_step']));
    }

    /** Step one for someone without an account: who they are, then a code to their email. */
    public function requestCode(Request $request): Response
    {
        $data = [
            'first_name' => $request->text('first_name'),
            'last_name' => $request->text('last_name'),
            'email' => strtolower($request->text('email')),
            'student_no' => strtoupper($request->text('student_no')),
        ];
        $errors = Validator::check($data + ['privacy_consent' => $request->text('privacy_consent')], [
            'first_name' => ['label' => 'First name', 'rules' => ['required', 'max:80']],
            'last_name' => ['label' => 'Last name', 'rules' => ['required', 'max:80']],
            'email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:150']],
            'student_no' => [
                'label' => 'PUP student number',
                'rules' => ['required', 'regex:' . Validator::STUDENT_NO_PATTERN],
                'message' => 'Enter your student number in this format: 2024-xxxxx-MN-0.',
            ],
            'privacy_consent' => ['label' => 'Your consent', 'rules' => ['accepted']],
        ]);

        // General Membership, or a position the MSW Committee Head opened for application.
        $choice = $request->text('applying_for');
        $openIds = array_column($this->membership->openPositions(), 'position_id');
        $forPosition = preg_match('/^position:(\d+)$/', $choice, $match) === 1 && in_array((int) $match[1], $openIds, true);

        if ($choice !== 'general' && !$forPosition) {
            $errors['applying_for'] = 'Please choose what you are applying for.';
        }

        // The Requirements dropdown is AGILE's table: the row that fits them. A General Membership applicant is a general
        // member or a 1st year; a position applicant is any row but "General members".
        $requirement = $request->text('requirement');
        $group = MembershipService::REQUIREMENT_GROUPS[$requirement] ?? null;
        $allowed = $forPosition ? $requirement !== 'general' : in_array($requirement, ['general', 'first_year_first_sem', 'first_year_second_sem'], true);

        if ($group === null) {
            $errors['requirement'] = 'Please choose the row of the requirements that fits you.';
        } elseif ($choice !== '' && !$allowed) {
            $errors['requirement'] = $forPosition
                ? 'For a position, choose the row for officers, committee members, position applicants or 1st years.'
                : 'For General Membership, choose General members or a 1st year row.';
        }

        // The documents that row asks for come with this page: the COR always, and the grades when the row needs them.
        $uploads = [];
        $storage = new \App\Services\FileStorageService();
        $needsGrades = (bool) ($group[2] ?? false);

        foreach ($needsGrades ? ['cor', 'grades'] : ['cor'] as $type) {
            $file = $request->files($type);
            $chosen = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

            if (!$chosen) {
                $errors[$type] = $type === 'cor' ? 'Please upload your current COR.' : 'Please upload your ' . lcfirst((string) $group[3]) . '.';

                continue;
            }

            try {
                $storage->validate($file);
                $uploads[$type] = $file;
            } catch (\App\Services\FileRejectedException $exception) {
                $errors[$type] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            // A browser cannot refill file fields, so the message says to choose them again.
            if ($uploads !== []) {
                $errors['upload_again'] = 'For your security, files are not kept when something needs fixing. Please choose them again.';
            }

            return $this->backWithErrors(route('apply'), $errors, $data + ['applying_for' => $choice, 'requirement' => $requirement]);
        }

        Session::set(self::CHOICE_KEY, $choice);

        // A bot that filled the hidden field sees the normal next page, but no email is sent.
        if ($request->text('website') === '' && !$this->verification->requestCode($data['email'], $request->ip(), GuestVerificationService::PURPOSE_APPLICATION)) {
            flash('error', self::TOO_MANY);
            flash('_old_input', $data);

            return redirect(route('apply'));
        }

        // Saved now, in the same private folder as every application document, and attached to the application once the
        // email is confirmed and the draft exists (createNew). A bot that filled the hidden field gets nothing saved.
        $documents = [];

        if ($request->text('website') === '') {
            foreach ($uploads as $type => $file) {
                $documents[$type] = $storage->store($file, 'applications');
            }
        }

        Session::set(self::PENDING_KEY, $data);
        Session::set(self::DOCUMENTS_KEY, $documents);
        flash('success', 'We sent a 6-digit code to your email.');

        return redirect(route('apply'));
    }

    public function resend(Request $request): Response
    {
        $pending = Session::get(self::PENDING_KEY);

        if ($pending !== null) {
            flash(...($this->verification->requestCode($pending['email'], $request->ip(), GuestVerificationService::PURPOSE_APPLICATION) ? ['success', 'We sent a new code to your email. The earlier one no longer works.'] : ['error', self::TOO_MANY]));
        }

        return redirect(route('apply'));
    }

    public function verify(Request $request): Response
    {
        $pending = Session::get(self::PENDING_KEY);

        if ($pending === null) {
            return redirect(route('apply'));
        }

        $code = $request->text('code');
        $result = preg_match('/^\d{6}$/', $code) === 1 ? $this->verification->verify($pending['email'], $code, GuestVerificationService::PURPOSE_APPLICATION) : GuestVerificationService::RESULT_WRONG;

        if ($result !== GuestVerificationService::RESULT_OK) {
            flash('_errors', ['code' => match ($result) {
                GuestVerificationService::RESULT_EXPIRED => 'This code has expired. Please ask for a new one.',
                GuestVerificationService::RESULT_LOCKED => 'Too many wrong tries. Please ask for a new code.',
                default => 'That code is not correct. Please check it and try again.',
            }]);

            return redirect(route('apply'));
        }

        Session::set(self::PENDING_KEY, null);

        try {
            $confirmed = $this->access->confirmEmail($pending);
        } catch (DomainException $exception) {
            // BR-02: this student number already belongs to another applicant.
            flash('_errors', ['student_no' => $exception->getMessage()]);

            return redirect(route('apply'));
        }

        if ($confirmed['outcome'] === ApplicantAccessService::OUTCOME_HAS_ACCOUNT) {
            flash('info', 'This email already has an AGILE account. Please log in. If you forgot your password, you can reset it from this page.');

            return redirect(route('login'));
        }

        if ($confirmed['outcome'] === ApplicantAccessService::OUTCOME_ACTIVE) {
            flash('info', 'You already have an active application (' . $confirmed['tracking_code'] . '). You can follow it on this page.');

            return redirect(route('track'));
        }

        return redirect(route('apply'));
    }

    public function restart(Request $request): Response
    {
        Session::set(self::PENDING_KEY, null);
        Session::set(self::CHOICE_KEY, null);
        Session::set(self::DOCUMENTS_KEY, null);

        return redirect(route('apply'));
    }

    /** A page with a button, because mail scanners open links and this one works once. */
    public function showLink(Request $request): Response
    {
        $token = $request->queryText('token');

        if (current_user_id() !== null) {
            flash('warning', 'This link is for someone without an account. Please log out first, then open it again.');

            return redirect(route('dashboard'));
        }

        if ($token === '' || !$this->access->isLinkUsable($token)) {
            flash('error', self::BAD_LINK);

            return redirect(route('track'));
        }

        return $this->publicPage('membership/apply/continue', ['token' => $token]);
    }

    public function openLink(Request $request): Response
    {
        $id = current_user_id() === null ? $this->access->openLink($request->text('token')) : null;

        if ($id === null) {
            flash('error', self::BAD_LINK);

            return redirect(route('track'));
        }

        return redirect($this->stepUrl($id, (int) $this->membership->findOwn($id)['wizard_step']));
    }

    /** A page with a button, for the same reason as showLink(). */
    public function showWithdraw(Request $request): Response
    {
        $token = $request->queryText('token');

        if ($token === '' || !$this->access->isWithdrawalLinkUsable($token)) {
            flash('error', self::BAD_LINK);

            return redirect(route('track'));
        }

        return $this->publicPage('membership/apply/withdraw', ['token' => $token]);
    }

    public function withdraw(Request $request): Response
    {
        if ($this->access->withdrawWithLink($request->text('token'))) {
            flash('success', 'Your application was withdrawn. You can apply again at any time.');
        } else {
            flash('error', self::BAD_LINK);
        }

        return redirect(route('track'));
    }

    public function showNew(Request $request): Response
    {
        $blocked = $this->needsAccess();

        if ($blocked !== null) {
            return $blocked;
        }

        $draft = $this->membership->currentDraft();

        return $draft !== null
            ? redirect($this->stepUrl((int) $draft['application_id'], (int) $draft['wizard_step']))
            : $this->render('membership/apply/step1', ['application' => null, 'step' => 1, 'reached' => 1]);
    }

    public function createNew(Request $request): Response
    {
        $blocked = $this->needsAccess();

        if ($blocked !== null) {
            return $blocked;
        }

        $contactNo = $request->text('contact_no');
        $personal = $this->personalInput($request);
        $errors = $this->contactErrors($contactNo) + $this->personalErrors($personal);

        if ($errors !== []) {
            return $this->backWithErrors(route('apply.new'), $errors, $personal + ['contact_no' => $contactNo]);
        }

        $id = $this->membership->startDraft($contactNo, $personal);

        // The COR (and grades) from the first page join the new application.
        $documents = Session::get(self::DOCUMENTS_KEY);

        if (is_array($documents) && $documents !== []) {
            $this->membership->attachFirstPageDocuments($id, $documents);
            Session::set(self::DOCUMENTS_KEY, null);
        }

        if ($request->text('intent') === 'save') {
            flash('success', $this->savedMessage());

            return redirect($this->stepUrl($id, 1));
        }

        $this->membership->advance($id, 2);

        return redirect($this->stepUrl($id, 2));
    }

    public function show(Request $request, string $id, string $step): Response
    {
        $blocked = $this->needsAccess();

        if ($blocked !== null) {
            return $blocked;
        }

        $application = $this->ownEditable($id);

        if ($application === null) {
            return $this->notEditable();
        }

        $number = (int) $step;

        if ($number < 1 || $number > MembershipService::LAST_STEP) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        // Steps cannot be skipped by editing the address.
        if ($number > (int) $application['wizard_step']) {
            return redirect($this->stepUrl((int) $application['application_id'], (int) $application['wizard_step']));
        }

        return $this->render("membership/apply/step{$number}", [
            'application' => $application,
            'step' => $number,
            'reached' => (int) $application['wizard_step'],
            'vacancies' => $number === 3 ? $this->membership->openVacancies() : [],
            'positions' => $number === 3 ? $this->membership->openPositions() : [],
            'preselected' => $number === 3 ? Session::get(self::VACANCY_KEY) : null,
            'preselectedChoice' => $number === 3 ? Session::get(self::CHOICE_KEY) : null,
        ] + $this->membership->details($application));
    }

    public function save(Request $request, string $id, string $step): Response
    {
        $blocked = $this->needsAccess();

        if ($blocked !== null) {
            return $blocked;
        }

        $application = $this->ownEditable($id);

        if ($application === null) {
            return $this->notEditable();
        }

        $applicationId = (int) $application['application_id'];
        $number = (int) $step;

        // Same rule as show(): a crafted POST cannot skip ahead, for example past choosing a membership type.
        if ($number < 1 || $number > (int) $application['wizard_step']) {
            return redirect($this->stepUrl($applicationId, (int) $application['wizard_step']));
        }

        $here = $this->stepUrl($applicationId, $number);

        try {
            $errors = $this->saveStep($request, $application, $number);
        } catch (FileRejectedException $exception) {
            return $this->backWithErrors($here, ['upload' => $exception->getMessage()]);
        } catch (DomainException $exception) {
            if ($number === 3) {
                return $this->backWithErrors($here, ['membership_type' => $exception->getMessage()]);
            }

            flash('error', $exception->getMessage());

            return redirect($here);
        }

        if ($errors !== []) {
            return $this->backWithErrors($here, $errors, $this->oldInput($request));
        }

        if ($request->text('intent') === 'save') {
            flash('success', $this->savedMessage());

            if ($application['applicant_id'] !== null && $application['status'] === 'draft') {
                $this->access->sendDraftLink($applicationId);
            }

            return redirect($here);
        }

        // Next: the step must be complete before moving on.
        $errors = $this->membership->errorsForStep($this->membership->findOwn($applicationId), $number);

        if ($errors !== []) {
            return $this->backWithErrors($here, $errors, $this->oldInput($request));
        }

        $this->membership->advance($applicationId, $number + 1);

        return redirect($this->stepUrl($applicationId, $number + 1));
    }

    public function uploadDocument(Request $request, string $id, string $type): Response
    {
        $blocked = $this->needsAccess();

        if ($blocked !== null) {
            return $blocked;
        }

        $here = $this->stepUrl((int) $id, 5);
        $file = $request->files('document');

        try {
            $this->membership->uploadDocument((int) $id, $type, is_array($file) ? $file : []);
            flash('success', 'Your document was uploaded.');
        } catch (FileRejectedException $exception) {
            return $this->backWithErrors($here, [$type => $exception->getMessage()]);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($here);
    }

    public function removeDocument(Request $request, string $id, string $documentId): Response
    {
        $blocked = $this->needsAccess();

        if ($blocked !== null) {
            return $blocked;
        }

        try {
            $this->membership->removeDocument((int) $id, (int) $documentId);
            flash('success', 'The document was removed.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($this->stepUrl((int) $id, 5));
    }

    public function submit(Request $request, string $id): Response
    {
        $blocked = $this->needsAccess();

        if ($blocked !== null) {
            return $blocked;
        }

        $applicationId = (int) $id;

        try {
            $this->membership->submit($applicationId, $request->text('privacy_consent') !== '');
        } catch (ApplicationIncompleteException $exception) {
            flash('error', 'Please complete the highlighted items before submitting.');

            return $this->backWithErrors($this->stepUrl($applicationId, $exception->step), $exception->errors);
        } catch (DomainException $exception) {
            return $this->backWithErrors($this->stepUrl($applicationId, 3), ['membership_type' => $exception->getMessage()]);
        }

        Session::set(self::VACANCY_KEY, null);

        return redirect(route('apply.success', ['id' => $applicationId]));
    }

    public function success(Request $request, string $id): Response
    {
        $blocked = $this->needsAccess();

        if ($blocked !== null) {
            return $blocked;
        }

        $application = ctype_digit($id) ? $this->membership->findOwn((int) $id) : null;

        if ($application === null || $application['status'] === 'draft') {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->page('membership/apply/success', ['application' => $application]);
    }

    /**
     * @param array<string, mixed> $application
     * @return array<string, string>
     */
    private function saveStep(Request $request, array $application, int $step): array
    {
        $id = (int) $application['application_id'];
        $answers = $request->input('q', []);
        $answers = is_array($answers) ? $answers : [];

        switch ($step) {
            case 1:
                $contactNo = $request->text('contact_no');
                $personal = $application['applicant_id'] !== null ? $this->personalInput($request) : [];
                $errors = $this->contactErrors($contactNo) + $this->personalErrors($personal);

                if ($errors === []) {
                    $this->membership->saveContact($id, $contactNo, $personal);
                }

                return $errors;

            case 2:
                $this->membership->saveAcademic($id, $answers, $request->text('enrolled') !== '');

                return [];

            case 3:
                // A revision keeps the type it was submitted with, so this step has nothing to save.
                if ($application['status'] !== 'draft') {
                    return [];
                }

                $choice = $request->text('membership_type');

                if ($choice === '') {
                    return $request->text('intent') === 'save' ? [] : ['membership_type' => 'Please choose a membership type.'];
                }

                // "general", "position" with a position_id (opened for application), or an open vacancy's id.
                if ($choice === 'position') {
                    $positionId = (int) $request->text('position_id');

                    if ($positionId <= 0) {
                        return ['membership_type' => 'Please choose the position you are applying for.'];
                    }

                    $this->membership->chooseType($id, null, $positionId);
                } else {
                    $this->membership->chooseType($id, $choice === 'general' ? null : (int) $choice);
                }
                // Recorded right away, because "no vacancy" alone cannot show that general membership was chosen.
                $this->membership->advance($id, 4);
                Session::set(self::VACANCY_KEY, null);
                Session::set(self::CHOICE_KEY, null);

                return [];

            case 4:
                $this->membership->saveAnswers($id, $answers, $this->questionUploads($request));

                return [];

            default:
                return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function questionUploads(Request $request): array
    {
        $uploads = [];

        foreach ($_FILES as $name => $file) {
            if (preg_match('/^q_(\d+)$/', (string) $name, $match) === 1 && is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploads[(int) $match[1]] = $file;
            }
        }

        return $uploads;
    }

    /** @return array<string, string> */
    private function contactErrors(string $contactNo): array
    {
        return Validator::check(['contact_no' => $contactNo], [
            'contact_no' => [
                'label' => 'Contact number',
                'rules' => ['required', 'regex:' . Validator::PHONE_PATTERN],
                'message' => 'Enter a valid contact number, such as 09171234567.',
            ],
        ]);
    }

    /** @return array<string, string> The fields only someone without an account edits here. A member's come from their profile. */
    private function personalInput(Request $request): array
    {
        return current_user_id() !== null ? [] : [
            'first_name' => $request->text('first_name'),
            'last_name' => $request->text('last_name'),
            'student_no' => strtoupper($request->text('student_no')),
        ];
    }

    /**
     * @param array<string, string> $personal
     * @return array<string, string>
     */
    private function personalErrors(array $personal): array
    {
        if ($personal === []) {
            return [];
        }

        return Validator::check($personal, [
            'first_name' => ['label' => 'First name', 'rules' => ['required', 'max:80']],
            'last_name' => ['label' => 'Last name', 'rules' => ['required', 'max:80']],
            'student_no' => [
                'label' => 'PUP student number',
                'rules' => ['required', 'regex:' . Validator::STUDENT_NO_PATTERN],
                'message' => 'Enter your student number in this format: 2024-xxxxx-MN-0.',
            ],
        ]);
    }

    /** Sends a visitor with no login and no proven email back to the start, and says why. */
    private function needsAccess(): ?Response
    {
        if (current_user_id() !== null || ApplicantAccessService::current() !== null) {
            return null;
        }

        flash('warning', 'To continue, please confirm your email or use the link we emailed you.');

        return redirect(route('apply'));
    }

    private function savedMessage(): string
    {
        return current_user_id() !== null
            ? 'Your draft was saved. You can come back to it at any time.'
            : 'Your draft was saved. We emailed you a link to continue it. The link expires in 72 hours.';
    }

    /** @return array<string, mixed>|null */
    private function ownEditable(string $id): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }

        try {
            return $this->membership->editable((int) $id);
        } catch (DomainException) {
            return null;
        }
    }

    private function notEditable(): Response
    {
        flash('warning', 'That application can no longer be changed here.');

        return redirect(current_user_id() !== null ? route('my_applications') : route('track'));
    }

    /** @return array<string, mixed> */
    private function oldInput(Request $request): array
    {
        $old = ['contact_no' => $request->text('contact_no')] + $this->personalInput($request);
        $answers = $request->input('q', []);

        foreach (is_array($answers) ? $answers : [] as $questionId => $answer) {
            if (is_string($answer)) {
                $old["q_{$questionId}"] = $answer;
            }
        }

        return $old;
    }

    private function stepUrl(int $id, int $step): string
    {
        return route('apply.step', ['id' => $id, 'step' => $step]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function backWithErrors(string $to, array $errors, array $old = []): Response
    {
        flash('_errors', $errors);
        flash('_old_input', $old);

        return redirect($to);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return $this->page($view, $data + [
            'user' => $this->membership->person($data['application']),
            'mayChooseGeneral' => current_user_id() === null || !(new \App\Models\UserRepository())->hasMemberRecord(current_user_id()),
            // Choosing an open position is for people without a position yet (MembershipService::assertPositionChoice).
            'mayChoosePosition' => current_user_id() === null || ((new \App\Models\MemberRepository())->findByUser(current_user_id())['position_id'] ?? null) === null,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'My Applications', 'url' => route('my_applications')], ['label' => 'Apply']],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return current_user_id() === null ? $this->publicPage($view, $data) : Response::html(View::render($view, $data, 'layouts/dashboard'));
    }

    /** @param array<string, mixed> $data */
    private function publicPage(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/public'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
