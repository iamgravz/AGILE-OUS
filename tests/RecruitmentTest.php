<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\HrRequestRepository;
use App\Models\UserRepository;
use App\Models\VacancyRepository;
use App\Services\RbacService;
use App\Services\RecruitmentService;
use PHPUnit\Framework\TestCase;

/** HR requests and vacancies. Needs the seeded database. Every database test rolls back, so nothing is left behind. */
final class RecruitmentTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testRequestTransitionsMatchSectionEight(): void
    {
        $allowed = [['submitted', 'approved'], ['submitted', 'returned'], ['returned', 'submitted'], ['submitted', 'cancelled']];

        foreach (RecruitmentService::REQUEST_STATUSES as $from) {
            foreach (RecruitmentService::REQUEST_STATUSES as $to) {
                $this->assertSame(in_array([$from, $to], $allowed, true), RecruitmentService::canTransitionRequest($from, $to), "{$from} to {$to}");
            }
        }
    }

    public function testVacancyTransitionsMatchSectionEight(): void
    {
        $allowed = [['draft', 'open'], ['open', 'closed'], ['open', 'filled'], ['closed', 'open'], ['closed', 'filled'], ['draft', 'cancelled'], ['open', 'cancelled']];

        foreach (RecruitmentService::VACANCY_STATUSES as $from) {
            foreach (RecruitmentService::VACANCY_STATUSES as $to) {
                $this->assertSame(in_array([$from, $to], $allowed, true), RecruitmentService::canTransitionVacancy($from, $to), "{$from} to {$to}");
            }
        }
    }

    public function testSubmittingNotifiesTheMswHead(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('head@agile.test');
            $id = $this->newRequest();

            $this->assertSame('submitted', (new HrRequestRepository())->find($id)['status']);

            $statement = Database::connection()->prepare(
                "SELECT n.channel FROM notifications n JOIN email_templates t ON t.template_id = n.template_id
                 WHERE n.user_id = ? AND t.code = 'HR_REQUEST_SUBMITTED' AND n.link = ? ORDER BY n.channel"
            );
            $statement->execute([$this->userId('mswhead@agile.test'), "/recruitment/hr-requests/{$id}"]);
            $this->assertSame(['email', 'in_app'], $statement->fetchAll(PDO::FETCH_COLUMN));
        });
    }

    public function testAHeadCannotRequestForAnotherCommittee(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('head@agile.test');
            $this->expectException(ForbiddenException::class);
            (new RecruitmentService())->submitRequest($this->requestData($this->positionId('Events and External Relations')));
        });
    }

    public function testRequestsOfOtherCommitteesCannotBeSeen(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $id = (new RecruitmentService())->submitRequest($this->requestData($this->positionId('Membership and Student Welfare')));

            $this->signIn('head@agile.test');
            $visible = array_column((new RecruitmentService())->listRequests([], 1)['data'], 'request_id');
            $this->assertNotContains($id, array_map(intval(...), $visible));
            $this->expectException(ForbiddenException::class);
            (new RecruitmentService())->findRequest($id);
        });
    }

    public function testReturnedRequestCanBeEditedAndResubmitted(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('head@agile.test');
            $id = $this->newRequest();

            $this->signIn('mswhead@agile.test');
            (new RecruitmentService())->returnRequest($id, 'Please add detail');
            $this->assertSame('returned', (new HrRequestRepository())->find($id)['status']);

            $this->signIn('head@agile.test');
            (new RecruitmentService())->resubmitRequest($id, ['slots' => 3] + $this->requestData($this->positionId('Academics and Training')));

            $row = (new HrRequestRepository())->find($id);
            $this->assertSame('submitted', $row['status']);
            $this->assertSame(3, (int) $row['slots']);
            $this->assertNull($row['remarks']);
        });
    }

    public function testDecisionsNeedRemarks(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('head@agile.test');
            $id = $this->newRequest();

            $this->signIn('mswhead@agile.test');
            $this->expectException(DomainException::class);
            (new RecruitmentService())->approveRequest($id, '   ');
        });
    }

    public function testRequesterCanCancelOnlyWhileSubmitted(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('head@agile.test');
            $id = $this->newRequest();
            (new RecruitmentService())->cancelRequest($id);
            $this->assertSame('cancelled', (new HrRequestRepository())->find($id)['status']);

            $this->expectException(DomainException::class);
            (new RecruitmentService())->cancelRequest($id);
        });
    }

    public function testACommitteeHeadCannotCreateAVacancy(): void
    {
        $this->inRollback(function (): void {
            $id = $this->approvedRequest();

            $this->signIn('head@agile.test');
            $this->expectException(ForbiddenException::class);
            (new RecruitmentService())->createVacancy($id, $this->vacancyData(), true);
        });
    }

    public function testAVacancyNeedsAnApprovedRequest(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('head@agile.test');
            $id = $this->newRequest();

            $this->signIn('mswhead@agile.test');
            $this->expectException(DomainException::class);
            (new RecruitmentService())->createVacancy($id, $this->vacancyData(), true);
        });
    }

    public function testARequestHasOneLiveVacancyAndCancellingFreesIt(): void
    {
        $this->inRollback(function (): void {
            $id = $this->approvedRequest();
            $service = new RecruitmentService();
            $vacancyId = $service->createVacancy($id, $this->vacancyData(), false);

            try {
                $service->createVacancy($id, $this->vacancyData(), false);
                $this->fail('A second vacancy was created.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $service->cancelVacancy($vacancyId, 'Budget changed');
            $this->assertNotNull($service->requestForNewVacancy($id));
        });
    }

    public function testPublishedVacancyIsPublicAndCopiesTheRequest(): void
    {
        $this->inRollback(function (): void {
            $id = $this->approvedRequest();
            $vacancyId = (new RecruitmentService())->createVacancy($id, $this->vacancyData(), true);

            $open = (new VacancyRepository())->findOpen($vacancyId);
            $this->assertNotNull($open);
            $this->assertSame(2, (int) $open['slots']);
            $this->assertSame('Knows PHP', $open['qualifications']);
        });
    }

    public function testDraftIsNotPublic(): void
    {
        $this->inRollback(function (): void {
            $vacancyId = (new RecruitmentService())->createVacancy($this->approvedRequest(), $this->vacancyData(), false);

            $this->assertNull((new VacancyRepository())->findOpen($vacancyId));
        });
    }

    public function testPublishingNeedsAFutureClosingDate(): void
    {
        $this->inRollback(function (): void {
            $id = $this->approvedRequest();
            $this->expectException(DomainException::class);
            (new RecruitmentService())->createVacancy($id, ['opens_at' => '2020-01-01 00:00:00', 'closes_at' => '2020-02-01 00:00:00'] + $this->vacancyData(), true);
        });
    }

    public function testJobClosesPastVacanciesOnceAndNotifiesTheHead(): void
    {
        $this->inRollback(function (): void {
            $service = new RecruitmentService();
            $vacancyId = $service->createVacancy($this->approvedRequest(), $this->vacancyData(), true);
            $repository = new VacancyRepository();
            $repository->update($vacancyId, ['closes_at' => date('Y-m-d H:i:s', time() - 60)]);
            unset($_SESSION['user_id']);
            $before = $this->closedNotices();

            $service->closeDueVacancies(static function (string $line): void {
            });
            $this->assertSame('closed', $repository->find($vacancyId)['status']);
            $this->assertSame($before + 2, $this->closedNotices());

            $counts = $service->closeDueVacancies(static function (string $line): void {
            });
            $this->assertSame(['closed' => 0, 'filled' => 0, 'failed' => 0], $counts);
            $this->assertSame($before + 2, $this->closedNotices());
        });
    }

    public function testJobMarksFullVacancyFilledAndLeavesOthersAlone(): void
    {
        $this->inRollback(function (): void {
            $service = new RecruitmentService();
            $full = $service->createVacancy($this->approvedRequest(), $this->vacancyData(), true);
            $notDue = $service->createVacancy($this->approvedRequest(), $this->vacancyData(), true);
            $repository = new VacancyRepository();
            $repository->update($full, ['slots_filled' => 2]);
            unset($_SESSION['user_id']);

            $service->closeDueVacancies(static function (string $line): void {
            });
            $this->assertSame('filled', $repository->find($full)['status']);
            $this->assertSame('open', $repository->find($notDue)['status']);
        });
    }

    public function testClosedVacancyReopensWithANewDate(): void
    {
        $this->inRollback(function (): void {
            $service = new RecruitmentService();
            $vacancyId = $service->createVacancy($this->approvedRequest(), $this->vacancyData(), true);
            $repository = new VacancyRepository();
            $repository->update($vacancyId, ['status' => 'closed']);

            $newDate = date('Y-m-d H:i:s', time() + 86400 * 30);
            $service->reopenVacancy($vacancyId, $newDate);
            $row = $repository->find($vacancyId);
            $this->assertSame('open', $row['status']);
            $this->assertSame($newDate, $row['closes_at']);
        });
    }

    public function testACancelledVacancyCannotBeReopened(): void
    {
        $this->inRollback(function (): void {
            $service = new RecruitmentService();
            $vacancyId = $service->createVacancy($this->approvedRequest(), $this->vacancyData(), true);
            $service->cancelVacancy($vacancyId, 'No longer needed');

            $this->expectException(DomainException::class);
            $service->reopenVacancy($vacancyId, date('Y-m-d H:i:s', time() + 86400));
        });
    }

    private function closedNotices(): int
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id
             WHERE t.code = 'VACANCY_CLOSED' AND n.user_id = ?"
        );
        $statement->execute([$this->userId('head@agile.test')]);

        return (int) $statement->fetchColumn();
    }

    /** Leaves the MSW Committee Head signed in. */
    private function approvedRequest(): int
    {
        $this->signIn('head@agile.test');
        $id = $this->newRequest();

        $this->signIn('mswhead@agile.test');
        (new RecruitmentService())->approveRequest($id, 'Approved');

        return $id;
    }

    private function newRequest(): int
    {
        return (new RecruitmentService())->submitRequest($this->requestData($this->positionId('Academics and Training')));
    }

    /** @return array{position_id: int, slots: int, justification: string, qualifications: string} */
    private function requestData(int $positionId): array
    {
        return ['position_id' => $positionId, 'slots' => 2, 'justification' => 'Needed for tutorials', 'qualifications' => 'Knows PHP'];
    }

    /** @return array{title: string, description: string, opens_at: string, closes_at: string} */
    private function vacancyData(): array
    {
        return [
            'title' => 'Academic Committee Member',
            'description' => 'Helps run tutorials.',
            'opens_at' => date('Y-m-d H:i:s', time() - 3600),
            'closes_at' => date('Y-m-d H:i:s', time() + 86400 * 14),
        ];
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

    private function positionId(string $committee): int
    {
        $statement = Database::connection()->prepare(
            "SELECT p.position_id FROM positions p JOIN committees c ON c.committee_id = p.committee_id
             WHERE c.name = ? AND p.title = 'Committee Member'"
        );
        $statement->execute([$committee]);

        return (int) $statement->fetchColumn();
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
