<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\AssessmentService;
use App\Services\InterviewService;
use DateTimeImmutable;
use DomainException;

/** Interviews. Rules and permission checks are in InterviewService. */
final class InterviewsController
{
    public function __construct(private readonly InterviewService $interviews = new InterviewService())
    {
    }

    public function index(Request $request): Response
    {
        $result = $request->queryText('result');
        $valid = $result === 'pending' || isset(InterviewService::RESULTS[$result]);

        return Response::html(View::render('recruitment/interviews/index', [
            'result' => $this->interviews->list($valid ? ['result' => $result] : [], max(1, (int) $request->queryText('page'))),
            'filter' => $valid ? $result : '',
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Interviews']],
        ], 'layouts/dashboard'));
    }

    public function schedule(Request $request, string $id): Response
    {
        return $this->save($request, $id, null);
    }

    public function reschedule(Request $request, string $id, string $interviewId): Response
    {
        return $this->save($request, $id, (int) $interviewId);
    }

    public function checklist(Request $request, string $id, string $interviewId): Response
    {
        try {
            $this->interviews->saveChecklist((int) $id, (int) $interviewId, [
                'identity' => $request->text('identity_verified') === '1',
                'role' => $request->text('role_confirmed') === '1',
                'privacy' => $request->text('privacy_reminder_given') === '1',
                'conflict' => $request->text('conflict_of_interest'),
            ]);
            flash('success', $request->text('conflict_of_interest') === 'yes'
                ? 'Conflict of interest recorded. Reschedule this interview with another interviewer.'
                : 'The interview checklist was confirmed. You can now record the result.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($this->back($id));
    }

    public function recordResult(Request $request, string $id, string $interviewId): Response
    {
        $notes = $request->text('notes');
        $errors = Validator::check(['notes' => $notes], ['notes' => ['label' => 'Notes', 'rules' => ['max:2000']]]);

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect($this->back($id));
        }

        try {
            $this->interviews->recordResult((int) $id, (int) $interviewId, $request->text('result'), $notes);
            flash('success', 'The interview result was saved.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect($this->back($id));
    }

    /** The MSW interviewer's assessment, after the interview (AGILE's Applicant Evaluation sheet). */
    public function assess(Request $request, string $id): Response
    {
        $ratings = $request->input('rating', []);
        $remarks = $request->input('remark', []);

        try {
            (new AssessmentService())->save((int) $id, is_array($ratings) ? $ratings : [], is_array($remarks) ? $remarks : [], [
                'recommendation' => $request->text('recommendation'),
                'strengths' => $request->text('strengths'),
                'development' => $request->text('development'),
                'remarks' => $request->text('assessment_remarks'),
            ]);
            flash('success', 'The assessment was saved. The Committee Head reads it on the Applicant profile tab.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            flash('_old_input', $request->all());
        }

        return redirect($this->back($id));
    }

    private function save(Request $request, string $id, ?int $interviewId): Response
    {
        $form = [
            'scheduled_at' => $request->text('scheduled_at'),
            'mode' => $request->text('mode'),
            'location' => $request->text('location'),
            'interviewer_id' => $request->text('interviewer_id'),
        ];
        $errors = Validator::check($form, [
            'scheduled_at' => ['label' => 'Date and time', 'rules' => ['required']],
            'mode' => ['label' => 'Mode', 'rules' => ['required']],
            'location' => ['label' => 'Link or venue', 'rules' => ['required', 'max:255']],
            'interviewer_id' => ['label' => 'Interviewer', 'rules' => ['required', 'regex:/^\d{1,10}$/']],
        ]);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $form['scheduled_at']);

        if (!isset($errors['scheduled_at']) && $date === false) {
            $errors['scheduled_at'] = 'Enter a valid date and time.';
        }

        $data = [
            'scheduled_at' => $date === false ? '' : $date->format('Y-m-d H:i:s'),
            'mode' => $form['mode'],
            'location' => $form['location'],
            'interviewer_id' => (int) $form['interviewer_id'],
        ];

        if ($date !== false && !isset($errors['location'])) {
            $errors += InterviewService::detailErrors($data, date('Y-m-d H:i:s'));
        }

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', $form);

            return redirect($this->back($id));
        }

        try {
            if ($interviewId === null) {
                $this->interviews->schedule((int) $id, $data);
                flash('success', 'The interview was scheduled, and the applicant was sent an invite.');
            } else {
                $this->interviews->reschedule((int) $id, $interviewId, $data);
                flash('success', 'The interview was rescheduled, and the applicant was sent a new invite.');
            }
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            flash('_old_input', $form);
        }

        return redirect($this->back($id));
    }

    private function back(string $id): string
    {
        return route('applications.show', ['id' => (int) $id]) . '?tab=interviews';
    }
}
