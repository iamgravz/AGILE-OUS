<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\AcademicTermRepository;
use App\Models\EligibilityDocumentRepository;
use App\Models\EligibilityReviewRepository;
use App\Models\EligibilityScreeningRepository;
use App\Models\MemberRepository;
use App\Models\NotificationRepository;
use App\Models\SettingRepository;
use DomainException;
use Throwable;

/**
 * Semestral eligibility screening (BR-08 to BR-10). The MSW Committee Head reviews everyone except an MSW Head,
 * whose screening goes to the President (BR-10). Nobody reviews their own.
 */
final class EligibilityService
{
    public const STATUSES = ['pending', 'submitted', 'eligible', 'flagged', 'incomplete', 'confirmed_ineligible', 'cleared'];
    public const SEMESTERS = ['1st' => '1st semester', '2nd' => '2nd semester', 'summer' => 'Summer'];
    public const MAX_DOCUMENTS = 5;

    /** Section 8. */
    private const TRANSITIONS = [
        'pending' => ['submitted', 'incomplete'],
        'submitted' => ['eligible', 'flagged'],
        'incomplete' => ['flagged'],
        'flagged' => ['confirmed_ineligible', 'cleared'],
        'eligible' => [],
        'confirmed_ineligible' => [],
        'cleared' => [],
    ];

    private const REMINDER_DAYS = [7, 1];

    public function __construct(
        private readonly AcademicTermRepository $terms = new AcademicTermRepository(),
        private readonly EligibilityScreeningRepository $screenings = new EligibilityScreeningRepository(),
        private readonly EligibilityDocumentRepository $documents = new EligibilityDocumentRepository(),
        private readonly EligibilityReviewRepository $reviews = new EligibilityReviewRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly FileStorageService $files = new FileStorageService(),
    ) {
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** BR-09: no failed subjects and officially enrolled for the whole semester. */
    public static function resultFor(bool $hasFailedSubject, bool $isOfficiallyEnrolled): string
    {
        return !$hasFailedSubject && $isOfficiallyEnrolled ? 'eligible' : 'flagged';
    }

    public static function termLabel(array $term): string
    {
        return $term['academic_year'] . ', ' . self::SEMESTERS[$term['semester']];
    }

    /** The last day an officer may still submit. */
    public static function lastUploadDate(array $term): string
    {
        return date('Y-m-d', strtotime($term['screening_deadline'] . ' +' . (int) $term['grace_days'] . ' days'));
    }

    /**
     * @param array{academic_year: string, semester: string, screening_deadline: string, grace_days: string} $data
     * @return array<string, string> field => message
     */
    public static function termErrors(array $data): array
    {
        $errors = [];

        if (preg_match('/^(\d{4})-(\d{4})$/', $data['academic_year'], $years) !== 1 || (int) $years[2] !== (int) $years[1] + 1) {
            $errors['academic_year'] = 'Enter the academic year like 2026-2027.';
        }

        if (!isset(self::SEMESTERS[$data['semester']])) {
            $errors['semester'] = 'Please choose a semester.';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['screening_deadline']);

        if ($date === false || $date->format('Y-m-d') !== $data['screening_deadline']) {
            $errors['screening_deadline'] = 'Enter a valid deadline.';
        }

        if (!ctype_digit($data['grace_days']) || (int) $data['grace_days'] > 60) {
            $errors['grace_days'] = 'Enter 0 to 60 days.';
        }

        return $errors;
    }

    /** @return list<array<string, mixed>> */
    public function terms(): array
    {
        $this->authorizeReviewer();

        return $this->terms->allNewestFirst();
    }

    /** @return array<string, mixed>|null */
    public function currentTerm(): ?array
    {
        return $this->terms->current();
    }

    public function defaultGraceDays(): int
    {
        return (int) (new SettingRepository())->value('eligibility.grace_days', '7');
    }

    /** @param array{academic_year: string, semester: string, screening_deadline: string, grace_days: string} $data */
    public function createTerm(array $data, bool $makeCurrent): int
    {
        authorize('eligibility.manage');

        foreach (self::termErrors($data) as $message) {
            throw new DomainException($message);
        }

        return Database::transaction(function () use ($data, $makeCurrent): int {
            if ($this->terms->findBy(['academic_year' => $data['academic_year'], 'semester' => $data['semester']]) !== []) {
                throw new DomainException('That term already exists.');
            }

            $id = $this->terms->insert([
                'academic_year' => $data['academic_year'],
                'semester' => $data['semester'],
                'screening_deadline' => $data['screening_deadline'],
                'grace_days' => (int) $data['grace_days'],
                'is_current' => 0,
            ]);
            AuditService::log('eligibility.term_created', 'academic_term', $id);

            if ($makeCurrent) {
                $this->terms->makeCurrent($id);
                AuditService::log('eligibility.term_made_current', 'academic_term', $id);
            }

            return $id;
        });
    }

    public function setCurrentTerm(int $id): void
    {
        authorize('eligibility.manage');

        Database::transaction(function () use ($id): void {
            if ($this->terms->find($id) === null) {
                throw new DomainException('That term no longer exists.');
            }

            $this->terms->makeCurrent($id);
            AuditService::log('eligibility.term_made_current', 'academic_term', $id);
        });
    }

    /** @return list<array<string, mixed>> Covered officers (BR-08) who have no screening row for this term yet. */
    public function coveredNotYetStarted(int $termId): array
    {
        authorize('eligibility.manage');

        return $this->screenings->coveredWithoutScreening($termId);
    }

    /**
     * Creates a pending row per covered officer and asks each for documents. Running it again adds only officers
     * appointed since, and nobody is asked twice.
     *
     * @return int How many were added.
     */
    public function startScreening(): int
    {
        authorize('eligibility.manage');

        return Database::transaction(function (): int {
            $term = $this->terms->current() ?? throw new DomainException('Please set a current term first.');
            $termId = (int) $term['term_id'];
            $covered = $this->screenings->coveredWithoutScreening($termId);

            if ($covered === []) {
                throw new DomainException('Every covered officer already has a screening for this term.');
            }

            foreach ($covered as $member) {
                $this->screenings->insert(['term_id' => $termId, 'member_id' => (int) $member['member_id'], 'result' => 'pending']);
                $this->sendRequest((int) $member['user_id'], $term);
            }

            AuditService::log('eligibility.screening_started', 'academic_term', $termId, ['count' => count($covered)]);

            return count($covered);
        });
    }

    /**
     * The signed-in user's screening for the current term, or null when they have none.
     *
     * @return array<string, mixed>|null
     */
    public function myScreening(): ?array
    {
        $term = $this->terms->current();
        $member = (new MemberRepository())->findByUser((int) current_user_id());

        if ($term === null || $member === null) {
            return null;
        }

        $screening = $this->screenings->findFor((int) $term['term_id'], (int) $member['member_id']);

        if ($screening === null) {
            return null;
        }

        return [
            'term' => $term,
            'screening' => $screening,
            'documents' => $this->documents->forScreening((int) $screening['screening_id']),
            'lastUploadDate' => self::lastUploadDate($term),
            'canUpload' => $this->canUpload($screening, $term),
        ];
    }

    /**
     * pending -> submitted. Uploading again before it is verified replaces the earlier files.
     *
     * @param list<array<string, mixed>> $uploads
     * @throws FileRejectedException|DomainException
     */
    public function submitDocuments(array $uploads): void
    {
        $mine = $this->myScreening() ?? throw new ForbiddenException('eligibility.own_screening');

        if ($uploads === []) {
            throw new FileRejectedException('Please choose at least one file.');
        }

        if (count($uploads) > self::MAX_DOCUMENTS) {
            throw new FileRejectedException('Please upload up to ' . self::MAX_DOCUMENTS . ' files.');
        }

        foreach ($uploads as $upload) {
            $this->files->validate($upload);
        }

        $id = (int) $mine['screening']['screening_id'];
        $old = [];

        Database::transaction(function () use ($id, $mine, $uploads, &$old): void {
            $screening = $this->screenings->lockAndFind($id);

            if (!$this->canUpload($screening, $mine['term'])) {
                throw new DomainException('Documents can no longer be submitted for this screening.');
            }

            $old = array_column($this->documents->forScreening($id), 'stored_path');
            $this->documents->deleteForScreening($id);

            foreach ($uploads as $upload) {
                $stored = $this->files->store($upload, 'eligibility');
                Database::onRollback(fn () => $this->files->delete($stored['stored_path']));
                $this->documents->insert($stored + ['screening_id' => $id]);
            }

            if ($screening['result'] === 'pending') {
                $this->move($screening, 'submitted');
            }

            AuditService::log('eligibility.documents_submitted', 'eligibility_screening', $id, ['files' => count($uploads), 'replaced' => count($old)]);
        });

        foreach ($old as $path) {
            $this->files->delete($path);
        }
    }

    /**
     * The MSW Committee Head sees every screening. The President sees only those of an MSW Head.
     *
     * @param array{term_id: int, result?: string} $filters
     */
    public function reviewList(array $filters, int $page): array
    {
        $this->authorizeReviewer();

        if (!can('eligibility.manage')) {
            $filters['owner_role'] = 'msw_head';
        }

        $result = $this->screenings->search($filters, $page);
        $result['data'] = array_map(fn (array $screening): array => $screening + ['can_review' => $this->isReviewerFor($screening)], $result['data']);

        return $result;
    }

    /**
     * Opens a screening for its reviewer. Every open is logged.
     *
     * @return array<string, mixed>|null
     */
    public function openReview(int $id): ?array
    {
        $this->authorizeReviewer();

        $screening = $this->screenings->findDetailed($id);

        if ($screening === null) {
            return null;
        }

        $this->assertCanReview($screening);
        AuditService::log('eligibility.review_opened', 'eligibility_screening', $id);

        return [
            'screening' => $screening,
            'documents' => $this->documents->forScreening($id),
            'review' => $this->reviews->forScreening($id),
        ];
    }

    /**
     * Applies BR-09 to what the reviewer submitted. Only a submitted screening can be verified. A pending AI suggestion
     * is only marked here as used or ignored, and its values are never read (BR-11).
     */
    public function verify(int $id, bool $hasFailedSubject, bool $isOfficiallyEnrolled, bool $usedSuggestion = false): string
    {
        $this->authorizeReviewer();

        return Database::transaction(function () use ($id, $hasFailedSubject, $isOfficiallyEnrolled, $usedSuggestion): string {
            $screening = $this->lockForReview($id);
            $result = self::resultFor($hasFailedSubject, $isOfficiallyEnrolled);
            $now = date('Y-m-d H:i:s');

            $this->move($screening, $result, [
                'has_failed_subject' => $hasFailedSubject ? 1 : 0,
                'is_officially_enrolled' => $isOfficiallyEnrolled ? 1 : 0,
                'verified_by' => current_user_id(),
                'verified_at' => $now,
            ] + ($result === 'flagged' ? ['flagged_at' => $now] : []));

            (new AiExtractionService())->settle($id, $usedSuggestion, (int) current_user_id());

            $userId = (int) $screening['user_id'];
            $term = self::termLabel($screening);

            if ($result === 'flagged') {
                $this->notifications->notify($userId, 'ELIGIBILITY_FLAGGED', ['term' => $term], '/my-eligibility');
            } else {
                $this->notifications->notify($userId, 'ELIGIBILITY_RESULT', ['term' => $term, 'status' => 'Eligible', 'remarks' => 'All requirements were met.'], '/my-eligibility');
            }

            return $result;
        });
    }

    /**
     * flagged -> confirmed_ineligible or cleared (BR-10, BR-14). Everything succeeds together or not at all.
     *
     * @throws DomainException
     */
    public function decide(int $id, string $decision, string $remarks): void
    {
        $this->authorizeReviewer();

        if (!in_array($decision, ['confirm_ineligible', 'clear'], true)) {
            throw new DomainException('Please choose a decision.');
        }

        if (trim($remarks) === '') {
            throw new DomainException('Remarks are required.');
        }

        Database::transaction(function () use ($id, $decision, $remarks): void {
            $screening = $this->lockForReview($id);
            $confirmed = $decision === 'confirm_ineligible';
            $actorId = (int) current_user_id();

            $this->move($screening, $confirmed ? 'confirmed_ineligible' : 'cleared');
            $this->reviews->insert([
                'screening_id' => $id,
                'decision' => $decision,
                'remarks' => trim($remarks),
                'decided_by' => $actorId,
                'decided_at' => date('Y-m-d H:i:s'),
            ]);
            AuditService::log('eligibility.decided', 'eligibility_screening', $id, ['decision' => $decision]);

            if ($confirmed) {
                (new MembershipService())->expireRole((int) $screening['member_id'], trim($remarks), $actorId);
            }

            $this->notifications->notify((int) $screening['user_id'], 'ELIGIBILITY_RESULT', [
                'term' => self::termLabel($screening),
                'status' => $confirmed ? 'Confirmed Ineligible' : 'Cleared',
                'remarks' => trim($remarks),
            ], '/my-eligibility');
        });
    }

    /**
     * The owner and the screening's reviewer. Anyone else gets a 403 whether or not it exists.
     *
     * @return array{stored_path: string, download_name: string, inline: bool, log: bool, previewable: bool}|null
     */
    public function openDocument(int $id): ?array
    {
        $document = $this->documents->findWithOwner($id);
        $isOwner = $document !== null && (int) $document['owner_id'] === current_user_id();
        $isReviewer = $document !== null
            ? $this->isReviewerFor(['user_id' => $document['owner_id'], 'role_slug' => $document['owner_role']])
            : can('eligibility.manage') || can('eligibility.decide_msw_head');

        if (!$isOwner && !$isReviewer) {
            throw new ForbiddenException('file.eligibility_document');
        }

        if ($document === null) {
            return null;
        }

        return ['stored_path' => $document['stored_path'], 'download_name' => $document['original_name'], 'inline' => false, 'log' => true, 'previewable' => $isReviewer];
    }

    /**
     * What the officer should know, for their dashboard and profile. Null when they have nothing to act on.
     *
     * @return array{tone: string, message: string}|null
     */
    public function noticeFor(int $memberId): ?array
    {
        $term = $this->terms->current();
        $screening = $term === null ? null : $this->screenings->findFor((int) $term['term_id'], $memberId);

        if ($screening === null) {
            return null;
        }

        $label = self::termLabel($term);
        $deadline = date('F j, Y', strtotime($term['screening_deadline']));

        return match ($screening['result']) {
            'pending' => ['tone' => 'warning', 'message' => "Please submit your eligibility documents for {$label} by {$deadline}."],
            'submitted' => ['tone' => 'info', 'message' => "Your eligibility documents for {$label} are being reviewed."],
            'eligible' => ['tone' => 'success', 'message' => "You are eligible for {$label}."],
            'incomplete', 'flagged' => ['tone' => 'warning', 'message' => "Your eligibility screening for {$label} was flagged for review. You will be notified of the result."],
            'cleared' => ['tone' => 'success', 'message' => "Your eligibility screening for {$label} was cleared. Your role stays active."],
            'confirmed_ineligible' => ['tone' => 'danger', 'message' => "You were confirmed ineligible for {$label}, so your officer role has ended. You remain an Active General Member."],
            default => null,
        };
    }

    /**
     * The daily job. Reminds officers still pending, and after the deadline plus grace days moves them
     * pending -> incomplete -> flagged. It runs with nobody signed in, so it has no permission check, and controllers
     * must not call it. A flagged row is no longer pending, and a reminder already sent today is not sent again.
     *
     * @param callable(string): void $log
     * @return array{reminded: int, flagged: int, failed: int}
     */
    public function runReminders(callable $log): array
    {
        $counts = ['reminded' => 0, 'flagged' => 0, 'failed' => 0];
        $term = $this->terms->current();

        if ($term === null) {
            return $counts;
        }

        $isPastGrace = date('Y-m-d') > self::lastUploadDate($term);
        $sent = new NotificationRepository();

        foreach ($this->screenings->pendingIds((int) $term['term_id']) as $id) {
            try {
                Database::transaction(function () use ($id, $term, $isPastGrace, $sent, &$counts, $log): void {
                    $screening = $this->screenings->lockAndFind($id);

                    if ($screening['result'] !== 'pending') {
                        return;
                    }

                    $detail = $this->screenings->findDetailed($id);
                    $userId = (int) $detail['user_id'];

                    if ($isPastGrace) {
                        $this->move($screening, 'incomplete');
                        $screening['result'] = 'incomplete';
                        $this->move($screening, 'flagged', ['flagged_at' => date('Y-m-d H:i:s')]);
                        $this->notifications->notify($userId, 'ELIGIBILITY_FLAGGED', ['term' => self::termLabel($term)], '/my-eligibility');
                        $counts['flagged']++;
                        $log("Screening {$id} is incomplete and now flagged.");

                        return;
                    }

                    if (!$sent->sentToday($userId, 'ELIGIBILITY_REQUEST')) {
                        $this->sendRequest($userId, $term);
                        $counts['reminded']++;
                        $log("Screening {$id}: reminder queued.");
                    }
                });
            } catch (Throwable $exception) {
                $counts['failed']++;
                $log(sprintf('Screening %d failed with %s: %s', $id, $exception::class, $exception->getMessage()));
            }
        }

        return $counts;
    }

    /**
     * ROLE_EXPIRATION_REMINDER 7 days and 1 day before the deadline, for officers who have not submitted. Runs with
     * nobody signed in, like runReminders().
     *
     * @param callable(string): void $log
     * @return array{sent: int, failed: int}
     */
    public function runRoleExpirationReminders(callable $log): array
    {
        $counts = ['sent' => 0, 'failed' => 0];
        $term = $this->terms->current();

        if ($term === null) {
            return $counts;
        }

        $daysLeft = (int) round((strtotime($term['screening_deadline']) - strtotime(date('Y-m-d'))) / 86400);

        if (!in_array($daysLeft, self::REMINDER_DAYS, true)) {
            return $counts;
        }

        $sent = new NotificationRepository();

        foreach ($this->screenings->pendingIds((int) $term['term_id']) as $id) {
            try {
                $detail = $this->screenings->findDetailed($id);
                $userId = (int) $detail['user_id'];

                if ($sent->sentToday($userId, 'ROLE_EXPIRATION_REMINDER')) {
                    continue;
                }

                $this->notifications->notify($userId, 'ROLE_EXPIRATION_REMINDER', [
                    'position' => MembershipService::roleLabel($detail),
                    'deadline' => date('F j, Y', strtotime($term['screening_deadline'])),
                ], '/my-eligibility');
                $counts['sent']++;
                $log("Screening {$id}: role expiration reminder queued.");
            } catch (Throwable $exception) {
                $counts['failed']++;
                $log(sprintf('Screening %d failed with %s: %s', $id, $exception::class, $exception->getMessage()));
            }
        }

        return $counts;
    }

    public function isReviewerFor(array $screening): bool
    {
        if ((int) $screening['user_id'] === current_user_id()) {
            return false;
        }

        return $screening['role_slug'] === 'msw_head' ? can('eligibility.decide_msw_head') : can('eligibility.manage');
    }

    private function canUpload(array $screening, array $term): bool
    {
        return in_array($screening['result'], ['pending', 'submitted'], true) && date('Y-m-d') <= self::lastUploadDate($term);
    }

    private function sendRequest(int $userId, array $term): void
    {
        $this->notifications->notify($userId, 'ELIGIBILITY_REQUEST', [
            'term' => self::termLabel($term),
            'deadline' => date('F j, Y', strtotime($term['screening_deadline'])),
        ], '/my-eligibility');
    }

    private function authorizeReviewer(): void
    {
        if (!can('eligibility.manage') && !can('eligibility.decide_msw_head')) {
            throw new ForbiddenException('eligibility.manage');
        }
    }

    private function assertCanReview(array $screening): void
    {
        if (!$this->isReviewerFor($screening)) {
            throw new ForbiddenException((int) $screening['user_id'] === current_user_id() ? 'eligibility.own_screening' : 'eligibility.decide_msw_head');
        }
    }

    /** @return array<string, mixed> The screening with its owner and term, locked and checked. */
    private function lockForReview(int $id): array
    {
        $screening = $this->screenings->lockAndFind($id) ?? throw new DomainException('This screening no longer exists.');
        $detail = $this->screenings->findDetailed($id);
        $this->assertCanReview($detail);

        return $detail + $screening;
    }

    /**
     * The only place a screening's result changes.
     *
     * @param array<string, mixed> $changes
     */
    private function move(array $screening, string $to, array $changes = []): void
    {
        if (!self::canTransition($screening['result'], $to)) {
            throw new DomainException('This screening cannot move from ' . str_replace('_', ' ', $screening['result']) . ' to ' . str_replace('_', ' ', $to) . '.');
        }

        $id = (int) $screening['screening_id'];
        $this->screenings->update($id, ['result' => $to] + $changes);
        AuditService::log('eligibility.status_changed', 'eligibility_screening', $id, ['from' => $screening['result'], 'to' => $to]);
    }
}
