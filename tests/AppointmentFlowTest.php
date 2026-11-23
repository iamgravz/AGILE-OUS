<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\AppointmentRepository;
use App\Models\CredentialRepository;
use App\Models\InterviewRepository;
use App\Models\MemberRepository;
use App\Models\PositionRepository;
use App\Models\UserRepository;
use App\Models\VacancyRepository;
use App\Services\AppointmentService;
use App\Services\InterviewService;
use App\Services\MembershipService;
use App\Services\RbacService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Interviews and appointments. Needs the seeded database. Database tests roll back, including PDFs. */
final class AppointmentFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testAppointmentTransitionsMatchSectionEight(): void
    {
        foreach (AppointmentService::STATUSES as $from) {
            foreach (AppointmentService::STATUSES as $to) {
                $this->assertSame($from === 'prepared' && $to !== 'prepared', AppointmentService::canTransition($from, $to), "{$from} to {$to}");
            }
        }
    }

    /** @return list<array{?string, string, string, string}> */
    public static function mappings(): array
    {
        $msw = 'Membership and Student Welfare';

        return [
            [$msw, 'Committee Head', 'committee_head', 'msw_head'],
            [$msw, 'Deputy Committee Head', 'deputy', 'msw_member'],
            [$msw, 'Committee Member', 'committee_member', 'msw_member'],
            ['Academics and Training', 'Committee Head', 'committee_head', 'committee_head'],
            ['Academics and Training', 'Deputy Committee Head', 'deputy', 'general_member'],
            ['Academics and Training', 'Committee Member', 'committee_member', 'general_member'],
            ['The Source Code', 'Writer', 'source_code', 'general_member'],
            [null, 'President', 'officer', 'president'],
            [null, 'Vice President for Internal Affairs', 'officer', 'execomm'],
        ];
    }

    #[DataProvider('mappings')]
    public function testRoleMapping(?string $committee, string $title, string $roleType, string $expected): void
    {
        $this->assertSame($expected, MembershipService::roleSlugFor(['committee_name' => $committee, 'title' => $title, 'role_type' => $roleType]));
    }

    public function testReminderSendsOnceAndReschedulingArmsItAgain(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application('under_review', null);
            $this->signIn('mswmember@agile.test');
            $service = new InterviewService();
            $service->schedule($applicationId, $this->interview(24));
            $this->assertSame(2, $this->notices('INTERVIEW_INVITE'));
            $id = (int) (new InterviewRepository())->forApplication($applicationId)[0]['interview_id'];

            $service->sendDueReminders($this->noLog());
            $this->assertSame(2, $this->notices('INTERVIEW_REMINDER'));
            $this->assertNotNull((new InterviewRepository())->find($id)['reminder_sent_at']);
            $this->assertSame(['sent' => 0, 'failed' => 0], $service->sendDueReminders($this->noLog()));

            $service->reschedule($applicationId, $id, $this->interview(48));
            $this->assertSame(4, $this->notices('INTERVIEW_INVITE'));
            $this->assertNull((new InterviewRepository())->find($id)['reminder_sent_at']);
            $service->sendDueReminders($this->noLog());
            $this->assertSame(2, $this->notices('INTERVIEW_REMINDER'));
        });
    }

    public function testTheChecklistComesBeforeTheResult(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application('under_review', null);
            $this->signIn('mswmember@agile.test');
            $service = new InterviewService();
            $service->schedule($applicationId, $this->interview(24));
            $id = (int) (new InterviewRepository())->forApplication($applicationId)[0]['interview_id'];

            try {
                $service->recordResult($applicationId, $id, 'passed', '');
                $this->fail('A result needs the checklist first (BR-25).');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('checklist', $exception->getMessage());
            }

            try {
                $service->saveChecklist($applicationId, $id, ['identity' => true, 'role' => true, 'privacy' => false, 'conflict' => 'no']);
                $this->fail('Every item must be ticked.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $service->saveChecklist($applicationId, $id, ['identity' => true, 'role' => true, 'privacy' => true, 'conflict' => 'no']);
            $saved = (new InterviewRepository())->find($id);
            $this->assertSame($this->userId('mswmember@agile.test'), (int) $saved['checklist_by']);
            $this->assertSame(0, (int) $saved['conflict_of_interest']);

            $service->recordResult($applicationId, $id, 'passed', 'Confident answers.');
            $this->assertSame('passed', (new InterviewRepository())->find($id)['result']);
        });
    }

    public function testANoShowNeedsNoChecklist(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application('under_review', null);
            $this->signIn('mswmember@agile.test');
            $service = new InterviewService();
            $service->schedule($applicationId, $this->interview(24));
            $id = (int) (new InterviewRepository())->forApplication($applicationId)[0]['interview_id'];

            $service->recordResult($applicationId, $id, 'no_show', 'Did not join.');
            $this->assertSame('no_show', (new InterviewRepository())->find($id)['result']);
        });
    }

    public function testAConflictBlocksTheResultAndTheScoreUntilRescheduled(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application('under_review', $this->vacancy('open', 2));
            $this->signIn('mswmember@agile.test');
            $service = new InterviewService();
            $service->schedule($applicationId, $this->interview(24));
            $id = (int) (new InterviewRepository())->forApplication($applicationId)[0]['interview_id'];
            $service->saveChecklist($applicationId, $id, ['identity' => true, 'role' => true, 'privacy' => true, 'conflict' => 'yes']);

            try {
                $service->recordResult($applicationId, $id, 'passed', '');
                $this->fail('A declared conflict blocks the result.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('conflict of interest', $exception->getMessage());
            }

            // The interviewer with the conflict (the MSW Committee Head here) cannot score this applicant.
            $this->signIn('mswhead@agile.test');

            try {
                (new \App\Services\AssessmentService())->save($applicationId, [], [], ['recommendation' => 'recommended', 'strengths' => '', 'development' => '', 'remarks' => '']);
                $this->fail('An interviewer with a conflict cannot assess (BR-25).');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('conflict of interest', $exception->getMessage());
            }

            // Rescheduling with someone else starts a new checklist.
            $this->signIn('mswmember@agile.test');
            $service->reschedule($applicationId, $id, ['interviewer_id' => $this->userId('mswdeputy@agile.test')] + $this->interview(48));
            $fresh = (new InterviewRepository())->find($id);
            $this->assertNull($fresh['checklist_at']);
            $this->assertNull($fresh['conflict_of_interest']);
        });
    }

    public function testTheDatabaseRefusesAHalfFilledChecklist(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application('under_review', null);
            $this->signIn('mswmember@agile.test');
            (new InterviewService())->schedule($applicationId, $this->interview(24));
            $id = (int) (new InterviewRepository())->forApplication($applicationId)[0]['interview_id'];

            $this->expectException(PDOException::class);
            Database::connection()->exec("UPDATE interviews SET checklist_at = NOW(), checklist_by = 1 WHERE interview_id = {$id}");
        });
    }

    public function testPreparedChangesNothingAndApprovalChangesEverything(): void
    {
        $this->inRollback(function (): void {
            $vacancyId = $this->vacancy('open', 2);
            $applicationId = $this->application('approved', $vacancyId);
            $userId = $this->userId('nomember@agile.test');
            $roleBefore = (new UserRepository())->find($userId)['role_id'];

            $this->signIn('mswmember@agile.test');
            $id = (new AppointmentService())->prepare($applicationId, date('Y-m-d'), 'Ready');

            $this->assertNull((new MemberRepository())->findByUser($userId));
            $this->assertSame($roleBefore, (new UserRepository())->find($userId)['role_id']);
            $this->assertSame(0, (int) (new VacancyRepository())->find($vacancyId)['slots_filled']);

            try {
                (new AppointmentService())->approve($id, 'Approved', null, '');
                $this->fail('An MSW member approved an appointment.');
            } catch (ForbiddenException) {
                $this->addToAssertionCount(1);
            }

            $this->signIn('mswhead@agile.test');
            (new AppointmentService())->approve($id, 'Approved', null, '');

            $member = (new MemberRepository())->findByUser($userId);
            $detailed = (new MemberRepository())->findDetailed((int) $member['member_id']);
            $this->assertSame('general_member', $detailed['role_slug']);
            $this->assertSame('officer', $member['membership_type']);
            $this->assertSame('active', $member['role_status']);
            $this->assertSame((int) (new VacancyRepository())->find($vacancyId)['position_id'], (int) $member['position_id']);
            $this->assertSame(1, (int) (new VacancyRepository())->find($vacancyId)['slots_filled']);
            $this->assertSame('open', (new VacancyRepository())->find($vacancyId)['status']);
            $this->assertSame('approved', (new AppointmentRepository())->find($id)['status']);
            $this->assertCount(2, array_filter((new CredentialRepository())->forMember((int) $member['member_id']), static fn (array $c): bool => $c['revoked_at'] === null));
            $this->assertSame(2, $this->notices('APPOINTMENT_NOTICE'));
        });
    }

    public function testTheLastSlotFillsAClosedVacancy(): void
    {
        $this->inRollback(function (): void {
            $vacancyId = $this->vacancy('closed', 1);
            $applicationId = $this->application('approved', $vacancyId);
            $this->signIn('mswhead@agile.test');
            $service = new AppointmentService();
            $service->approve($service->prepare($applicationId, date('Y-m-d'), 'Ready'), 'Approved', null, '');

            $this->assertSame('filled', (new VacancyRepository())->find($vacancyId)['status']);
        });
    }

    public function testOverrideNeedsAReasonAndApplies(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application('approved', $this->vacancy('open', 2));
            $this->signIn('mswhead@agile.test');
            $service = new AppointmentService();
            $id = $service->prepare($applicationId, date('Y-m-d'), 'Ready');

            try {
                $service->approve($id, 'Approved', 'msw_member', ' ');
                $this->fail('An override without a reason was accepted.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            try {
                $service->approve($id, 'Approved', 'guest', 'No');
                $this->fail('A role outside the list was accepted.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $service->approve($id, 'Approved', 'msw_member', 'Helping MSW this term');
            $member = (new MemberRepository())->findByUser($this->userId('nomember@agile.test'));
            $this->assertSame('msw_member', (new MemberRepository())->findDetailed((int) $member['member_id'])['role_slug']);
        });
    }

    public function testAFullVacancyTakesNoMoreAppointments(): void
    {
        $this->inRollback(function (): void {
            $vacancyId = $this->vacancy('open', 1);
            $applicationId = $this->application('approved', $vacancyId);
            $this->signIn('mswhead@agile.test');
            $service = new AppointmentService();
            $id = $service->prepare($applicationId, date('Y-m-d'), 'Ready');
            (new VacancyRepository())->update($vacancyId, ['slots_filled' => 1]);

            try {
                $service->approve($id, 'Approved', null, '');
                $this->fail('A full vacancy took another appointment.');
            } catch (DomainException) {
                $this->assertSame(1, (int) (new VacancyRepository())->find($vacancyId)['slots_filled']);
            }
        });
    }

    public function testReturnedAppointmentAllowsANewOne(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application('approved', $this->vacancy('open', 2));
            $this->signIn('mswhead@agile.test');
            $service = new AppointmentService();
            $id = $service->prepare($applicationId, date('Y-m-d'), 'Ready');

            try {
                $service->prepare($applicationId, date('Y-m-d'), 'Twice');
                $this->fail('Two live appointments were prepared.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $service->returnAppointment($id, 'Wrong date');
            $this->assertNotSame($id, $service->prepare($applicationId, date('Y-m-d'), 'Again'));
        });
    }

    private function noLog(): callable
    {
        return static function (string $line): void {
        };
    }

    /** @return array{scheduled_at: string, mode: string, location: string, interviewer_id: int} */
    private function interview(int $hoursAhead): array
    {
        return [
            'scheduled_at' => date('Y-m-d H:i:s', time() + $hoursAhead * 3600 - 60),
            'mode' => 'onsite',
            'location' => 'Room 1',
            'interviewer_id' => $this->userId('mswhead@agile.test'),
        ];
    }

    private function notices(string $code): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id
             WHERE t.code = ? AND n.user_id = ? AND n.notification_id > ?'
        );
        $statement->execute([$code, $this->userId('nomember@agile.test'), $this->baseline]);

        return (int) $statement->fetchColumn();
    }

    private int $baseline = 0;

    private function vacancy(string $status, int $slots): int
    {
        $db = Database::connection();
        $this->baseline = (int) $db->query('SELECT COALESCE(MAX(notification_id), 0) FROM notifications')->fetchColumn();
        $position = $db->query("SELECT p.position_id, p.committee_id FROM positions p JOIN committees c ON c.committee_id = p.committee_id WHERE c.name = 'Academics and Training' AND p.title = 'Committee Member'")->fetch();
        $requestId = $this->insert('hr_requests', [
            'committee_id' => $position['committee_id'],
            'position_id' => $position['position_id'],
            'requested_by' => $this->userId('head@agile.test'),
            'slots' => $slots,
            'justification' => 'j',
            'qualifications' => 'q',
            'status' => 'approved',
        ]);

        return (new VacancyRepository())->insert([
            'request_id' => $requestId,
            'position_id' => $position['position_id'],
            'title' => 'Test vacancy',
            'description' => 'd',
            'slots' => $slots,
            'opens_at' => date('Y-m-d H:i:s', time() - 86400),
            'closes_at' => date('Y-m-d H:i:s', time() + ($status === 'closed' ? -3600 : 86400)),
            'status' => $status,
        ]);
    }

    private function application(string $status, ?int $vacancyId): int
    {
        if ($vacancyId === null) {
            $this->baseline = (int) Database::connection()->query('SELECT COALESCE(MAX(notification_id), 0) FROM notifications')->fetchColumn();
        }

        return $this->insert('membership_applications', [
            'user_id' => $this->userId('nomember@agile.test'),
            'vacancy_id' => $vacancyId,
            'tracking_code' => MembershipService::generateTrackingCode(),
            'status' => $status,
            'submitted_at' => date('Y-m-d H:i:s'),
            'wizard_step' => 6,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function insert(string $table, array $row): int
    {
        $columns = implode(', ', array_keys($row));
        $marks = implode(', ', array_fill(0, count($row), '?'));
        $db = Database::connection();
        $db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$marks})")->execute(array_values($row));

        return (int) $db->lastInsertId();
    }

    private function signIn(string $email): void
    {
        $_SESSION['user_id'] = $this->userId($email);
        RbacService::clearCache();
    }

    private function userId(string $email): int
    {
        return (int) (new UserRepository())->findByEmail($email)['user_id'];
    }

    private function inRollback(callable $test): void
    {
        $sentinel = new RuntimeException('Roll back the test.');

        try {
            Database::transaction(static function () use ($test, $sentinel): void {
                $test();

                throw $sentinel;
            });
        } catch (RuntimeException $exception) {
            if ($exception !== $sentinel) {
                throw $exception;
            }
        }
    }
}
