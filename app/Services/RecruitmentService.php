<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\AuditLogRepository;
use App\Models\HrRequestRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\PositionRepository;
use App\Models\UserRepository;
use App\Models\VacancyRepository;
use DomainException;
use Throwable;

/** HR requests and vacancies (FR-REC-01, 02, 03, 09). Evaluations, interviews and appointments have their own services. */
final class RecruitmentService
{
    public const REQUEST_STATUSES = ['submitted', 'approved', 'returned', 'cancelled'];
    public const VACANCY_STATUSES = ['draft', 'open', 'closed', 'filled', 'cancelled'];
    public const MAX_SLOTS = 255;

    /** Section 8. */
    private const REQUEST_TRANSITIONS = [
        'submitted' => ['approved', 'returned', 'cancelled'],
        'returned' => ['submitted'],
        'approved' => [],
        'cancelled' => [],
    ];

    /** Section 8. */
    private const VACANCY_TRANSITIONS = [
        'draft' => ['open', 'cancelled'],
        'open' => ['closed', 'filled', 'cancelled'],
        'closed' => ['open', 'filled'],
        'filled' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly HrRequestRepository $requests = new HrRequestRepository(),
        private readonly VacancyRepository $vacancies = new VacancyRepository(),
        private readonly PositionRepository $positions = new PositionRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    public static function canTransitionRequest(string $from, string $to): bool
    {
        return in_array($to, self::REQUEST_TRANSITIONS[$from] ?? [], true);
    }

    public static function canTransitionVacancy(string $from, string $to): bool
    {
        return in_array($to, self::VACANCY_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * The date rules for a vacancy, by field, so the form can show them inline and the service can enforce them.
     *
     * @return array<string, string>
     */
    public static function scheduleErrors(string $opensAt, string $closesAt, bool $publishing, string $now): array
    {
        if ($closesAt <= $opensAt) {
            return ['closes_at' => 'The closing date must be after the opening date.'];
        }

        if ($publishing && $closesAt <= $now) {
            return ['closes_at' => 'The closing date must be in the future to publish.'];
        }

        return [];
    }

    // HR requests

    /** The MSW Committee Head sees every request. Others see their own and those of a committee they head. */
    public function listRequests(array $filters, int $page): array
    {
        authorize('hr_request.submit');

        if (!can('hr_request.approve')) {
            $filters['visible_to'] = (int) current_user_id();
        }

        return $this->requests->search($filters, $page);
    }

    /** @return list<array<string, mixed>> Positions the signed-in user may request: those of committees they head. */
    public function ownPositions(): array
    {
        authorize('hr_request.submit');

        return $this->positions->forCommitteesHeadedBy((int) current_user_id());
    }

    /** @return array<string, mixed>|null */
    public function findRequest(int $id): ?array
    {
        authorize('hr_request.submit');

        $request = $this->requests->findDetailed($id);

        if ($request !== null) {
            $this->assertCanSee($request);
        }

        return $request;
    }

    /** @return array{history: list<array<string, mixed>>, vacancy: array<string, mixed>|null} For a request findRequest() returned. */
    public function requestDetails(array $request): array
    {
        return [
            'history' => (new AuditLogRepository())->forEntity('hr_request', (int) $request['request_id']),
            'vacancy' => $this->vacancies->liveForRequest((int) $request['request_id']),
        ];
    }

    /** @param array{position_id: int, slots: int, justification: string, qualifications: string} $data */
    public function submitRequest(array $data): int
    {
        authorize('hr_request.submit');

        return Database::transaction(function () use ($data): int {
            $position = $this->ownPosition($data['position_id']);
            $this->assertSlots($data['slots']);

            $id = $this->requests->insert([
                'committee_id' => (int) $position['committee_id'],
                'position_id' => (int) $position['position_id'],
                'requested_by' => current_user_id(),
                'slots' => $data['slots'],
                'justification' => $data['justification'],
                'qualifications' => $data['qualifications'],
                'status' => 'submitted',
            ]);

            AuditService::log('hr_request.submitted', 'hr_request', $id, ['committee_id' => (int) $position['committee_id'], 'position_id' => (int) $position['position_id'], 'slots' => $data['slots']]);
            $this->tellMswHead($id, $position, $data['slots']);

            return $id;
        });
    }

    /**
     * returned -> submitted, with the requester's changes. The earlier remarks stay in the history.
     *
     * @param array{position_id: int, slots: int, justification: string, qualifications: string} $data
     */
    public function resubmitRequest(int $id, array $data): void
    {
        authorize('hr_request.submit');

        Database::transaction(function () use ($id, $data): void {
            $request = $this->lockedRequest($id);
            $this->assertRequester($request);
            $this->assertRequestTransition($request['status'], 'submitted');

            $position = $this->ownPosition($data['position_id']);
            $this->assertSlots($data['slots']);

            $this->requests->update($id, [
                'committee_id' => (int) $position['committee_id'],
                'position_id' => (int) $position['position_id'],
                'slots' => $data['slots'],
                'justification' => $data['justification'],
                'qualifications' => $data['qualifications'],
                'status' => 'submitted',
                'remarks' => null,
            ]);

            AuditService::log('hr_request.resubmitted', 'hr_request', $id, ['position_id' => (int) $position['position_id'], 'slots' => $data['slots']]);
            $this->tellMswHead($id, $position, $data['slots']);
        });
    }

    public function approveRequest(int $id, string $remarks): void
    {
        authorize('hr_request.approve');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $request = $this->lockedRequest($id);
            $this->assertRequestTransition($request['status'], 'approved');

            $this->requests->update($id, [
                'status' => 'approved',
                'remarks' => $remarks,
                'approved_by' => current_user_id(),
                'approved_at' => date('Y-m-d H:i:s'),
            ]);

            AuditService::log('hr_request.approved', 'hr_request', $id, ['remarks' => $remarks]);
            $this->tellRequester($request, 'HR_REQUEST_APPROVED', ['slots' => (int) $request['slots']]);
        });
    }

    /** submitted -> returned. The requester can edit and resubmit it. */
    public function returnRequest(int $id, string $remarks): void
    {
        authorize('hr_request.approve');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $request = $this->lockedRequest($id);
            $this->assertRequestTransition($request['status'], 'returned');

            $this->requests->update($id, ['status' => 'returned', 'remarks' => $remarks]);

            AuditService::log('hr_request.returned', 'hr_request', $id, ['remarks' => $remarks]);
            $this->tellRequester($request, 'HR_REQUEST_RETURNED', ['remarks' => $remarks]);
        });
    }

    public function cancelRequest(int $id): void
    {
        authorize('hr_request.submit');

        Database::transaction(function () use ($id): void {
            $request = $this->lockedRequest($id);
            $this->assertRequester($request);
            $this->assertRequestTransition($request['status'], 'cancelled');

            $this->requests->update($id, ['status' => 'cancelled']);

            AuditService::log('hr_request.cancelled', 'hr_request', $id);
        });
    }

    // Vacancies

    public function listVacancies(array $filters, int $page): array
    {
        authorize('vacancy.manage');

        return $this->vacancies->search($filters, $page);
    }

    /** @return list<array<string, mixed>> Approved requests that have no vacancy yet. */
    public function requestsReadyForVacancy(): array
    {
        authorize('vacancy.manage');

        return $this->requests->readyForVacancy();
    }

    /** @return array<string, mixed>|null The approved request to prefill the form from, or null when it cannot be used. */
    public function requestForNewVacancy(int $requestId): ?array
    {
        authorize('vacancy.manage');

        $request = $this->requests->findDetailed($requestId);

        if ($request === null || $request['status'] !== 'approved' || $this->vacancies->liveForRequest($requestId) !== null) {
            return null;
        }

        return $request;
    }

    /**
     * BR-04: only from an approved request. Position, slots and qualifications are copied from it.
     *
     * @param array{title: string, description: string, opens_at: string, closes_at: string} $data
     */
    public function createVacancy(int $requestId, array $data, bool $publish): int
    {
        authorize('vacancy.manage');
        $this->assertSchedule($data['opens_at'], $data['closes_at'], $publish);

        return Database::transaction(function () use ($requestId, $data, $publish): int {
            // The lock stops two tabs creating two vacancies from one request.
            $request = $this->lockedRequest($requestId);

            if ($request['status'] !== 'approved') {
                throw new DomainException('A vacancy can only be created from an approved HR request.');
            }

            if ($this->vacancies->liveForRequest($requestId) !== null) {
                throw new DomainException('This HR request already has a vacancy.');
            }

            $id = $this->vacancies->insert($data + [
                'request_id' => $requestId,
                'position_id' => (int) $request['position_id'],
                'qualifications' => $request['qualifications'],
                'slots' => (int) $request['slots'],
                'status' => $publish ? 'open' : 'draft',
            ]);

            AuditService::log('vacancy.created', 'vacancy', $id, ['request_id' => $requestId, 'status' => $publish ? 'open' : 'draft']);

            if ($publish) {
                AuditService::log('vacancy.published', 'vacancy', $id);
            }

            return $id;
        });
    }

    /**
     * Drafts only: an open vacancy already has applicants reading what it says.
     *
     * @param array{title: string, description: string, opens_at: string, closes_at: string} $data
     */
    public function updateDraft(int $id, array $data, bool $publish): void
    {
        authorize('vacancy.manage');
        $this->assertSchedule($data['opens_at'], $data['closes_at'], $publish);

        Database::transaction(function () use ($id, $data, $publish): void {
            $vacancy = $this->lockedVacancy($id);

            if ($vacancy['status'] !== 'draft') {
                throw new DomainException('Only a draft vacancy can be edited.');
            }

            $this->vacancies->update($id, $data);
            AuditService::log('vacancy.updated', 'vacancy', $id);

            if ($publish) {
                $this->moveVacancy($vacancy, 'open', 'vacancy.published');
            }
        });
    }

    public function publishVacancy(int $id): void
    {
        authorize('vacancy.manage');

        Database::transaction(function () use ($id): void {
            $vacancy = $this->lockedVacancy($id);

            if ($vacancy['status'] !== 'draft') {
                throw new DomainException('Only a draft vacancy can be published.');
            }

            $this->assertSchedule($vacancy['opens_at'], $vacancy['closes_at'], true);
            $this->moveVacancy($vacancy, 'open', 'vacancy.published');
        });
    }

    /** closed -> open with a new closing date. */
    public function reopenVacancy(int $id, string $closesAt): void
    {
        authorize('vacancy.manage');

        Database::transaction(function () use ($id, $closesAt): void {
            $vacancy = $this->lockedVacancy($id);

            if ($vacancy['status'] !== 'closed') {
                throw new DomainException('Only a closed vacancy can be reopened.');
            }

            if ((int) $vacancy['slots_filled'] >= (int) $vacancy['slots']) {
                throw new DomainException('All the slots of this vacancy are filled, so it cannot be reopened.');
            }

            $this->assertSchedule($vacancy['opens_at'], $closesAt, true);
            $this->moveVacancy($vacancy, 'open', 'vacancy.reopened', ['closes_at' => $closesAt], ['closes_at' => $closesAt]);
        });
    }

    public function cancelVacancy(int $id, string $remarks): void
    {
        authorize('vacancy.manage');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $this->moveVacancy($this->lockedVacancy($id), 'cancelled', 'vacancy.cancelled', [], ['remarks' => $remarks]);
        });
    }

    /** @return array<string, mixed>|null */
    public function findVacancy(int $id): ?array
    {
        authorize('vacancy.manage');

        return $this->vacancies->findDetailed($id);
    }

    /** @return array{applications: list<array<string, mixed>>, history: list<array<string, mixed>>} */
    public function vacancyDetails(int $id): array
    {
        authorize('vacancy.manage');

        return [
            'applications' => (new MembershipApplicationRepository())->forVacancy($id),
            'history' => (new AuditLogRepository())->forEntity('vacancy', $id),
        ];
    }

    /**
     * Closes open vacancies that are past their date or have every slot filled (BR-04), and tells the committee
     * head. Runs from jobs/close_vacancies.php with nobody signed in, so it has no permission check. Controllers
     * must not call it. A vacancy that was already handled is no longer open, so a second run does nothing.
     *
     * @param callable(string): void $log
     * @return array{closed: int, filled: int, failed: int}
     */
    public function closeDueVacancies(callable $log): array
    {
        $counts = ['closed' => 0, 'filled' => 0, 'failed' => 0];

        foreach ($this->vacancies->dueIds(date('Y-m-d H:i:s')) as $id) {
            try {
                $outcome = Database::transaction(fn (): ?string => $this->closeOne($id));

                if ($outcome !== null) {
                    $counts[$outcome]++;
                    $log("Vacancy {$id} is now {$outcome}.");
                }
            } catch (Throwable $exception) {
                // One bad vacancy must not stop the rest, and it is picked up again on the next run.
                $counts['failed']++;
                $log(sprintf('Vacancy %d failed with %s: %s', $id, $exception::class, $exception->getMessage()));
            }
        }

        return $counts;
    }

    /**
     * Takes one slot for an approved appointment. The last slot makes the vacancy Filled, even after its closing date.
     * The caller holds the transaction.
     *
     * @throws DomainException
     */
    public function recordAppointment(int $vacancyId): void
    {
        authorize('appointment.approve');

        $vacancy = $this->lockedVacancy($vacancyId);
        $filled = (int) $vacancy['slots_filled'] + 1;

        if (!in_array($vacancy['status'], ['open', 'closed'], true) || $filled > (int) $vacancy['slots']) {
            throw new DomainException('This vacancy has no free slot.');
        }

        $this->vacancies->update($vacancyId, ['slots_filled' => $filled]);
        AuditService::log('vacancy.slot_filled', 'vacancy', $vacancyId, ['slots_filled' => $filled, 'slots' => (int) $vacancy['slots']]);

        if ($filled === (int) $vacancy['slots']) {
            $vacancy['slots_filled'] = $filled;
            $this->moveVacancy($vacancy, 'filled', 'vacancy.filled', [], ['slots_filled' => $filled, 'slots' => $filled]);
            $this->tellCommitteeHead($vacancy);
        }
    }

    private function closeOne(int $id): ?string
    {
        $vacancy = $this->lockedVacancy($id);
        $isFilled = (int) $vacancy['slots_filled'] >= (int) $vacancy['slots'];

        if ($vacancy['status'] !== 'open' || (!$isFilled && $vacancy['closes_at'] > date('Y-m-d H:i:s'))) {
            return null;
        }

        $outcome = $isFilled ? 'filled' : 'closed';
        $this->moveVacancy($vacancy, $outcome, "vacancy.{$outcome}", [], ['slots_filled' => (int) $vacancy['slots_filled'], 'slots' => (int) $vacancy['slots']]);

        $this->tellCommitteeHead($vacancy);

        return $outcome;
    }

    private function tellCommitteeHead(array $vacancy): void
    {
        $request = $this->requests->findDetailed((int) $vacancy['request_id']);

        // Vacancy pages need vacancy.manage, so the link is the HR request, which shows the vacancy's status.
        $this->notifications->notify((int) ($request['head_user_id'] ?? $request['requested_by']), 'VACANCY_CLOSED', [
            'title' => $vacancy['title'],
            'slots' => (int) $vacancy['slots'],
            'slots_filled' => (int) $vacancy['slots_filled'],
        ], '/recruitment/hr-requests/' . (int) $vacancy['request_id']);
    }

    // Shared rules

    /** @return array<string, mixed> The position, when it belongs to a committee the signed-in user heads. */
    private function ownPosition(int $positionId): array
    {
        $position = $this->positions->findWithCommittee($positionId);

        if ($position === null) {
            throw new DomainException('Please choose a position.');
        }

        if ($position['head_user_id'] === null || (int) $position['head_user_id'] !== current_user_id()) {
            throw new ForbiddenException('hr_request.own_committee');
        }

        return $position;
    }

    private function assertCanSee(array $request): void
    {
        $userId = current_user_id();
        $isOwn = (int) $request['requested_by'] === $userId || ($request['head_user_id'] !== null && (int) $request['head_user_id'] === $userId);

        if (!$isOwn && !can('hr_request.approve')) {
            throw new ForbiddenException('hr_request.own_committee');
        }
    }

    private function assertRequester(array $request): void
    {
        if ((int) $request['requested_by'] !== current_user_id()) {
            throw new ForbiddenException('hr_request.own_request');
        }
    }

    private function assertSlots(int $slots): void
    {
        if ($slots < 1 || $slots > self::MAX_SLOTS) {
            throw new DomainException('Slots must be between 1 and ' . self::MAX_SLOTS . '.');
        }
    }

    private function assertSchedule(string $opensAt, string $closesAt, bool $publishing): void
    {
        foreach (self::scheduleErrors($opensAt, $closesAt, $publishing, date('Y-m-d H:i:s')) as $message) {
            throw new DomainException($message);
        }
    }

    /** BR-14. */
    private function requireRemarks(string $remarks): void
    {
        if (trim($remarks) === '') {
            throw new DomainException('Remarks are required.');
        }
    }

    private function assertRequestTransition(string $from, string $to): void
    {
        if (!self::canTransitionRequest($from, $to)) {
            throw new DomainException("An HR request cannot move from {$from} to {$to}.");
        }
    }

    private function assertVacancyTransition(string $from, string $to): void
    {
        if (!self::canTransitionVacancy($from, $to)) {
            throw new DomainException("A vacancy cannot move from {$from} to {$to}.");
        }
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<string, mixed> $details
     */
    private function moveVacancy(array $vacancy, string $to, string $action, array $changes = [], array $details = []): void
    {
        $this->assertVacancyTransition($vacancy['status'], $to);
        $this->vacancies->update((int) $vacancy['vacancy_id'], ['status' => $to] + $changes);
        AuditService::log($action, 'vacancy', (int) $vacancy['vacancy_id'], $details);
    }

    /** @return array<string, mixed> */
    private function lockedRequest(int $id): array
    {
        return $this->requests->lockAndFind($id) ?? throw new DomainException('That HR request no longer exists.');
    }

    /** @return array<string, mixed> */
    private function lockedVacancy(int $id): array
    {
        return $this->vacancies->lockAndFind($id) ?? throw new DomainException('That vacancy no longer exists.');
    }

    /** TC-09. An MSW Head filing for their own committee is not told about their own request. */
    private function tellMswHead(int $requestId, array $position, int $slots): void
    {
        $recipients = array_values(array_diff((new UserRepository())->activeIdsByRole('msw_head'), [(int) current_user_id()]));

        $this->notifications->notifyMany($recipients, 'HR_REQUEST_SUBMITTED', [
            'committee' => $position['committee_name'],
            'slots' => $slots,
            'position' => $position['title'],
        ], "/recruitment/hr-requests/{$requestId}");
    }

    /** @param array<string, mixed> $payload */
    private function tellRequester(array $request, string $template, array $payload): void
    {
        if ((int) $request['requested_by'] === current_user_id()) {
            return;
        }

        $position = $this->positions->find((int) $request['position_id']);

        $this->notifications->notify((int) $request['requested_by'], $template, $payload + [
            'position' => $position['title'],
        ], '/recruitment/hr-requests/' . (int) $request['request_id']);
    }
}
