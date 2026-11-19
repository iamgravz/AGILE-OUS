<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\AppointmentRepository;
use App\Models\AuditLogRepository;
use App\Models\MemberRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\PositionRepository;
use App\Models\RoleRepository;
use App\Models\VacancyRepository;
use DomainException;

/** Appointments (FR-REC-07, 08, TC-12). A prepared appointment changes nothing until the MSW Committee Head approves it. */
final class AppointmentService
{
    public const STATUSES = ['prepared', 'approved', 'returned'];

    /** Section 8. */
    private const TRANSITIONS = [
        'prepared' => ['approved', 'returned'],
        'approved' => [],
        'returned' => [],
    ];

    public function __construct(
        private readonly AppointmentRepository $appointments = new AppointmentRepository(),
        private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository(),
        private readonly VacancyRepository $vacancies = new VacancyRepository(),
        private readonly PositionRepository $positions = new PositionRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @param array{status?: string} $filters */
    public function list(array $filters, int $page): array
    {
        authorize('appointment.prepare');

        return $this->appointments->search($filters, $page);
    }

    /** @return list<array<string, mixed>> */
    public function readyToPrepare(): array
    {
        authorize('appointment.prepare');

        return $this->appointments->readyToPrepare();
    }

    /**
     * The application's live appointment, for its detail page.
     *
     * @return array<string, mixed>|null
     */
    public function forApplication(int $applicationId): ?array
    {
        authorize('appointment.prepare');

        return $this->appointments->liveForApplication($applicationId);
    }

    /** @return array<string, mixed>|null The application and position to prefill the form, or null when it cannot be prepared. */
    public function forPrepare(int $applicationId): ?array
    {
        authorize('appointment.prepare');

        $application = $this->applications->findDetailed($applicationId);

        if ($application === null) {
            return null;
        }

        try {
            $vacancy = $this->assertCanPrepare($application);
        } catch (DomainException) {
            return null;
        }

        return ['application' => $application, 'vacancy' => $vacancy, 'position' => $this->positions->findWithCommittee((int) $vacancy['position_id'])];
    }

    /**
     * The position always comes from the vacancy, whose slots this appointment takes.
     *
     * @throws DomainException
     */
    public function prepare(int $applicationId, string $effectiveDate, string $remarks): int
    {
        authorize('appointment.prepare');
        $this->requireRemarks($remarks);

        if ($effectiveDate < date('Y-m-d')) {
            throw new DomainException('The effective date cannot be in the past.');
        }

        return Database::transaction(function () use ($applicationId, $effectiveDate, $remarks): int {
            $application = $this->lockApplication($applicationId);
            $vacancy = $this->assertCanPrepare($application);
            $member = (new MemberRepository())->findByUser((int) $application['user_id']);

            $id = $this->appointments->insert([
                'application_id' => $applicationId,
                'member_id' => $member['member_id'] ?? null,
                'position_id' => (int) $vacancy['position_id'],
                'status' => 'prepared',
                'prepared_by' => current_user_id(),
                'remarks' => trim($remarks),
                'effective_date' => $effectiveDate,
            ]);

            AuditService::log('appointment.prepared', 'appointment', $id, ['application_id' => $applicationId, 'remarks' => trim($remarks)]);

            return $id;
        });
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        authorize('appointment.prepare');

        return $this->appointments->findDetailed($id);
    }

    /** @return array<string, mixed> The extras on the appointment page. */
    public function details(array $appointment): array
    {
        authorize('appointment.prepare');

        $position = $this->positions->findWithCommittee((int) $appointment['position_id']);
        $roles = new RoleRepository();
        $options = [];

        foreach (config('role_mapping.overrides') as $slug) {
            $options[$slug] = $roles->findBySlug($slug)['role_name'];
        }

        $slug = MembershipService::roleSlugFor($position);

        return [
            'resultingRole' => $options[$slug] ?? $slug,
            'resultingRoleSlug' => $slug,
            'roleOptions' => $options,
            'history' => (new AuditLogRepository())->forEntity('appointment', (int) $appointment['appointment_id']),
        ];
    }

    /**
     * MSW Committee Head only. Everything succeeds together or not at all: the member record, the role, the
     * credentials, the vacancy's slots and the notice (TC-12).
     *
     * @throws DomainException
     */
    public function approve(int $id, string $remarks, ?string $roleOverride, string $overrideReason): void
    {
        authorize('appointment.approve');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks, $roleOverride, $overrideReason): void {
            $appointment = $this->lockAppointment($id);
            $this->assertTransition($appointment['status'], 'approved');
            $application = $this->lockApplication((int) $appointment['application_id']);
            $position = $this->positions->findWithCommittee((int) $appointment['position_id']);
            $mapped = MembershipService::roleSlugFor($position);
            $override = $this->validOverride($roleOverride, $overrideReason, $mapped);
            $actorId = (int) current_user_id();

            $membership = new MembershipService();
            $member = $membership->ensureMember((int) $application['user_id'], trim($remarks));
            $memberId = (int) $member['member_id'];

            $this->appointments->update($id, [
                'status' => 'approved',
                'member_id' => $memberId,
                'appointed_by' => $actorId,
                'remarks' => trim($remarks),
            ]);

            $reason = 'Appointed: ' . trim($remarks) . ($override === null ? '' : ' Role set by the MSW Committee Head: ' . trim($overrideReason));
            $membership->changeRole($memberId, (int) $appointment['position_id'], $reason, $actorId, $override);
            (new RecruitmentService())->recordAppointment((int) $application['vacancy_id']);

            AuditService::log('appointment.approved', 'appointment', $id, array_filter([
                'application_id' => (int) $application['application_id'],
                'member_id' => $memberId,
                'remarks' => trim($remarks),
                'role_mapped' => $mapped,
                'role_override' => $override,
                'override_reason' => $override === null ? null : trim($overrideReason),
            ], static fn (mixed $value): bool => $value !== null));

            $this->notifications->notify((int) $application['user_id'], 'APPOINTMENT_NOTICE', [
                'position' => MembershipService::roleLabel(['position_title' => $position['title'], 'committee_name' => $position['committee_name']]),
                'effective_date' => date('F j, Y', strtotime($appointment['effective_date'])),
            ], '/account');
        });
    }

    /** prepared -> returned. A new appointment can then be prepared. */
    public function returnAppointment(int $id, string $remarks): void
    {
        authorize('appointment.approve');
        $this->requireRemarks($remarks);

        Database::transaction(function () use ($id, $remarks): void {
            $appointment = $this->lockAppointment($id);
            $this->assertTransition($appointment['status'], 'returned');
            $this->lockApplication((int) $appointment['application_id']);

            $this->appointments->update($id, ['status' => 'returned', 'remarks' => trim($remarks)]);
            AuditService::log('appointment.returned', 'appointment', $id, ['application_id' => (int) $appointment['application_id'], 'remarks' => trim($remarks)]);
        });
    }

    /**
     * @return array<string, mixed> The vacancy.
     * @throws DomainException
     */
    private function assertCanPrepare(array $application): array
    {
        if ($application['status'] !== 'approved' || $application['vacancy_id'] === null) {
            throw new DomainException('Only an approved application for a position can have an appointment.');
        }

        $applicationId = (int) $application['application_id'];

        if ($this->appointments->liveForApplication($applicationId) !== null) {
            throw new DomainException('This application already has an appointment.');
        }

        $vacancy = $this->vacancies->find((int) $application['vacancy_id']);

        if (!in_array($vacancy['status'], ['open', 'closed'], true) || (int) $vacancy['slots_filled'] >= (int) $vacancy['slots']) {
            throw new DomainException('This vacancy has no free slot.');
        }

        $member = (new MemberRepository())->findByUser((int) $application['user_id']);

        if ($member !== null && (int) $member['position_id'] === (int) $vacancy['position_id']) {
            throw new DomainException('This member already holds that position.');
        }

        return $vacancy;
    }

    /** @return string|null The override to apply, or null when the mapped role stands. */
    private function validOverride(?string $roleOverride, string $reason, string $mapped): ?string
    {
        if ($roleOverride === null || $roleOverride === '' || $roleOverride === $mapped) {
            return null;
        }

        if (!in_array($roleOverride, config('role_mapping.overrides'), true)) {
            throw new DomainException('Please choose a role from the list.');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required to change the resulting role.');
        }

        return $roleOverride;
    }

    /** @return array<string, mixed> */
    private function lockAppointment(int $id): array
    {
        return $this->appointments->lockAndFind($id) ?? throw new DomainException('This appointment no longer exists.');
    }

    /** @return array<string, mixed> The application row, locked. Nobody may act on their own application. */
    private function lockApplication(int $id): array
    {
        $application = $this->applications->findForUpdate($id);

        if ($application === null || $application['status'] === 'draft') {
            throw new DomainException('This application no longer exists.');
        }

        if ((int) $application['user_id'] === current_user_id()) {
            throw new ForbiddenException('application.own_review');
        }

        return $application;
    }

    private function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new DomainException("An appointment cannot move from {$from} to {$to}.");
        }
    }

    /** BR-14. */
    private function requireRemarks(string $remarks): void
    {
        if (trim($remarks) === '') {
            throw new DomainException('Remarks are required.');
        }
    }
}
