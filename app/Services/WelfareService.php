<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\Validator;
use App\Models\CaseFollowupRepository;
use App\Models\CaseNoteRepository;
use App\Models\CaseResolutionRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\ReferralRepository;
use App\Models\UserRepository;
use App\Models\WelfareAttachmentRepository;
use App\Models\WelfareCaseRepository;
use App\Models\WelfareCategoryRepository;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Welfare cases (BR-06, BR-07, BR-15, BR-17): the submitter's side, then the handlers' side. Case text and attachment
 * files are encrypted here before saving and decrypted only here. The history and the audit log never hold their text.
 */
final class WelfareService
{
    public const STATUSES = ['submitted', 'in_progress', 'referred', 'resolved', 'closed'];
    public const SUBJECT_MAX = 150;
    public const DESCRIPTION_MAX = 5000;
    public const MAX_ATTACHMENTS = 5;

    public const CATEGORY_HELP = [
        1 => 'Grades, enrollment, subjects or other school concerns.',
        2 => 'Tuition, fees or other money concerns.',
        3 => 'Stress, anxiety or your emotional well-being.',
        4 => 'A conduct concern or a disciplinary matter.',
        5 => 'Scholarships, grants or financial aid applications.',
        6 => 'Being harassed, bullied or made to feel unsafe.',
        7 => 'Anything else you need help with.',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const REFERRAL_STATUSES = ['pending', 'accepted', 'completed', 'declined'];
    public const DISPOSITIONS = ['kept', 'returned', 'deleted'];
    public const OUTCOMES = [
        'resolved' => 'Resolved',
        'partially_resolved' => 'Partially resolved',
        'referred_out' => 'Referred out',
        'withdrawn' => 'Withdrawn',
    ];

    private const HARASSMENT_CATEGORY = 6;
    private const ACKNOWLEDGE_HOURS = 24;

    /** Section 8. Closed is final (BR-15). */
    private const TRANSITIONS = [
        'submitted' => ['in_progress'],
        'in_progress' => ['referred', 'resolved'],
        'referred' => ['in_progress', 'resolved'],
        'resolved' => ['closed'],
        'closed' => [],
    ];

    public function __construct(
        private readonly WelfareCaseRepository $cases = new WelfareCaseRepository(),
        private readonly WelfareAttachmentRepository $attachments = new WelfareAttachmentRepository(),
        private readonly WelfareCategoryRepository $categories = new WelfareCategoryRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly CaseNoteRepository $notes = new CaseNoteRepository(),
        private readonly ReferralRepository $referrals = new ReferralRepository(),
        private readonly CaseFollowupRepository $followups = new CaseFollowupRepository(),
        private readonly CaseResolutionRepository $resolutions = new CaseResolutionRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Every change to a case must pass here, so nothing can touch a closed one (TC-17).
     *
     * @throws DomainException
     */
    public static function assertCanChange(array $case, ?string $toStatus = null): void
    {
        if ($case['status'] === 'closed') {
            throw new DomainException('This case is closed and cannot be changed. Please submit a new concern.');
        }

        if ($toStatus !== null && !self::canTransition($case['status'], $toStatus)) {
            throw new DomainException("A case cannot move from {$case['status']} to {$toStatus}.");
        }
    }

    /**
     * $_FILES for a multiple file field, as one array per file. Empty slots are dropped.
     *
     * @return list<array<string, mixed>>
     */
    public static function uploadsFrom(mixed $field): array
    {
        if (!is_array($field) || !is_array($field['name'] ?? null)) {
            return [];
        }

        $uploads = [];

        foreach (array_keys($field['name']) as $index) {
            if (($field['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploads[] = [
                    'name' => $field['name'][$index],
                    'type' => $field['type'][$index] ?? '',
                    'tmp_name' => $field['tmp_name'][$index] ?? '',
                    'error' => $field['error'][$index],
                    'size' => $field['size'][$index] ?? 0,
                ];
            }
        }

        return $uploads;
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return $this->categories->all();
    }

    /**
     * @param array{category_id: string, subject: string, description: string} $input
     * @return array<string, string> field => message
     */
    public function concernErrors(array $input): array
    {
        $errors = Validator::check($input, [
            'subject' => ['label' => 'Subject', 'rules' => ['required', 'max:' . self::SUBJECT_MAX]],
            'description' => ['label' => 'Description', 'rules' => ['required', 'max:' . self::DESCRIPTION_MAX]],
        ]);

        if (!ctype_digit($input['category_id']) || $this->categories->find((int) $input['category_id']) === null) {
            $errors = ['category_id' => 'Please choose a category.'] + $errors;
        }

        return $errors;
    }

    /**
     * A logged-in user's concern, Applicants included. $relatedCaseId must be one of their own closed cases.
     *
     * @param array{category_id: string, subject: string, description: string, urgent: bool} $input
     * @param list<array<string, mixed>> $uploads
     * @return array<string, mixed> The new case.
     * @throws FileRejectedException|DomainException
     */
    public function submit(array $input, ?int $relatedCaseId, array $uploads): array
    {
        authorize('welfare.submit');

        $userId = (int) current_user_id();

        if ($relatedCaseId !== null) {
            $related = $this->cases->find($relatedCaseId);

            if ($related === null || (int) $related['user_id'] !== $userId || $related['status'] !== 'closed') {
                throw new DomainException('Please choose one of your closed cases.');
            }
        }

        return $this->create(['user_id' => $userId], $input, $relatedCaseId, $uploads);
    }

    /**
     * A guest's concern (BR-17). The caller has already confirmed the email with a one-time code.
     *
     * @param array{name: string, email: string, student_no: string} $guest
     * @param array{category_id: string, subject: string, description: string, urgent: bool} $input
     * @param list<array<string, mixed>> $uploads
     * @return array<string, mixed> The new case.
     * @throws FileRejectedException|DomainException
     */
    public function submitAsGuest(array $guest, array $input, string $relatedCode, array $uploads): array
    {
        $relatedId = null;

        if ($relatedCode !== '') {
            $related = $this->cases->findForEmail(strtoupper($relatedCode), $guest['email']);

            if ($related === null) {
                throw new DomainException('We could not find that case code for your email.');
            }

            $relatedId = (int) $related['case_id'];
        }

        // A guest's identity is as confidential as the concern (M3): encrypted, with a keyed hash to find their cases.
        $encryption = new EncryptionService();

        return $this->create([
            'guest_name' => $encryption->encrypt($guest['name']),
            'guest_email' => $encryption->encrypt($guest['email']),
            'guest_email_hash' => $encryption->blindIndex($guest['email']),
            'guest_student_no' => $guest['student_no'] === '' ? null : $encryption->encrypt($guest['student_no']),
        ], $input, $relatedId, $uploads);
    }

    /**
     * A guest case's submitter, decrypted. Empty strings for a member's case.
     *
     * @param array<string, mixed> $case A welfare_cases row.
     * @return array{name: string, email: string, student_no: string}
     */
    public function guestOf(array $case): array
    {
        $read = fn (?string $value): string => $value === null ? '' : $this->readable($value, '');

        return ['name' => $read($case['guest_name'] ?? null), 'email' => $read($case['guest_email'] ?? null), 'student_no' => $read($case['guest_student_no'] ?? null)];
    }

    /** Sends to the account, or to the guest's email when the case has no account (BR-17). */
    public function notifySubmitter(array $case, string $templateCode, array $payload): void
    {
        $payload += ['case_code' => $case['case_code'], 'link' => '/track'];

        if ($case['user_id'] === null) {
            $guest = $this->guestOf($case);
            $this->notifications->notifyEmail($guest['email'], $templateCode, $payload + ['name' => $guest['name']]);

            return;
        }

        $this->notifications->notify((int) $case['user_id'], $templateCode, $payload, '/my-welfare-cases/' . (int) $case['case_id']);
    }

    /** My Welfare Cases. Decrypts the subject and leaves everything confidential out. */
    public function myCases(int $page): array
    {
        $result = $this->cases->paginate($page, 20, 'user_id = ?', [(int) current_user_id()]);
        $result['data'] = array_map(fn (array $case): array => [
            'case_id' => $case['case_id'],
            'case_code' => $case['case_code'],
            'subject' => $this->readable($case['subject'], 'Subject unavailable'),
            'status' => $case['status'],
            'submitted_at' => $case['submitted_at'],
            'updated_at' => $case['updated_at'],
            'closed_at' => $case['closed_at'],
        ], $result['data']);

        return $result;
    }

    /** @return list<array{case_id: int, label: string}> For the "related case" choice on the form. */
    public function myClosedCases(): array
    {
        return array_map(fn (array $case): array => [
            'case_id' => (int) $case['case_id'],
            'label' => $case['case_code'] . ': ' . $this->readable($case['subject'], 'Subject unavailable'),
        ], $this->cases->closedForUser((int) current_user_id()));
    }

    /**
     * The owner's view: status, status changes, student-visible notes and their files. Nothing about handling.
     * Every view is logged in both places (section 11).
     *
     * @return array<string, mixed>|null
     * @throws ForbiddenException for someone else's case
     */
    public function openOwn(int $id): ?array
    {
        $case = $this->cases->find($id);

        if ($case === null) {
            return null;
        }

        if ((int) $case['user_id'] !== current_user_id()) {
            throw new ForbiddenException('welfare.own_case');
        }

        $this->cases->recordView($id, current_user_id(), 'submitter');
        AuditService::log('welfare.viewed', 'welfare_case', $id);

        return [
            'case' => [
                'case_id' => $case['case_id'],
                'case_code' => $case['case_code'],
                'subject' => $this->readable($case['subject'], 'Subject unavailable'),
                'status' => $case['status'],
                'submitted_at' => $case['submitted_at'],
            ],
            'timeline' => $this->timeline($id),
            'attachments' => $this->attachmentsFor($id),
        ];
    }

    /**
     * Status changes and student-visible notes, oldest first. For the owner and for public tracking.
     *
     * @return list<array{time: string, status: ?string, note: ?string}>
     */
    public function timeline(int $id): array
    {
        $timeline = array_map(
            static fn (array $row): array => ['time' => $row['created_at'], 'status' => $row['new_value'], 'note' => null],
            $this->cases->statusChanges($id)
        );

        foreach ($this->cases->studentVisibleNotes($id) as $note) {
            $timeline[] = ['time' => $note['created_at'], 'status' => null, 'note' => $this->readable($note['note'], 'An update was added to your case.')];
        }

        usort($timeline, static fn (array $a, array $b): int => $a['time'] <=> $b['time']);

        return $timeline;
    }

    /**
     * The submitting user and those with welfare.view. Anyone else, guests included, gets a 403 whether or not it exists.
     *
     * @return array{contents: string, download_name: string, inline: bool, log: bool}|null
     */
    public function openAttachment(int $id): ?array
    {
        $attachment = $this->attachments->findWithOwner($id);
        $isOwner = $attachment !== null && $attachment['owner_id'] !== null && (int) $attachment['owner_id'] === current_user_id();

        if (!$isOwner && !can('welfare.view')) {
            throw new ForbiddenException('file.welfare_attachment');
        }

        if ($attachment === null || $attachment['disposition'] === 'deleted') {
            return null;
        }

        $path = $this->files->path($attachment['stored_path']);

        if (!is_file($path)) {
            return null;
        }

        $this->cases->recordView((int) $attachment['case_id'], current_user_id(), 'attachment');

        return [
            'contents' => (new EncryptionService())->decrypt((string) file_get_contents($path)),
            'mime_type' => $attachment['mime_type'],
            'download_name' => $this->readable($attachment['original_name'], 'attachment'),
            'inline' => false,
            'log' => true,
        ];
    }

    /**
     * Gives a newly verified account its earlier guest cases. Verifying the email proved it is theirs.
     * Called while verifying an email, so it needs no permission.
     */
    public function linkGuestCases(int $userId, string $email): int
    {
        $linked = 0;

        foreach ($this->cases->guestCaseIds($email) as $caseId) {
            $this->cases->update($caseId, ['user_id' => $userId]);
            $this->cases->addHistory($caseId, 'linked_to_account', null, null, $userId);
            AuditService::log('welfare.linked_to_account', 'welfare_case', $caseId, ['user_id' => $userId]);
            $linked++;
        }

        return $linked;
    }

    /** A rejected account gives back the guest concerns it took over, so they stay trackable with the same email. */
    public function unlinkGuestCases(int $userId): void
    {
        foreach ($this->cases->findBy(['user_id' => $userId]) as $case) {
            if ($case['guest_email'] !== null) {
                $this->cases->update((int) $case['case_id'], ['user_id' => null]);
                $this->cases->addHistory((int) $case['case_id'], 'linked_to_account', (string) $userId, null, null);
            }
        }
    }

    /* ---------- The handlers' side (BR-06). Every method re-checks permission on top of the route. ---------- */

    /**
     * The queue. The subject is encrypted, so a search decrypts it in memory for those allowed to read it.
     *
     * @param array{status?: string, category_id?: int, priority?: string, assigned?: string, from?: string, to?: string, q?: string} $filters
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function queue(array $filters, int $page, int $perPage = 20): array
    {
        authorize('welfare.view');

        $query = $filters;
        unset($query['q'], $query['assigned']);

        if (($filters['assigned'] ?? null) === 'me') {
            $query['assigned_to'] = (int) current_user_id();
        } elseif (($filters['assigned'] ?? null) === 'unassigned') {
            $query['unassigned'] = true;
        }

        $rows = array_map(function (array $case): array {
            $case['subject'] = $this->readable($case['subject'], 'Subject unavailable');
            $case['overdue'] = self::isOverdue($case);

            return $case;
        }, $this->cases->forQueue($query));

        if (isset($filters['q'])) {
            $search = $filters['q'];
            $rows = array_values(array_filter($rows, static fn (array $case): bool => mb_stripos($case['case_code'], $search) !== false || mb_stripos($case['subject'], $search) !== false));
        }

        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        return ['data' => array_slice($rows, ($page - 1) * $perPage, $perPage), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }

    /** BR-07. */
    public static function isOverdue(array $case): bool
    {
        $isUrgent = $case['priority'] === 'urgent' || (int) $case['student_marked_urgent'] === 1 || (int) $case['category_id'] === self::HARASSMENT_CATEGORY;

        return $isUrgent
            && $case['acknowledged_at'] === null
            && $case['status'] !== 'closed'
            && strtotime($case['submitted_at']) <= time() - self::ACKNOWLEDGE_HOURS * 3600;
    }

    /**
     * Opens a case and decrypts it. Every open is logged in both places (section 11).
     *
     * @return array<string, mixed>|null
     */
    public function openCase(int $id): ?array
    {
        authorize('welfare.view');

        $case = $this->cases->findDetailed($id);

        if ($case === null) {
            return null;
        }

        $this->cases->recordView($id, current_user_id(), 'workspace');
        AuditService::log('welfare.viewed', 'welfare_case', $id);

        $case['subject'] = $this->readable($case['subject'], 'Subject unavailable');
        $case['description'] = $this->readable($case['description'], 'Description unavailable');
        $guest = $this->guestOf($case);
        $case['guest_name'] = $guest['name'];
        $case['guest_email'] = $guest['email'];
        $case['guest_student_no'] = $guest['student_no'] === '' ? null : $guest['student_no'];
        $case['closing_remarks'] = $case['closing_remarks'] === null ? null : $this->readable($case['closing_remarks'], 'Remarks unavailable');
        $case['overdue'] = self::isOverdue($case);
        $resolution = $this->resolutions->forCase($id);

        if ($resolution !== null) {
            $resolution['actions_taken'] = $this->readable($resolution['actions_taken'], 'Unavailable');
            $resolution['summary'] = $this->readable($resolution['summary'], 'Unavailable');
        }

        return [
            'case' => $case,
            'notes' => array_map(fn (array $note): array => ['note' => $this->readable($note['note'], 'This note cannot be read.')] + $note, $this->notes->forCase($id)),
            'referrals' => array_map(fn (array $referral): array => ['referred_to' => $this->readable($referral['referred_to'], 'This name cannot be read.'), 'remarks' => $this->readable($referral['remarks'], 'These remarks cannot be read.')] + $referral, $this->referrals->forCase($id)),
            'followups' => array_map(fn (array $followup): array => ['remarks' => $followup['remarks'] === null ? null : $this->readable($followup['remarks'], 'These remarks cannot be read.')] + $followup, $this->followups->forCase($id)),
            'resolution' => $resolution,
            'attachments' => $this->attachmentsFor($id),
            'history' => $this->cases->history($id),
            'handlers' => $this->users->activeWelfareHandlers(),
            'categories' => $this->categories->all(),
        ];
    }

    public function acknowledge(int $id): void
    {
        authorize('welfare.handle');

        Database::transaction(function () use ($id): void {
            $case = $this->lockCase($id);
            self::assertCanChange($case);

            if ($case['acknowledged_at'] !== null) {
                throw new DomainException('This case was already acknowledged.');
            }

            $this->cases->update($id, ['acknowledged_at' => date('Y-m-d H:i:s')]);
            $this->record($id, 'acknowledged', null, null, 'welfare.acknowledged');
        });
    }

    /** The remarks are kept as an encrypted internal note, because history rows hold no free text. */
    public function classify(int $id, int $categoryId, string $priority, string $remarks): void
    {
        authorize('welfare.assign_close');
        $this->requireText($remarks, 'Remarks are required.');

        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new DomainException('Please choose a priority.');
        }

        $category = $this->categories->find($categoryId) ?? throw new DomainException('Please choose a category.');

        Database::transaction(function () use ($id, $category, $categoryId, $priority, $remarks): void {
            $case = $this->lockCase($id);
            self::assertCanChange($case);

            $categoryChanged = (int) $case['category_id'] !== $categoryId;
            $priorityChanged = $case['priority'] !== $priority;

            if (!$categoryChanged && !$priorityChanged) {
                throw new DomainException('Change the category or the priority first.');
            }

            $this->cases->update($id, ['category_id' => $categoryId, 'priority' => $priority]);
            $this->insertNote($id, 'Classification changed: ' . trim($remarks), false);

            if ($categoryChanged) {
                $this->record($id, 'category_change', $this->categories->find((int) $case['category_id'])['name'], $category['name'], 'welfare.category_changed');
            }

            if ($priorityChanged) {
                $this->record($id, 'priority_change', $case['priority'], $priority, 'welfare.priority_changed');
            }
        });
    }

    /** BR-07: the MSW Committee Head themself or a President. */
    public function assign(int $id, int $handlerId): void
    {
        authorize('welfare.assign_close');

        Database::transaction(function () use ($id, $handlerId): void {
            $case = $this->lockCase($id);
            self::assertCanChange($case);
            $handler = null;

            foreach ($this->users->activeWelfareHandlers() as $candidate) {
                if ((int) $candidate['user_id'] === $handlerId && ($handlerId === current_user_id() || $candidate['role_slug'] === 'president')) {
                    $handler = $candidate;
                }
            }

            if ($handler === null) {
                throw new DomainException('A case can be assigned to you or to a President.');
            }

            $old = $case['assigned_to'] === null ? null : $this->users->find((int) $case['assigned_to']);

            if ($old !== null && (int) $old['user_id'] === $handlerId) {
                throw new DomainException('This case is already assigned to that person.');
            }

            $this->cases->update($id, ['assigned_to' => $handlerId]);
            $this->record($id, 'assigned', $old === null ? null : $old['first_name'] . ' ' . $old['last_name'], $handler['first_name'] . ' ' . $handler['last_name'], 'welfare.assigned');
        });
    }

    /** The first note starts handling. Only a note marked visible reaches the submitter. */
    public function addNote(int $id, string $note, bool $visible): void
    {
        authorize('welfare.handle');
        $this->requireText($note, 'Please write the note.');

        Database::transaction(function () use ($id, $note, $visible): void {
            $case = $this->lockCase($id);
            self::assertCanChange($case);

            $this->insertNote($id, trim($note), $visible);
            $this->record($id, 'note_added', null, $visible ? 'visible_to_student' : 'internal', 'welfare.note_added');

            $status = $case['status'] === 'submitted' ? $this->move($case, 'in_progress') : $case['status'];

            if ($visible) {
                $this->notifySubmitter($case, 'WELFARE_UPDATE', ['status' => $this->statusLabel($status)]);
            }
        });
    }

    public function addReferral(int $id, string $referredTo, string $remarks): void
    {
        authorize('welfare.handle');
        $this->requireText($referredTo, 'Please say who the case is referred to.');
        $this->requireText($remarks, 'Remarks are required.');

        Database::transaction(function () use ($id, $referredTo, $remarks): void {
            $case = $this->lockCase($id);
            self::assertCanChange($case);

            if ($case['status'] === 'resolved') {
                throw new DomainException('A resolved case cannot be referred.');
            }

            $referralId = $this->referrals->insert([
                'case_id' => $id,
                'referred_to' => (new EncryptionService())->encrypt(trim($referredTo)),
                'remarks' => (new EncryptionService())->encrypt(trim($remarks)),
                'status' => 'pending',
                'referred_by' => current_user_id(),
                'referred_at' => date('Y-m-d H:i:s'),
            ]);
            $this->record($id, 'referral', null, 'pending', 'welfare.referral_added', ['referral_id' => $referralId]);

            if ($case['status'] !== 'referred') {
                $this->moveThroughProgress($case, 'referred');
            }
        });
    }

    public function updateReferral(int $id, int $referralId, string $status): void
    {
        authorize('welfare.handle');

        if (!in_array($status, self::REFERRAL_STATUSES, true)) {
            throw new DomainException('Please choose a status.');
        }

        Database::transaction(function () use ($id, $referralId, $status): void {
            self::assertCanChange($this->lockCase($id));
            $referral = $this->referrals->find($referralId);

            if ($referral === null || (int) $referral['case_id'] !== $id) {
                throw new DomainException('This referral no longer exists.');
            }

            if ($referral['status'] === $status) {
                throw new DomainException('The referral already has that status.');
            }

            $this->referrals->update($referralId, ['status' => $status]);
            $this->record($id, 'referral', $referral['status'], $status, 'welfare.referral_updated', ['referral_id' => $referralId]);
        });
    }

    /** $dueDate is Y-m-d. A follow-up is due until the end of that day. */
    public function addFollowup(int $id, string $dueDate, int $handlerId): void
    {
        authorize('welfare.handle');

        if ($dueDate < date('Y-m-d')) {
            throw new DomainException('The due date cannot be in the past.');
        }

        Database::transaction(function () use ($id, $dueDate, $handlerId): void {
            self::assertCanChange($this->lockCase($id));

            if (!in_array($handlerId, array_map(static fn (array $user): int => (int) $user['user_id'], $this->users->activeWelfareHandlers()), true)) {
                throw new DomainException('Please choose a handler from the list.');
            }

            $followupId = $this->followups->insert(['case_id' => $id, 'due_at' => $dueDate . ' 23:59:59', 'handler_id' => $handlerId]);
            $this->record($id, 'followup', null, 'due ' . $dueDate, 'welfare.followup_added', ['followup_id' => $followupId]);
        });
    }

    public function completeFollowup(int $id, int $followupId, string $remarks): void
    {
        authorize('welfare.handle');
        $this->requireText($remarks, 'Remarks are required.');

        Database::transaction(function () use ($id, $followupId, $remarks): void {
            self::assertCanChange($this->lockCase($id));
            $followup = $this->followups->find($followupId);

            if ($followup === null || (int) $followup['case_id'] !== $id) {
                throw new DomainException('This follow-up no longer exists.');
            }

            if ($followup['done_at'] !== null) {
                throw new DomainException('This follow-up was already completed.');
            }

            $this->followups->update($followupId, ['done_at' => date('Y-m-d H:i:s'), 'remarks' => (new EncryptionService())->encrypt(trim($remarks))]);
            $this->record($id, 'followup', null, 'completed', 'welfare.followup_completed', ['followup_id' => $followupId]);
        });
    }

    public function resolve(int $id, string $actionsTaken, string $outcome, string $summary): void
    {
        authorize('welfare.assign_close');
        $this->requireText($actionsTaken, 'Please describe the actions taken.');
        $this->requireText($summary, 'Please write a summary.');

        if (!isset(self::OUTCOMES[$outcome])) {
            throw new DomainException('Please choose an outcome.');
        }

        Database::transaction(function () use ($id, $actionsTaken, $outcome, $summary): void {
            $case = $this->lockCase($id);
            self::assertCanChange($case);

            if ($case['status'] === 'resolved') {
                throw new DomainException('This case was already resolved.');
            }

            $encryption = new EncryptionService();
            $this->resolutions->insert([
                'case_id' => $id,
                'actions_taken' => $encryption->encrypt(trim($actionsTaken)),
                'outcome' => $outcome,
                'summary' => $encryption->encrypt(trim($summary)),
                'resolved_by' => current_user_id(),
                'resolved_at' => date('Y-m-d H:i:s'),
            ]);
            $this->record($id, 'resolved', null, $outcome, 'welfare.resolved');
            $this->moveThroughProgress($case, 'resolved');
        });
    }

    /** TC-18. Only from resolved. Closed is final, and attachments can then only get a disposition. */
    public function close(int $id, string $remarks): void
    {
        authorize('welfare.assign_close');
        $this->requireText($remarks, 'Closing remarks are required.');

        Database::transaction(function () use ($id, $remarks): void {
            $case = $this->lockCase($id);
            self::assertCanChange($case, 'closed');

            $this->cases->update($id, [
                'closing_remarks' => (new EncryptionService())->encrypt(trim($remarks)),
                'closed_by' => current_user_id(),
                'closed_at' => date('Y-m-d H:i:s'),
            ]);
            $this->move($case, 'closed');
            $this->record($id, 'closed', null, null, 'welfare.closed');
            $this->notifySubmitter($case, 'WELFARE_CLOSED', []);
        });
    }

    /** BR-15. Once only, after closure. Deleting removes the file and keeps the record. */
    public function setDisposition(int $id, int $attachmentId, string $disposition): void
    {
        authorize('welfare.assign_close');

        if (!in_array($disposition, self::DISPOSITIONS, true)) {
            throw new DomainException('Please choose kept, returned or deleted.');
        }

        $storedPath = Database::transaction(function () use ($id, $attachmentId, $disposition): string {
            $case = $this->lockCase($id);

            if ($case['status'] !== 'closed') {
                throw new DomainException('A disposition is recorded after the case is closed.');
            }

            $attachment = $this->attachments->find($attachmentId);

            if ($attachment === null || (int) $attachment['case_id'] !== $id) {
                throw new DomainException('This attachment no longer exists.');
            }

            if ($attachment['disposition'] !== 'open') {
                throw new DomainException('This attachment already has a disposition.');
            }

            $this->attachments->update($attachmentId, ['disposition' => $disposition, 'disposition_by' => current_user_id(), 'disposition_at' => date('Y-m-d H:i:s')]);
            $this->record($id, 'disposition', 'open', $disposition, 'welfare.attachment_disposition', ['attachment_id' => $attachmentId]);

            return $attachment['stored_path'];
        });

        // After the commit, so a failure above cannot lose a file the record still says is there.
        if ($disposition === 'deleted') {
            $this->files->delete($storedPath);
        }
    }

    /**
     * Runs from jobs/welfare_followups.php with nobody signed in, so it has no permission check. Controllers must not
     * call it. reminder_sent_at is saved with the notification, so a second run sends nothing.
     *
     * @param callable(string): void $log
     * @return array{sent: int, failed: int}
     */
    public function sendFollowupReminders(callable $log): array
    {
        return $this->eachInItsOwnTransaction($this->followups->dueForReminder(date('Y-m-d 23:59:59')), $log, 'Follow-up', function (int $id): bool {
            $followup = $this->followups->lockForReminder($id);

            if ($followup === null || $followup['reminder_sent_at'] !== null || $followup['done_at'] !== null) {
                return false;
            }

            $this->notifications->notify((int) $followup['handler_id'], 'WELFARE_FOLLOWUP_REMINDER', [
                'case_code' => $followup['case_code'],
                'due_at' => date('F j, Y', strtotime($followup['due_at'])),
            ], '/welfare/' . (int) $followup['case_id']);
            $this->followups->update($id, ['reminder_sent_at' => date('Y-m-d H:i:s')]);
            AuditService::log('welfare.followup_reminder_sent', 'welfare_case', (int) $followup['case_id'], ['followup_id' => $id]);

            return true;
        });
    }

    /**
     * BR-07, hourly. welfare_cases has no reminder column, so a history row marks the case as reminded.
     * Runs with nobody signed in, like sendFollowupReminders().
     *
     * @param callable(string): void $log
     * @return array{sent: int, failed: int}
     */
    public function sendOverdueAlerts(callable $log): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::ACKNOWLEDGE_HOURS * 3600);

        return $this->eachInItsOwnTransaction($this->cases->overdueAcknowledgementIds($cutoff), $log, 'Case', function (int $id): bool {
            $case = $this->lockCase($id);

            if ($case['acknowledged_at'] !== null || $case['status'] === 'closed' || $this->cases->hasEvent($id, 'overdue_reminder')) {
                return false;
            }

            $this->sendAlertToHandlers($case);
            $this->cases->addHistory($id, 'overdue_reminder', null, null, null);
            AuditService::log('welfare.overdue_reminder_sent', 'welfare_case', $id);

            return true;
        });
    }

    /**
     * One bad row must not stop the rest, and it is picked up again on the next run.
     *
     * @param list<int> $ids
     * @param callable(string): void $log
     * @return array{sent: int, failed: int}
     */
    private function eachInItsOwnTransaction(array $ids, callable $log, string $label, callable $remind): array
    {
        $counts = ['sent' => 0, 'failed' => 0];

        foreach ($ids as $id) {
            try {
                if (Database::transaction(static fn (): bool => $remind($id))) {
                    $counts['sent']++;
                    $log("Reminder queued: {$label} {$id}.");
                }
            } catch (Throwable $exception) {
                $counts['failed']++;
                $log(sprintf('%s %d failed with %s: %s', $label, $id, $exception::class, $exception->getMessage()));
            }
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    private function lockCase(int $id): array
    {
        return $this->cases->lockAndFind($id) ?? throw new DomainException('This case no longer exists.');
    }

    private function requireText(string $text, string $message): void
    {
        if (trim($text) === '') {
            throw new DomainException($message);
        }
    }

    private function insertNote(int $caseId, string $note, bool $visible): void
    {
        $this->notes->insert([
            'case_id' => $caseId,
            'author_id' => current_user_id(),
            'note' => (new EncryptionService())->encrypt($note),
            'visible_to_student' => $visible ? 1 : 0,
        ]);
    }

    /**
     * The one place a handler's action is logged: a history row and an audit row, never with the case's text.
     *
     * @param array<string, mixed> $details
     */
    private function record(int $id, string $event, ?string $old, ?string $new, string $action, array $details = []): void
    {
        $this->cases->addHistory($id, $event, $old, $new, current_user_id());
        AuditService::log($action, 'welfare_case', $id, $details);
    }

    /** The only place a case's status changes after it is submitted. Returns the new status. */
    private function move(array $case, string $to): string
    {
        self::assertCanChange($case, $to);

        $id = (int) $case['case_id'];
        $this->cases->update($id, ['status' => $to]);
        $this->record($id, 'status_change', $case['status'], $to, 'welfare.status_changed', ['from' => $case['status'], 'to' => $to]);

        return $to;
    }

    /** Section 8 reaches referred and resolved only through in_progress, so a new case passes through it. */
    private function moveThroughProgress(array $case, string $to): void
    {
        if ($case['status'] === 'submitted') {
            $case['status'] = $this->move($case, 'in_progress');
        }

        $this->move($case, $to);
    }

    private function statusLabel(string $status): string
    {
        return config("statuses.{$status}")[0] ?? $status;
    }

    /**
     * @param array<string, mixed> $owner Either user_id, or the guest columns.
     * @param array{category_id: string, subject: string, description: string, urgent: bool} $input
     * @param list<array<string, mixed>> $uploads
     * @return array<string, mixed>
     */
    private function create(array $owner, array $input, ?int $relatedId, array $uploads): array
    {
        $errors = $this->concernErrors($input);

        if ($errors !== []) {
            throw new DomainException((string) reset($errors));
        }

        if (count($uploads) > self::MAX_ATTACHMENTS) {
            throw new FileRejectedException('Please attach up to ' . self::MAX_ATTACHMENTS . ' files.');
        }

        $checked = array_map(fn (array $upload): array => $this->files->validate($upload) + ['tmp_name' => $upload['tmp_name']], $uploads);
        $encryption = new EncryptionService();

        return Database::transaction(function () use ($owner, $input, $relatedId, $checked, $encryption): array {
            $isUrgent = $input['urgent'];
            $categoryId = (int) $input['category_id'];
            $id = $this->cases->insert($owner + [
                'case_code' => $this->newCaseCode(),
                'category_id' => $categoryId,
                // A suggestion only. The final priority is the MSW Committee Head's.
                'priority' => $isUrgent ? 'high' : 'medium',
                'student_marked_urgent' => $isUrgent ? 1 : 0,
                'subject' => $encryption->encrypt($input['subject']),
                'description' => $encryption->encrypt($input['description']),
                'related_case_id' => $relatedId,
                'status' => 'submitted',
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);
            $case = $this->cases->find($id);

            foreach ($checked as $file) {
                $this->saveAttachment($id, $file, $encryption);
            }

            // A status change, so that tracking's timeline starts with it.
            $this->cases->addHistory($id, 'status_change', null, 'submitted', current_user_id());
            AuditService::log('welfare.submitted', 'welfare_case', $id, [
                'category_id' => $categoryId,
                'marked_urgent' => $isUrgent,
                'guest' => !isset($owner['user_id']),
                'attachments' => count($checked),
            ]);

            $this->notifySubmitter($case, 'WELFARE_RECEIVED', []);

            if ($isUrgent || $categoryId === self::HARASSMENT_CATEGORY) {
                $this->sendUrgentAlert($case);
            }

            return $case;
        });
    }

    /** TC-14. Holds only the case code and a link, never what the concern says. */
    private function sendUrgentAlert(array $case): void
    {
        $id = (int) $case['case_id'];

        $this->sendAlertToHandlers($case);
        AuditService::log('welfare.urgent_alert_sent', 'welfare_case', $id);
    }

    private function sendAlertToHandlers(array $case): void
    {
        foreach (['msw_head', 'president'] as $role) {
            $this->notifications->notifyRole($role, 'WELFARE_URGENT_ALERT', ['case_code' => $case['case_code']], '/welfare/' . (int) $case['case_id']);
        }
    }

    /** @param array<string, mixed> $file A result of FileStorageService::validate() with tmp_name. */
    private function saveAttachment(int $caseId, array $file, EncryptionService $encryption): void
    {
        $contents = file_get_contents($file['tmp_name']);

        if ($contents === false) {
            throw new RuntimeException('Could not read an uploaded file.');
        }

        // The extension says nothing about the contents, and the real type is kept in mime_type.
        $storedPath = $this->files->storeContents($encryption->encrypt($contents), 'welfare', 'enc');
        Database::onRollback(fn () => $this->files->delete($storedPath));

        $this->attachments->insert([
            'case_id' => $caseId,
            'original_name' => $encryption->encrypt($file['original_name']),
            'stored_path' => $storedPath,
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
        ]);
    }

    /** Case codes share the tracking page with application codes, so neither table may already hold it. */
    private function newCaseCode(): string
    {
        $applications = new MembershipApplicationRepository();

        do {
            $code = MembershipService::generateTrackingCode();
        } while ($this->cases->codeExists($code) || $applications->trackingCodeExists($code));

        return $code;
    }

    /** @return list<array<string, mixed>> With file names decrypted. */
    private function attachmentsFor(int $caseId): array
    {
        return array_map(
            fn (array $attachment): array => ['original_name' => $this->readable($attachment['original_name'], 'Attachment')] + $attachment,
            $this->attachments->forCase($caseId),
        );
    }

    /** An unreadable value must not break the page or show ciphertext. */
    private function readable(string $encrypted, string $fallback): string
    {
        try {
            return (new EncryptionService())->decrypt($encrypted);
        } catch (RuntimeException) {
            return $fallback;
        }
    }
}
