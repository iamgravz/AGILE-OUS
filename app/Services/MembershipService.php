<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\Validator;
use App\Models\ApplicantRepository;
use App\Models\ApplicationAccessLinkRepository;
use App\Models\ApplicationAnswerRepository;
use App\Models\ApplicationDocumentRepository;
use App\Models\ApplicationQuestionRepository;
use App\Models\AuditLogRepository;
use App\Models\CommitteeRepository;
use App\Models\MemberRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\NotificationRepository;
use App\Models\PositionRepository;
use App\Models\RoleRepository;
use App\Models\UserRepository;
use App\Models\VacancyRepository;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Membership applications. The applicant's methods act only on the visitor's own records: a logged-in member, or
 * someone without an account who proved their email (BR-18, ApplicantAccessService).
 * The staff methods further down check permissions themselves, on top of the route checks.
 */
final class MembershipService
{
    public const LAST_STEP = 6;

    /**
     * Every document type an application can hold. doc_type => label. Which ones the documents step asks for depends on
     * what the person applies for: see requiredDocuments(). School ID is no longer asked for (Nov 22, 2026); it stays
     * here so documents uploaded before then still show their name.
     */
    public const DOCUMENT_TYPES = [
        'cor' => 'Current COR (Certificate of Registration)',
        'grades' => 'Previous semester copy of grades',
        'certificate' => 'Certificates (optional)',
        'school_id' => 'School ID',
    ];
    /** The year level answer that, in a 1st semester, means there are no grades yet. */
    private const FIRST_YEAR = '1st Year';

    // No 0/O, 1/I or L, so a code can be read aloud or retyped without mistakes.
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 12;
    private const PENDING_REMINDER_DAYS = 3;
    private const REVISION_REMINDER_DAYS = 3;
    private const ACTIVE_MESSAGE_GENERAL = 'You already have an active general membership application (%s). Only one is allowed.';
    private const ACTIVE_MESSAGE_STUDENT = 'This student number already has an active general membership application (%s). Only one is allowed.';
    private const ACTIVE_MESSAGE_VACANCY = 'You already have an active application for this position (%s). Only one is allowed per position.';
    private const ANSWER_MAX_LENGTH = 5000;

    public function __construct(
        private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository(),
        private readonly ApplicationQuestionRepository $questions = new ApplicationQuestionRepository(),
        private readonly ApplicationAnswerRepository $answers = new ApplicationAnswerRepository(),
        private readonly ApplicationDocumentRepository $documents = new ApplicationDocumentRepository(),
        private readonly VacancyRepository $vacancies = new VacancyRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly ApplicantRepository $applicants = new ApplicantRepository(),
    ) {
    }

    public static function generateTrackingCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    public static function isEditable(string $status): bool
    {
        return in_array($status, ['draft', 'for_revision'], true);
    }

    /** @return array<string, mixed>|null */
    public function currentDraft(): ?array
    {
        $this->authorizeApplicant();
        $access = ApplicantAccessService::current();

        return $access !== null && current_user_id() === null
            ? $this->applications->findDraftForApplicant($access['applicant_id'])
            : $this->applications->findDraft($this->userId());
    }

    /**
     * Creates the draft when step 1 is first saved. The status change to submitted writes the first history row.
     *
     * @param array{first_name?: string, last_name?: string, student_no?: string} $personal Only for someone without an account.
     */
    public function startDraft(string $contactNo, array $personal = []): int
    {
        $this->authorizeApplicant();

        return current_user_id() === null ? $this->startApplicantDraft($contactNo, $personal) : Database::transaction(function () use ($contactNo): int {
            $userId = $this->userId();
            $this->users->lockRow($userId);
            $existing = $this->applications->findDraft($userId);
            $this->users->update($userId, ['contact_no' => $contactNo]);

            return $existing !== null ? (int) $existing['application_id'] : $this->insertDraft(['user_id' => $userId]);
        });
    }

    /** @param array<string, string> $personal */
    private function startApplicantDraft(string $contactNo, array $personal): int
    {
        $applicantId = ApplicantAccessService::current()['applicant_id'];

        $id = Database::transaction(function () use ($applicantId, $contactNo, $personal): int {
            $this->applicants->lockRow($applicantId);
            $existing = $this->applications->findDraftForApplicant($applicantId);
            $this->applicants->update($applicantId, $personal + ['contact_no' => $contactNo]);

            return $existing !== null ? (int) $existing['application_id'] : $this->insertDraft(['applicant_id' => $applicantId]);
        });

        ApplicantAccessService::bindApplication($id);
        (new ApplicantAccessService())->sendDraftLink($id);

        return $id;
    }

    /** @param array<string, int> $owner */
    private function insertDraft(array $owner): int
    {
        $id = $this->applications->insert($owner + [
            'tracking_code' => $this->newTrackingCode(),
            'status' => 'draft',
            'wizard_step' => 1,
        ]);
        AuditService::log('application.draft_created', 'membership_application', $id);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     * @throws ForbiddenException for someone else's application
     */
    public function findOwn(int $id): ?array
    {
        $application = $this->applications->findDetailed($id);

        if ($application === null) {
            return null;
        }

        if (!$this->isOwner($application)) {
            throw new ForbiddenException('application.own');
        }

        return $application;
    }

    /** @param array<string, mixed> $application A row from findDetailed(). */
    private function isOwner(array $application): bool
    {
        if (current_user_id() !== null) {
            return (int) $application['owner_user_id'] === current_user_id();
        }

        $access = ApplicantAccessService::current();

        return $access !== null
            && (int) $application['applicant_id'] === $access['applicant_id']
            && (int) $application['application_id'] === $access['application_id'];
    }

    /**
     * Name, email and contact details of whoever owns an application, or of the visitor when there is none yet.
     *
     * @param array<string, mixed>|null $application
     * @return array<string, mixed>
     */
    public function person(?array $application): array
    {
        $applicantId = $application['applicant_id'] ?? (current_user_id() === null ? ApplicantAccessService::current()['applicant_id'] ?? null : null);

        return $applicantId !== null ? $this->applicants->find((int) $applicantId) : $this->users->find((int) ($application['user_id'] ?? $this->userId()));
    }

    /**
     * Who may do what is for the caller. An account gets the in-app notice too. Someone without one gets the email only.
     *
     * @param array<string, mixed> $row Holds user_id and applicant_id.
     * @param array<string, mixed> $payload
     */
    public function notifyOwner(array $row, string $template, array $payload, ?string $link = null): void
    {
        $applicant = $row['applicant_id'] === null ? null : $this->applicants->find((int) $row['applicant_id']);
        $userId = $row['user_id'] ?? $applicant['user_id'] ?? null;

        if ($userId !== null) {
            $this->notifications->notify((int) $userId, $template, $payload, $link);

            return;
        }

        $this->notifications->notifyEmail($applicant['email'], $template, $payload + ['name' => $applicant['first_name'], 'link' => '/track']);
    }

    /** @return array<string, mixed> An application the user may still change. */
    public function editable(int $id): array
    {
        $this->authorizeApplicant();
        $application = $this->findOwn($id) ?? throw new DomainException('This application no longer exists.');

        if (!self::isEditable($application['status'])) {
            throw new DomainException('This application can no longer be changed.');
        }

        // Without an account, a revision needs the emailed link. Any other proof of the email only opens a draft.
        if (current_user_id() === null && $application['status'] === 'for_revision' && !ApplicantAccessService::current()['may_revise']) {
            throw new DomainException('Please use the link we email you to revise this application.');
        }

        return $application;
    }

    /** @param array<string, string> $personal Only for someone without an account. */
    public function saveContact(int $id, string $contactNo, array $personal = []): void
    {
        $application = $this->editable($id);

        if ($application['applicant_id'] !== null) {
            $this->applicants->update((int) $application['applicant_id'], $personal + ['contact_no' => $contactNo]);

            return;
        }

        $this->users->update($this->userId(), ['contact_no' => $contactNo]);
    }

    /** @param array<int|string, mixed> $answers */
    public function saveAcademic(int $id, array $answers, bool $enrolled): void
    {
        $application = $this->editable($id);

        Database::transaction(function () use ($id, $answers, $enrolled, $application): void {
            $academic = $this->questions->forRoleTypes(['academic']);
            $this->storeAnswers($id, $academic, $answers);
            $this->applications->update($id, ['enrollment_confirmed_at' => $enrolled ? date('Y-m-d H:i:s') : null]);

            if ($application['applicant_id'] !== null && $academic !== []) {
                $yearLevel = $this->answers->forApplication($id)[(int) $academic[0]['question_id']] ?? '';
                $this->applicants->update((int) $application['applicant_id'], ['year_level' => $yearLevel === '' ? null : $yearLevel]);
            }
        });
    }

    /**
     * Step 3. $vacancyId is null for general membership.
     *
     * @throws DomainException with a message to show the applicant
     */
    public function chooseType(int $id, ?int $vacancyId, ?int $committeeId = null): void
    {
        $application = $this->editable($id);

        if ($application['status'] !== 'draft') {
            throw new DomainException('The membership type cannot be changed during a revision.');
        }

        if ($vacancyId !== null && $committeeId !== null) {
            throw new DomainException('Please choose either a committee or an open position, not both.');
        }

        $this->assertAllowedChoice($application, $vacancyId, $committeeId);
        $this->assertCommitteeChoice($application, $committeeId);

        Database::transaction(function () use ($id, $vacancyId, $committeeId): void {
            $this->applications->update($id, ['vacancy_id' => $vacancyId, 'committee_id' => $committeeId]);
            $application = $this->applications->findDetailed($id);
            $keep = array_map(static fn (array $q): int => (int) $q['question_id'], [
                ...$this->questions->forRoleTypes(['academic']),
                ...$this->stepFourQuestions($application),
            ]);
            $this->answers->deleteExcept($id, $keep);
        });
    }

    /**
     * Step 4.
     *
     * @param array<int|string, mixed> $answers
     * @param array<int, array<string, mixed>> $uploads question_id => uploaded file, for file questions
     * @throws FileRejectedException
     */
    public function saveAnswers(int $id, array $answers, array $uploads): void
    {
        $application = $this->editable($id);
        $questions = $this->stepFourQuestions($application);

        Database::transaction(function () use ($id, $questions, $answers): void {
            $this->storeAnswers($id, $questions, $answers);
        });

        foreach ($questions as $question) {
            $questionId = (int) $question['question_id'];

            if ($question['input_type'] === 'file' && isset($uploads[$questionId])) {
                $document = $this->replaceDocument($application, "question_{$questionId}", $uploads[$questionId]);
                Database::transaction(function () use ($id, $questionId, $document): void {
                    $this->answers->put($id, $questionId, $document['original_name']);
                });
            }
        }
    }

    public function advance(int $id, int $reached): void
    {
        $application = $this->editable($id);

        if ($reached > (int) $application['wizard_step']) {
            $this->applications->update($id, ['wizard_step' => min($reached, self::LAST_STEP)]);
        }
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed> The saved document.
     * @throws FileRejectedException
     */
    public function uploadDocument(int $id, string $docType, array $file): array
    {
        $application = $this->editable($id);

        if (!in_array($docType, $this->documentsToAsk($this->applications->findDetailed($id)), true)) {
            throw new DomainException('This document is not asked for in your application.');
        }

        return $docType === 'certificate'
            ? $this->addDocument($application, $docType, $file)
            : $this->replaceDocument($application, $docType, $file);
    }

    public function removeDocument(int $id, int $documentId): void
    {
        $application = $this->editable($id);
        $document = $this->documents->find($documentId);

        if ($document === null || (int) $document['application_id'] !== $id) {
            throw new DomainException('This document no longer exists.');
        }

        if ($application['status'] !== 'draft' && $document['verification_status'] !== 'pending') {
            throw new DomainException('This document was already reviewed, so it cannot be removed.');
        }

        $this->documents->delete($documentId);
        $this->files->delete($document['stored_path']);
        AuditService::log('application.document_removed', 'application_document', $documentId, ['doc_type' => $document['doc_type']]);
    }

    /**
     * Rechecks everything, because a browser can skip any step.
     *
     * @throws ApplicationIncompleteException when details are missing
     * @throws DomainException when a rule such as BR-02 blocks the submission
     */
    public function submit(int $id, bool $consent): void
    {
        $this->authorizeApplicant();

        Database::transaction(function () use ($id, $consent): void {
            $owner = $this->applications->findDetailed($id) ?? throw new DomainException('This application no longer exists.');

            if (!$this->isOwner($owner)) {
                throw new ForbiddenException('application.own');
            }

            // Same lock order for both kinds of owner, so two tabs cannot both pass the BR-02 check.
            if ($owner['applicant_id'] !== null) {
                $this->applicants->lockRow((int) $owner['applicant_id']);
            } else {
                $this->users->lockRow($this->userId());
            }

            $row = $this->applications->findForUpdate($id) ?? throw new DomainException('This application no longer exists.');

            if (!self::isEditable($row['status'])) {
                throw new DomainException('This application was already submitted.');
            }

            $application = $this->applications->findDetailed($id);

            foreach (range(1, self::LAST_STEP - 1) as $step) {
                $errors = $this->errorsForStep($application, $step);

                if ($errors !== []) {
                    throw new ApplicationIncompleteException($step, $errors);
                }
            }

            if (!$consent) {
                throw new ApplicationIncompleteException(self::LAST_STEP, ['privacy_consent' => 'Your consent is required before submitting.']);
            }

            $vacancyId = $application['vacancy_id'] === null ? null : (int) $application['vacancy_id'];
            $committeeId = $application['committee_id'] === null ? null : (int) $application['committee_id'];

            if ($row['status'] === 'draft') {
                $this->assertAllowedChoice($application, $vacancyId, $committeeId);
                $this->assertCommitteeChoice($application, $committeeId);
            }

            $from = $row['status'];
            $now = date('Y-m-d H:i:s');
            $this->changeStatus($row, 'submitted', ['submitted_at' => $now, 'consent_at' => $now], null);

            $this->notifyOwner($row, 'APP_SUBMITTED', [
                'tracking_code' => $row['tracking_code'],
                'link' => '/track',
            ], "/my-applications/{$id}");

            AuditService::log($from === 'draft' ? 'application.submitted' : 'application.resubmitted', 'membership_application', $id, ['from' => $from, 'to' => 'submitted']);
        });

        ApplicantAccessService::endRevision();
    }

    public function withdraw(int $id): void
    {
        Database::transaction(function () use ($id): void {
            $row = $this->applications->findForUpdate($id) ?? throw new DomainException('This application no longer exists.');

            if ((int) $this->applications->findDetailed($id)['owner_user_id'] !== current_user_id()) {
                throw new ForbiddenException('application.own');
            }

            $this->changeStatus($row, 'withdrawn', [], null);
            AuditService::log('application.withdrawn', 'membership_application', $id, ['from' => $row['status'], 'to' => 'withdrawn']);
        });
    }

    /** For someone without an account, who proved the email with a link. ApplicantAccessService used the link and holds the transaction. */
    public function withdrawByLink(int $id): void
    {
        $row = $this->applications->findForUpdate($id) ?? throw new DomainException('This application no longer exists.');
        $this->changeStatus($row, 'withdrawn', [], null);
        AuditService::log('application.withdrawn', 'membership_application', $id, ['from' => $row['status'], 'to' => 'withdrawn', 'by' => 'emailed_link']);
    }

    /** @return list<array<string, mixed>> */
    public function myApplications(): array
    {
        return $this->applications->forUser($this->userId());
    }

    /** @return array<string, mixed> Everything the wizard and summary pages show for one application. */
    public function details(array $application): array
    {
        $id = (int) $application['application_id'];

        return [
            'academicQuestions' => $this->questions->forRoleTypes(['academic']),
            'questions' => $this->stepFourQuestions($application),
            'answers' => $this->answers->forApplication($id),
            'documents' => $this->documents->forApplication($id),
            'requiredDocuments' => $this->requiredDocuments($application),
            'documentsToAsk' => $this->documentsToAsk($application),
            'firstYearFirstSemester' => $this->isFirstYearFirstSemester($application),
        ];
    }

    /** @return list<array<string, mixed>> Status changes with remarks, for the applicant's own timeline. */
    public function timeline(int $id): array
    {
        return $this->applications->fullHistory($id);
    }

    /** @return list<array<string, mixed>> */
    public function openVacancies(): array
    {
        return $this->vacancies->openList();
    }

    /** @return array<string, string> field => message. Empty when the step is complete. */
    public function errorsForStep(array $application, int $step): array
    {
        return match ($step) {
            1 => $this->personalErrors($application),
            2 => $this->academicErrors($application),
            3 => $this->typeErrors($application),
            4 => $this->questionErrors($application),
            5 => $this->documentErrors($application),
            default => [],
        };
    }

    /* ---------- The staff side (section 6) ---------- */

    /**
     * @param array{status?: string, type?: string, vacancy_id?: int, from?: string, to?: string, q?: string} $filters
     */
    public function listForStaff(array $filters, string $sort, string $direction, int $page): array
    {
        authorize('evaluation.view');

        if (!can('application.update_status')) {
            $filters['reviewed_only'] = true;
        }

        return $this->applications->searchForStaff($filters, $sort, $direction, $page);
    }

    /** @return list<array<string, mixed>> */
    public function vacancyFilterOptions(): array
    {
        authorize('evaluation.view');

        return $this->applications->vacanciesWithApplications();
    }

    /**
     * Opens an application for staff. Every open is logged (FR-MEM-14).
     *
     * @return array<string, mixed>|null
     * @throws ForbiddenException
     */
    public function openForStaff(int $id): ?array
    {
        authorize('evaluation.view');

        $application = $this->applications->findDetailed($id);

        if ($application === null || $application['status'] === 'draft') {
            return null;
        }

        // A committee head may read approved or already evaluated applications, and position applications they can evaluate.
        $canEvaluate = $application['vacancy_id'] !== null && in_array($application['status'], EvaluationService::OPEN_STATUSES, true);

        if (!can('application.update_status') && $application['status'] !== 'approved' && !$canEvaluate && !$this->isOwnCommitteeHead($application) && !$this->applications->hasEvaluation($id)) {
            throw new ForbiddenException('application.review_scope');
        }

        AuditService::log('application.viewed', 'membership_application', $id);

        return $application;
    }

    /** @return array<string, mixed> Everything the staff detail page shows. */
    public function staffDetails(array $application): array
    {
        $id = (int) $application['application_id'];
        $details = $this->details($application);
        $documentIds = array_map(static fn (array $d): int => (int) $d['document_id'], $details['documents']);

        return $details + [
            'applicant' => $this->person($application),
            'timeline' => $this->applications->historyWithActors($id),
            'activity' => can('application.update_status') ? (new AuditLogRepository())->forApplication($id, $documentIds) : [],
            'missingDocuments' => $this->documentsBlockingApproval($application, $details['documents']),
            'mayDecide' => $this->canDecide($application),
            'decider' => $this->decider($application),
            'isOwnCommitteeHead' => $this->isOwnCommitteeHead($application),
        ];
    }

    /**
     * BR-20: the committee head who decides this application, or null when the MSW Committee Head does: general
     * membership, executive and MSW positions, a committee with no head, and a head applying to their own committee.
     *
     * @param array<string, mixed> $application A row from findDetailed().
     */
    public function committeeDecider(array $application): ?int
    {
        $head = $application['committee_head_id'] === null ? null : (int) $application['committee_head_id'];

        // R6: a Committee Head position is decided by the MSW Committee Head, not by the head being replaced.
        // The Source Code has no committee head, so its positions go to the MSW Committee Head too.
        if ($head === null
            || $application['committee_name'] === config('role_mapping.msw_committee')
            || ($application['role_type'] ?? null) === 'committee_head'
            || $head === (int) $application['owner_user_id']) {
            return null;
        }

        return $head;
    }

    /**
     * BR-20: who must decide this application.
     *
     * @param array<string, mixed> $application A row from findDetailed().
     * @return array{user_ids: list<int>, label: string, committee: bool} More than one id only if there is more than one MSW Committee Head.
     */
    public function decider(array $application): array
    {
        $head = $this->committeeDecider($application);

        if ($head !== null) {
            return ['user_ids' => [$head], 'label' => $application['committee_head_name'] . ', ' . $application['committee_name'] . ' Committee Head', 'committee' => true];
        }

        $ids = array_map('intval', $this->users->activeIdsByRole('msw_head'));
        $first = $ids === [] ? null : $this->users->find($ids[0]);

        return ['user_ids' => $ids, 'label' => ($first === null ? '' : $first['first_name'] . ' ' . $first['last_name'] . ', ') . 'MSW Committee Head', 'committee' => false];
    }

    /** @param array<string, mixed> $application A row from findDetailed(). */
    public function canDecide(array $application): bool
    {
        return can('application.decide') && in_array(current_user_id(), $this->decider($application)['user_ids'], true);
    }

    /** The head of the committee an application belongs to, who reads it in full. The decision panel stays with decider(). */
    public function isOwnCommitteeHead(array $application): bool
    {
        return $application['committee_name'] !== null && can('application.decide') && (int) ($application['committee_head_id'] ?? 0) === current_user_id();
    }

    /**
     * @param array{status?: string, vacancy_id?: int} $filters
     * @return array{applications: list<array<string, mixed>>, vacancies: list<array<string, mixed>>}
     */
    public function listForCommitteeHead(array $filters): array
    {
        authorize('application.decide');

        $userId = $this->userId();
        $vacancies = [];

        foreach ($this->applications->forCommitteeHead($userId, []) as $row) {
            $vacancies[(int) $row['vacancy_id']] = ['vacancy_id' => (int) $row['vacancy_id'], 'title' => $row['vacancy_title']];
        }

        return ['applications' => $this->applications->forCommitteeHead($userId, $filters), 'vacancies' => array_values($vacancies)];
    }

    /** @throws ForbiddenException A forced attempt, which the router logs. */
    private function authorizeDecision(int $id): void
    {
        authorize('application.decide');

        $application = $this->applications->findDetailed($id);

        if ($application !== null && !$this->canDecide($application)) {
            throw new ForbiddenException('application.decide');
        }
    }

    /** When nothing but a decision is left, tell whoever makes it. The caller holds the transaction. */
    private function notifyIfReadyForDecision(int $id): void
    {
        $application = $this->applications->findDetailed($id);

        if ($application['status'] !== 'under_review' || $this->documentsBlockingApproval($application, $this->documents->forApplication($id)) !== []) {
            return;
        }

        foreach ($this->decider($application)['user_ids'] as $userId) {
            if ($userId !== (int) $application['owner_user_id']) {
                $this->notifications->notify($userId, 'APP_READY_FOR_DECISION', ['tracking_code' => $application['tracking_code']], "/applications/{$id}");
            }
        }
    }

    /**
     * pending -> verified, needs_correction or rejected. Remarks are required unless verifying.
     */
    public function verifyDocument(int $applicationId, int $documentId, string $decision, string $remarks): void
    {
        authorize('document.verify');

        if ($decision !== 'verified' && trim($remarks) === '') {
            throw new DomainException('Remarks are required when a document is not verified.');
        }

        Database::transaction(function () use ($applicationId, $documentId, $decision, $remarks): void {
            $application = $this->lockForStaff($applicationId);

            if (!in_array($application['status'], ['submitted', 'under_review'], true)) {
                throw new DomainException('Documents can only be checked while the application is Submitted or Under Review.');
            }

            $document = $this->documents->find($documentId);

            if ($document === null || (int) $document['application_id'] !== $applicationId) {
                throw new DomainException('This document no longer exists.');
            }

            DocumentStatus::assertTransition($document['verification_status'], $decision);

            $this->documents->update($documentId, [
                'verification_status' => $decision,
                'verification_remarks' => trim($remarks) === '' ? null : trim($remarks),
                'verified_by' => current_user_id(),
                'verified_at' => date('Y-m-d H:i:s'),
            ]);
            AuditService::log('document.' . $decision, 'application_document', $documentId, [
                'application_id' => $applicationId,
                'doc_type' => $document['doc_type'],
                'from' => $document['verification_status'],
                'to' => $decision,
            ]);

            if ($decision === 'verified') {
                $this->notifyIfReadyForDecision($applicationId);
            }

            if ($decision === 'needs_correction') {
                $this->notifyOwner($application, 'APP_DOCUMENT_CORRECTION', [
                    'tracking_code' => $application['tracking_code'],
                    'document' => self::documentLabel($document['doc_type']),
                    'remarks' => trim($remarks),
                ], "/my-applications/{$applicationId}");
            }
        });
    }

    public function markUnderReview(int $id, string $remarks): void
    {
        authorize('application.update_status');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $row = $this->lockForStaff($id);
            $this->changeStatus($row, 'under_review', [], $remarks);
            AuditService::log('application.under_review', 'membership_application', $id, ['from' => $row['status'], 'to' => 'under_review']);
            $this->notifyIfReadyForDecision($id);
        });
    }

    public function returnForRevision(int $id, string $remarks): void
    {
        authorize('application.return');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $row = $this->lockForStaff($id);
            $this->changeStatus($row, 'for_revision', [], $remarks);
            AuditService::log('application.returned', 'membership_application', $id, ['from' => $row['status'], 'to' => 'for_revision']);
            // Without an account, the email carries a fresh edit link. It goes in the payload, which the queue erases.
            $this->notifyOwner($row, 'APP_FOR_REVISION', [
                'tracking_code' => $row['tracking_code'],
                'remarks' => $remarks,
            ] + ($row['applicant_id'] !== null ? ['link' => (new ApplicantAccessService())->newRevisionLink($id)] : []), "/my-applications/{$id}");
        });
    }

    /**
     * The decider under BR-20. An appointment still goes to the MSW Committee Head afterwards. Everything succeeds together or not at all: the status, the member record,
     * the role, the history, the credentials and the notification.
     */
    public function approve(int $id, string $remarks): void
    {
        $this->authorizeDecision($id);
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $row = $this->lockForStaff($id);
            $application = $this->applications->findDetailed($id);
            $missing = $this->documentsBlockingApproval($application, $this->documents->forApplication($id));

            if ($missing !== []) {
                throw new DomainException('This application cannot be approved until every required document is verified: ' . implode(', ', $missing) . '.');
            }

            $this->changeStatus($row, 'approved', $this->decisionValues($remarks), $remarks);
            $applicant = $row['applicant_id'] === null ? null : $this->applicants->find((int) $row['applicant_id']);
            $accountId = $row['user_id'] ?? $applicant['user_id'] ?? null;
            $member = $accountId !== null ? $this->ensureMember((int) $accountId, $remarks) : $this->ensureApplicantMember((int) $row['applicant_id'], $remarks);

            if ($row['committee_id'] !== null) {
                $this->assignCommitteeMember((int) $member['member_id'], (int) $row['committee_id'], $remarks);
            }

            AuditService::log('application.approved', 'membership_application', $id, [
                'from' => $row['status'],
                'to' => 'approved',
                'member_id' => $member['member_id'],
            ]);

            // AppointmentService picks up approved vacancy applications that have no appointment yet.
            if ($row['vacancy_id'] !== null) {
                AuditService::log('application.ready_for_recruitment', 'membership_application', $id, ['vacancy_id' => (int) $row['vacancy_id']]);
            }

            (new CredentialService())->issueFor((int) $member['member_id']);

            $this->notifyOwner($row, 'APP_APPROVED', [
                'tracking_code' => $row['tracking_code'],
                'member_no' => $member['member_no'],
                'next_step' => $application['vacancy_id'] !== null && $this->committeeDecider($application) !== null
                    ? 'Accepted by the ' . $application['committee_name'] . ' Committee. The MSW Committee Head will confirm your appointment.'
                    : ($accountId !== null ? 'Your digital ID and certificate will be ready after the MSW Committee Head reviews them.' : 'Create your AGILE account at ' . url('/register') . ' using your PUP student number ' . $applicant['student_no'] . '.'),
                'link_text' => $accountId !== null ? 'Open my member profile' : 'Create my account',
                'link' => $accountId !== null ? '/account' : '/register',
            ], $accountId !== null ? '/account' : null);
        });
    }

    /**
     * AGILE's Waitlisted decision: the applicant qualifies but there is no slot now. The same decider (BR-20) can approve
     * or reject the application later, for example when a slot opens.
     */
    public function waitlist(int $id, string $remarks): void
    {
        $this->authorizeDecision($id);
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $row = $this->lockForStaff($id);
            $this->changeStatus($row, 'waitlisted', ['decision_remarks' => trim($remarks)], $remarks);
            AuditService::log('application.waitlisted', 'membership_application', $id, ['from' => $row['status'], 'to' => 'waitlisted']);
            $this->notifyOwner($row, 'APP_WAITLISTED', [
                'tracking_code' => $row['tracking_code'],
                'remarks' => $remarks,
            ]);
        });
    }

    public function reject(int $id, string $remarks): void
    {
        $this->authorizeDecision($id);
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $row = $this->lockForStaff($id);
            $this->changeStatus($row, 'rejected', $this->decisionValues($remarks), $remarks);
            AuditService::log('application.rejected', 'membership_application', $id, ['from' => $row['status'], 'to' => 'rejected']);
            $this->notifyOwner($row, 'APP_REJECTED', [
                'tracking_code' => $row['tracking_code'],
                'remarks' => $remarks,
            ]);
        });
    }

    public static function documentLabel(string $docType): string
    {
        return self::DOCUMENT_TYPES[$docType] ?? 'Attachment';
    }

    /**
     * AGILE's membership requirements. General Membership: the current COR. A committee or officer position (a vacancy,
     * or joining a committee as a Committee Member): the current COR and the previous semester's copy of grades, except
     * for 1st years in their 1st semester, who have no grades yet. 1st years joining in the 2nd semester give their 1st
     * semester grades, which is the same "previous semester" document.
     *
     * @param array<string, mixed> $application A row from findDetailed().
     * @return list<string> doc_types
     */
    public function requiredDocuments(array $application): array
    {
        return $this->needsGrades($application) ? ['cor', 'grades'] : ['cor'];
    }

    /**
     * The upload slots on the documents step: the required ones, then optional certificates.
     *
     * @param array<string, mixed> $application A row from findDetailed().
     * @return list<string>
     */
    public function documentsToAsk(array $application): array
    {
        return [...$this->requiredDocuments($application), 'certificate'];
    }

    /** @param array<string, mixed> $application A row from findDetailed(). */
    public function needsGrades(array $application): bool
    {
        $forPosition = $application['vacancy_id'] !== null || ($application['committee_id'] ?? null) !== null;

        return $forPosition && !$this->isFirstYearFirstSemester($application);
    }

    /**
     * A 1st year applying in a 1st semester: the year level from step 2, and the current academic term set by the MSW
     * Committee Head. With no current term set, grades are asked for, so nothing is skipped by mistake.
     *
     * @param array<string, mixed> $application
     */
    public function isFirstYearFirstSemester(array $application): bool
    {
        $academic = $this->questions->forRoleTypes(['academic']);
        $yearLevel = $academic === [] ? '' : (string) ($this->answers->forApplication((int) $application['application_id'])[(int) $academic[0]['question_id']] ?? '');
        $term = (new \App\Models\AcademicTermRepository())->current();

        return $yearLevel === self::FIRST_YEAR && ($term['semester'] ?? null) === '1st';
    }

    /**
     * Committees someone can apply to join as a Committee Member: those with a Committee Member position. The Source
     * Code has none; its positions open as vacancies.
     *
     * @return list<array{committee_id: int, name: string, position_id: int}>
     */
    public function joinableCommittees(): array
    {
        return array_map(static fn (array $row): array => [
            'committee_id' => (int) $row['committee_id'],
            'name' => (string) $row['name'],
            'position_id' => (int) $row['position_id'],
        ], Database::connection()->query(
            "SELECT c.committee_id, c.name, p.position_id FROM committees c
             JOIN positions p ON p.committee_id = c.committee_id AND p.role_type = 'committee_member'
             ORDER BY c.name"
        )->fetchAll());
    }

    /**
     * An approved committee application: the member becomes that committee's Committee Member, with the role the
     * position gives (config/role_mapping.php) if they have an account. Without one, /register gives it later. The
     * caller holds the transaction and has checked who may decide (BR-20).
     *
     * @throws DomainException
     */
    private function assignCommitteeMember(int $memberId, int $committeeId, string $reason): void
    {
        $positionId = $this->committeeMemberPosition($committeeId) ?? throw new DomainException('This committee no longer has a Committee Member position, so the application cannot be approved.');
        $members = new MemberRepository();
        $before = $members->findDetailed($memberId) ?? throw new DomainException('This member no longer exists.');

        // Someone who meanwhile got a position keeps it; the committee choice does not replace a role quietly.
        if ($before['position_id'] !== null) {
            return;
        }

        $members->update($memberId, ['position_id' => $positionId, 'membership_type' => 'officer', 'role_status' => 'active', 'role_expired_at' => null]);

        if ($before['user_id'] !== null) {
            $position = (new PositionRepository())->findWithCommittee($positionId);
            $this->users->update((int) $before['user_id'], ['role_id' => (new RoleRepository())->findBySlug(self::roleSlugFor($position))['role_id']]);
            RbacService::clearCache();
        }

        // Written out, because roleLabel() needs an account's role and someone without one has not registered yet.
        $committee = (string) ($this->joinableCommitteeName($committeeId) ?? 'the committee');
        $members->addHistory($memberId, 'General Member', 'Committee Member, ' . $committee, trim($reason), (int) current_user_id());
    }

    private function joinableCommitteeName(int $committeeId): ?string
    {
        foreach ($this->joinableCommittees() as $committee) {
            if ($committee['committee_id'] === $committeeId) {
                return $committee['name'];
            }
        }

        return null;
    }

    private function committeeMemberPosition(int $committeeId): ?int
    {
        foreach ($this->joinableCommittees() as $committee) {
            if ($committee['committee_id'] === $committeeId) {
                return $committee['position_id'];
            }
        }

        return null;
    }

    /**
     * The one way a member's role changes, used by appointments and role expiry. A null position returns them to
     * General Member: they stay an active member (BR-13) and only the role ends. $actorId says who the change is
     * recorded against. $roleOverride replaces the role config/role_mapping.php would give. The last active MSW
     * Committee Head cannot lose the role (BR-10), because only that role can approve the appointment of another.
     *
     * @throws DomainException
     */
    public function changeRole(int $memberId, ?int $newPositionId, string $reason, int $actorId, ?string $roleOverride = null): void
    {
        authorize_any('appointment.approve', 'eligibility.manage', 'eligibility.decide_msw_head', 'admin.settings');
        $this->requireRemarks($reason);

        Database::transaction(function () use ($memberId, $newPositionId, $reason, $actorId, $roleOverride): void {
            $members = new MemberRepository();
            $members->lockRow($memberId);
            $member = $members->findDetailed($memberId) ?? throw new DomainException('This member no longer exists.');
            $position = $newPositionId === null ? null : (new PositionRepository())->findWithCommittee($newPositionId);

            if ($newPositionId !== null && $position === null) {
                throw new DomainException('This position no longer exists.');
            }

            $hadPosition = $member['position_id'] !== null;

            if ($newPositionId === ($hadPosition ? (int) $member['position_id'] : null)) {
                throw new DomainException('This member already holds that role.');
            }

            $userId = (int) $member['user_id'];
            $roleSlug = $roleOverride ?? self::roleSlugFor($position);
            $this->assertNotLastMswHead($member, $roleSlug);
            $committees = new CommitteeRepository();
            $committees->clearHead($userId);

            if (($position['role_type'] ?? null) === 'committee_head') {
                $committees->update((int) $position['committee_id'], ['head_user_id' => $userId]);
            }

            $this->users->update($userId, ['role_id' => (new RoleRepository())->findBySlug($roleSlug)['role_id']]);
            $members->update($memberId, [
                'position_id' => $newPositionId,
                'membership_type' => $position === null || $position['role_type'] === 'general' ? 'general' : 'officer',
                'role_status' => $position !== null ? 'active' : ($hadPosition ? 'expired' : null),
                'role_expired_at' => $position === null && $hadPosition ? date('Y-m-d H:i:s') : null,
            ]);

            $oldLabel = self::roleLabel($member);
            $newLabel = self::roleLabel($members->findDetailed($memberId));
            $members->addHistory($memberId, $oldLabel, $newLabel, trim($reason), $actorId);
            RbacService::clearCache();

            AuditService::log('member.role_changed', 'member', $memberId, ['from' => $oldLabel, 'to' => $newLabel, 'reason' => mb_substr(trim($reason), 0, 255), 'actor_id' => $actorId]);

            (new CredentialService())->reissue($memberId, $reason);
        });
    }

    /**
     * BR-10, BR-13: the officer role ends and they stay an Active General Member, with new credentials and a notice.
     * The caller holds the transaction.
     *
     * @throws DomainException
     */
    public function expireRole(int $memberId, string $remarks, int $actorId): void
    {
        authorize_any('eligibility.manage', 'eligibility.decide_msw_head');
        $this->requireRemarks($remarks);

        $members = new MemberRepository();
        $member = $members->findDetailed($memberId) ?? throw new DomainException('This member no longer exists.');
        $position = self::roleLabel($member);

        $this->changeRole($memberId, null, 'Confirmed ineligible: ' . trim($remarks), $actorId);
        AuditService::log('member.role_expired', 'member', $memberId, ['position' => $position, 'actor_id' => $actorId]);

        $this->notifications->notify((int) $member['user_id'], 'ROLE_EXPIRED', ['position' => $position, 'remarks' => trim($remarks)], '/account');
    }

    /**
     * For jobs/application_reminders.php: tells MSW staff about applications waiting over 3 days. Once a day per person.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendPendingReminders(callable $log): array
    {
        $counts = ['sent' => 0, 'failed' => 0];
        $count = $this->applications->countPendingSince(date('Y-m-d H:i:s', time() - self::PENDING_REMINDER_DAYS * 86400));

        if ($count === 0) {
            return $counts;
        }

        $sent = new NotificationRepository();

        foreach (array_merge($this->users->activeIdsByRole('msw_head'), $this->users->activeIdsByRole('msw_member')) as $userId) {
            try {
                if (!$sent->sentToday((int) $userId, 'APP_PENDING_REMINDER')) {
                    $this->notifications->notify((int) $userId, 'APP_PENDING_REMINDER', ['count' => $count, 'link' => '/applications'], null, ['email']);
                    $counts['sent']++;
                }
            } catch (Throwable $exception) {
                $counts['failed']++;
                $log("User {$userId}: " . $exception->getMessage());
            }
        }

        return $counts;
    }

    /**
     * For jobs/application_reminders.php: reminds people whose application is For Revision every 3 days, with a fresh
     * edit link for those without an account. The queue erases the payload, so for them the edit link's date says when
     * the last one went out, which also counts the email sent when it was returned.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendRevisionReminders(callable $log): array
    {
        $counts = ['sent' => 0, 'failed' => 0];
        // By calendar day, so the job's exact run time does not push a reminder to the next day.
        $since = date('Y-m-d 00:00:00', time() - (self::REVISION_REMINDER_DAYS - 1) * 86400);
        $sent = new NotificationRepository();
        $links = new ApplicationAccessLinkRepository();

        foreach ($this->applications->forRevisionWithRemarks() as $row) {
            $id = (int) $row['application_id'];

            try {
                $userId = $row['user_id'] ?? ($row['applicant_id'] === null ? null : $this->applicants->find((int) $row['applicant_id'])['user_id'] ?? null);
                $alreadyToday = $row['applicant_id'] !== null && $links->issuedSince($id, ApplicantAccessService::PURPOSE_REVISE, $since);
                $alreadyToday = $alreadyToday || ($userId !== null && $sent->sentSince((int) $userId, 'APP_FOR_REVISION', $since));

                if ($alreadyToday) {
                    continue;
                }

                Database::transaction(function () use ($row, $id): void {
                    $this->notifyOwner($row, 'APP_FOR_REVISION', [
                        'tracking_code' => $row['tracking_code'],
                        'remarks' => (string) $row['revision_remarks'],
                    ] + ($row['applicant_id'] !== null ? ['link' => (new ApplicantAccessService())->newRevisionLink($id)] : []), "/my-applications/{$id}");
                });
                $counts['sent']++;
            } catch (Throwable $exception) {
                $counts['failed']++;
                $log("Application {$id}: " . $exception->getMessage());
            }
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $member A row from MemberRepository::findDetailed().
     * @throws DomainException
     */
    private function assertNotLastMswHead(array $member, string $newRoleSlug): void
    {
        if ($member['role_slug'] !== 'msw_head' || $newRoleSlug === 'msw_head') {
            return;
        }

        $others = array_diff($this->users->activeIdsByRole('msw_head'), [(int) $member['user_id']]);

        if ($others === []) {
            throw new DomainException('This person is the only active MSW Committee Head, so this role cannot end yet. Please appoint another MSW Committee Head first.');
        }
    }

    /** @param array<string, mixed> $member A row from MemberRepository::findDetailed(). */
    public static function roleLabel(array $member): string
    {
        if (($member['position_title'] ?? null) === null) {
            return $member['role_name'];
        }

        return $member['committee_name'] === null ? $member['position_title'] : $member['position_title'] . ', ' . $member['committee_name'];
    }

    /**
     * The role a position gives, from config/role_mapping.php. No position means general_member.
     *
     * @param array<string, mixed>|null $position A row from PositionRepository::findWithCommittee().
     */
    public static function roleSlugFor(?array $position): string
    {
        $mapping = config('role_mapping');
        $default = $mapping['default'];

        if ($position === null) {
            return $default;
        }

        if ($position['role_type'] === 'officer') {
            return $mapping['executive'][$position['title']] ?? $mapping['executive']['default'];
        }

        $group = $position['committee_name'] === $mapping['msw_committee'] ? 'msw' : 'other_committees';

        return $mapping[$group][$position['role_type']] ?? $default;
    }

    /**
     * BR-03. Certificates are optional, so they never block. Required file questions do.
     *
     * @param list<array<string, mixed>> $documents
     * @return list<string> Labels of required documents that are not verified yet.
     */
    private function documentsBlockingApproval(array $application, array $documents): array
    {
        $required = [];

        foreach ($this->requiredDocuments($application) as $type) {
            $required[$type] = self::documentLabel($type);
        }

        foreach ($this->stepFourQuestions($application) as $question) {
            if ($question['input_type'] === 'file' && (int) $question['is_required'] === 1) {
                $required['question_' . $question['question_id']] = $question['question_text'];
            }
        }

        $missing = [];

        foreach ($required as $type => $label) {
            $verified = array_filter($documents, static fn (array $d): bool => $d['doc_type'] === $type && $d['verification_status'] === 'verified');

            if ($verified === []) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** @return array<string, mixed> The application row, locked. Nobody may act on their own application. */
    private function lockForStaff(int $id): array
    {
        $row = $this->applications->findForUpdate($id);

        if ($row === null || $row['status'] === 'draft') {
            throw new DomainException('This application no longer exists.');
        }

        if ((int) $row['user_id'] === current_user_id()) {
            throw new ForbiddenException('application.own_review');
        }

        return $row;
    }

    /**
     * Someone who is already a member, applying for a position, keeps their record and role.
     * Their role changes at appointment. The caller holds the transaction.
     *
     * @return array{member_id: int, member_no: string}
     */
    public function ensureMember(int $userId, string $reason): array
    {
        authorize_any('application.decide', 'appointment.approve', 'admin.settings');

        $members = new MemberRepository();
        $existing = $members->findByUser($userId);

        if ($existing !== null) {
            return ['member_id' => (int) $existing['member_id'], 'member_no' => $existing['member_no']];
        }

        $memberNo = $members->nextMemberNo((int) date('Y'));
        $memberId = $members->insert([
            'user_id' => $userId,
            'member_no' => $memberNo,
            'membership_type' => 'general',
            'status' => 'active',
            'joined_at' => date('Y-m-d'),
        ]);

        $user = $this->users->findWithRole($userId);
        $this->users->update($userId, ['role_id' => (new RoleRepository())->findBySlug('general_member')['role_id']]);
        $members->addHistory($memberId, $user['role_name'], 'General Member', $reason, (int) current_user_id());
        RbacService::clearCache();

        return ['member_id' => $memberId, 'member_no' => $memberNo];
    }

    /**
     * BR-18: an approved applicant becomes a member with no account until they register at /register.
     * The caller checks permission and holds the transaction.
     *
     * @return array{member_id: int, member_no: string}
     */
    private function ensureApplicantMember(int $applicantId, string $reason): array
    {
        $members = new MemberRepository();
        $existing = $members->findByApplicant($applicantId);

        if ($existing !== null) {
            return ['member_id' => (int) $existing['member_id'], 'member_no' => $existing['member_no']];
        }

        $memberNo = $members->nextMemberNo((int) date('Y'));
        $memberId = $members->insert([
            'applicant_id' => $applicantId,
            'member_no' => $memberNo,
            'membership_type' => 'general',
            'status' => 'active',
            'joined_at' => date('Y-m-d'),
        ]);
        $members->addHistory($memberId, 'Applicant', 'General Member', $reason, (int) current_user_id());

        return ['member_id' => $memberId, 'member_no' => $memberNo];
    }

    /** @return array<string, mixed> */
    private function decisionValues(string $remarks): array
    {
        return ['decision_remarks' => trim($remarks), 'decided_by' => current_user_id(), 'decided_at' => date('Y-m-d H:i:s')];
    }

    private function requireRemarks(string $remarks): void
    {
        if (trim($remarks) === '') {
            throw new DomainException('Remarks are required.');
        }
    }

    private function personalErrors(array $application): array
    {
        $person = $this->person($application);

        return Validator::check(['contact_no' => (string) $person['contact_no']], [
            'contact_no' => [
                'label' => 'Contact number',
                'rules' => ['required', 'regex:' . Validator::PHONE_PATTERN],
                'message' => 'Enter a valid contact number, such as 09171234567.',
            ],
        ]);
    }

    private function academicErrors(array $application): array
    {
        $errors = $this->requiredAnswerErrors((int) $application['application_id'], $this->questions->forRoleTypes(['academic']));
        if ((string) $this->person($application)['student_no'] === '') {
            $errors['student_no'] = 'We have no student number for you. Please add it on the Personal info step, or contact the MSW Committee.';
        }

        if ($application['enrollment_confirmed_at'] === null) {
            $errors['enrolled'] = 'Please confirm that you are currently enrolled. Only enrolled BSIT-OU students may apply.';
        }

        return $errors;
    }

    private function typeErrors(array $application): array
    {
        if ((int) $application['wizard_step'] < 4 && $application['vacancy_id'] === null) {
            return ['membership_type' => 'Please choose a membership type.'];
        }

        // A position may close between saving this step and submitting. A revision keeps its original choice.
        if ($application['status'] === 'draft' && $application['vacancy_id'] !== null && $this->vacancies->findOpen((int) $application['vacancy_id']) === null) {
            return ['membership_type' => 'This position is no longer open. Please choose another.'];
        }

        return [];
    }

    private function questionErrors(array $application): array
    {
        return $this->requiredAnswerErrors((int) $application['application_id'], $this->stepFourQuestions($application));
    }

    private function documentErrors(array $application): array
    {
        $errors = [];
        $documents = $this->documents->forApplication((int) $application['application_id']);
        $asked = $this->documentsToAsk($application);

        foreach ($this->requiredDocuments($application) as $type) {
            $usable = array_filter($documents, static fn (array $d): bool => $d['doc_type'] === $type && $d['verification_status'] !== 'rejected');

            if ($usable === []) {
                $errors[$type] = self::DOCUMENT_TYPES[$type] . ' is required.';
            }
        }

        // Only what the step still asks for can be corrected there. An older School ID is the reviewer's to settle.
        foreach ($documents as $document) {
            if ($document['verification_status'] === 'needs_correction' && in_array($document['doc_type'], $asked, true) && !isset($errors[$document['doc_type']])) {
                $errors[$document['doc_type']] = 'This document needs correction. Please upload a new copy.';
            }
        }

        return $errors;
    }

    /** @param list<array<string, mixed>> $questions */
    private function requiredAnswerErrors(int $id, array $questions): array
    {
        $errors = [];
        $answers = $this->answers->forApplication($id);

        foreach ($questions as $question) {
            $questionId = (int) $question['question_id'];

            if ((int) $question['is_required'] === 1 && trim($answers[$questionId] ?? '') === '') {
                $errors["q_{$questionId}"] = 'This question is required.';
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $application */
    private function stepFourQuestions(array $application): array
    {
        $roleType = $application['role_type'] ?? null;

        return $this->questions->forRoleTypes($roleType === null ? ['common'] : ['common', $roleType]);
    }

    /**
     * @param list<array<string, mixed>> $questions
     * @param array<int|string, mixed> $submitted
     */
    private function storeAnswers(int $id, array $questions, array $submitted): void
    {
        foreach ($questions as $question) {
            $questionId = (int) $question['question_id'];

            if ($question['input_type'] === 'file') {
                continue;
            }

            $raw = $submitted[$questionId] ?? '';
            $answer = is_string($raw) ? trim($raw) : '';

            if ($question['input_type'] === 'select' && $answer !== '') {
                $options = json_decode((string) $question['options_json'], true) ?: [];

                if (!in_array($answer, $options, true)) {
                    $answer = '';
                }
            }

            if ($question['input_type'] === 'checkbox') {
                $answer = $answer === '' ? '' : 'Yes';
            }

            $this->answers->put($id, $questionId, mb_substr($answer, 0, self::ANSWER_MAX_LENGTH));
        }
    }

    /** One copy per type. A new upload replaces the old one, and a needs_correction document returns to pending. */
    private function replaceDocument(array $application, string $docType, array $file): array
    {
        $id = (int) $application['application_id'];
        $existing = null;

        foreach ($this->documents->forApplication($id) as $document) {
            if ($document['doc_type'] === $docType && $document['verification_status'] !== 'rejected') {
                $existing = $document;
            }
        }

        if ($existing === null) {
            return $this->addDocument($application, $docType, $file);
        }

        $status = $existing['verification_status'];

        if ($status === 'verified') {
            throw new FileRejectedException('This document is already verified, so it cannot be replaced.');
        }

        if ($status === 'needs_correction') {
            DocumentStatus::assertTransition($status, 'pending');
        }

        $stored = $this->files->store($file, 'applications');
        $values = $stored + ['verification_status' => 'pending', 'verification_remarks' => null, 'verified_by' => null, 'verified_at' => null];
        $this->documents->update((int) $existing['document_id'], $values);
        $this->files->delete($existing['stored_path']);
        AuditService::log('application.document_replaced', 'application_document', (int) $existing['document_id'], ['doc_type' => $docType, 'from' => $status, 'to' => 'pending']);

        return $values + ['document_id' => $existing['document_id'], 'doc_type' => $docType];
    }

    private function addDocument(array $application, string $docType, array $file): array
    {
        $stored = $this->files->store($file, 'applications');
        $documentId = $this->documents->insert($stored + ['application_id' => $application['application_id'], 'doc_type' => $docType]);
        AuditService::log('application.document_uploaded', 'application_document', $documentId, ['doc_type' => $docType]);

        return $stored + ['document_id' => $documentId, 'doc_type' => $docType];
    }

    /**
     * BR-02, plus: a vacancy must be open, an existing member cannot apply for general membership again, and only
     * members can apply for a position.
     *
     * @param array<string, mixed> $application
     */
    /**
     * Joining a committee as a Committee Member: open to people without an account and to members with no position
     * yet. Someone who already holds a position applies for an open position instead, so no role is replaced quietly.
     *
     * @param array<string, mixed> $application
     * @throws DomainException
     */
    private function assertCommitteeChoice(array $application, ?int $committeeId): void
    {
        if ($committeeId === null) {
            return;
        }

        if ($this->committeeMemberPosition($committeeId) === null) {
            throw new DomainException('Please choose one of the committees listed.');
        }

        if ($application['user_id'] !== null) {
            $member = (new MemberRepository())->findByUser((int) $application['user_id']);

            if ($member !== null && $member['position_id'] !== null) {
                throw new DomainException('You already hold a position. To join another committee, apply for one of its open positions.');
            }
        }
    }

    private function assertAllowedChoice(array $application, ?int $vacancyId, ?int $committeeId = null): void
    {
        $id = (int) $application['application_id'];

        if ($application['applicant_id'] !== null) {
            $this->assertApplicantChoice($application, $vacancyId);

            return;
        }

        $userId = $this->userId();

        if ($vacancyId !== null && $this->vacancies->findOpen($vacancyId) === null) {
            throw new DomainException('This position is no longer open. Please choose another.');
        }

        // A member joining a committee: their approved general membership does not count, only another committee
        // application still waiting for a decision (BR-02).
        if ($committeeId !== null && $this->users->hasMemberRecord($userId)) {
            $pending = $this->applications->findPendingCommittee($userId, $id);

            if ($pending !== null) {
                throw new DomainException(sprintf('You already have a committee application waiting for a decision (%s). Only one is allowed.', $pending['tracking_code']));
            }

            return;
        }

        if ($vacancyId === null && $this->users->hasMemberRecord($userId)) {
            throw new DomainException('You are already an AGILE member, so you do not need general membership. You can apply for an open position instead.');
        }

        if ($vacancyId === null) {
            $this->assertNoActiveGeneral($this->users->find($userId), $id);
        }

        $active = $this->applications->findActive($userId, $vacancyId, $id);

        if ($active !== null) {
            $message = $vacancyId === null ? self::ACTIVE_MESSAGE_GENERAL : self::ACTIVE_MESSAGE_VACANCY;

            throw new DomainException(sprintf($message, $active['tracking_code']));
        }
    }

    /** @param array<string, mixed> $person A row from users or applicants. */
    private function assertNoActiveGeneral(array $person, int $exceptId): void
    {
        $active = $this->applications->findActiveGeneral($person['email'], $person['student_no'], $exceptId);

        if ($active !== null) {
            throw new DomainException(sprintf($active['owner_email'] === $person['email'] ? self::ACTIVE_MESSAGE_GENERAL : self::ACTIVE_MESSAGE_STUDENT, $active['tracking_code']));
        }
    }

    /** @param array<string, mixed> $application */
    private function assertApplicantChoice(array $application, ?int $vacancyId): void
    {
        if ($vacancyId !== null) {
            throw new DomainException('Positions are open to AGILE members. Please apply for general membership first.');
        }

        $applicant = $this->applicants->find((int) $application['applicant_id']);
        $this->assertNoActiveGeneral($applicant, (int) $application['application_id']);

        // Their own account, still waiting for approval, may hold the number already.
        $ownAccount = $this->users->findByEmail($applicant['email']);

        if ((string) $applicant['student_no'] !== '' && $this->users->studentNoExists($applicant['student_no']) && ($ownAccount['student_no'] ?? null) !== $applicant['student_no']) {
            throw new DomainException('This student number already has an AGILE account. Please log in instead.');
        }
    }

    /**
     * The only place an application's status changes. Writes the history row every time.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function changeStatus(array $row, string $to, array $values, ?string $remarks): void
    {
        ApplicationStatus::assertTransition($row['status'], $to);

        $id = (int) $row['application_id'];
        $this->applications->update($id, $values + ['status' => $to]);
        $this->applications->addHistory($id, $row['status'], $to, $remarks, current_user_id());
    }

    private function newTrackingCode(): string
    {
        do {
            $code = self::generateTrackingCode();
        } while ($this->applications->trackingCodeExists($code));

        return $code;
    }

    private function authorizeApplicant(): void
    {
        if (current_user_id() === null) {
            if (ApplicantAccessService::current() === null) {
                throw new ForbiddenException('application.applicant_access');
            }

            return;
        }

        authorize('application.submit');

        if ($this->users->find($this->userId())['email_verified_at'] === null) {
            throw new ForbiddenException('application.verified_account');
        }
    }

    private function userId(): int
    {
        return current_user_id() ?? throw new RuntimeException('MembershipService needs a logged-in user.');
    }
}
