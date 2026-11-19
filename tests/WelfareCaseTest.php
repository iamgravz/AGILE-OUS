<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\CaseNoteRepository;
use App\Models\UserRepository;
use App\Models\WelfareCaseRepository;
use App\Services\EncryptionService;
use App\Services\RbacService;
use App\Services\WelfareService;
use PHPUnit\Framework\TestCase;

/** The case workspace. Needs the seeded database. Every test rolls back, so nothing is left behind. */
final class WelfareCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testOverdueRule(): void
    {
        $old = date('Y-m-d H:i:s', time() - 25 * 3600);
        $new = date('Y-m-d H:i:s', time() - 3600);
        $case = ['priority' => 'medium', 'student_marked_urgent' => 0, 'category_id' => 6, 'acknowledged_at' => null, 'status' => 'submitted', 'submitted_at' => $old];

        $this->assertTrue(WelfareService::isOverdue($case));
        $this->assertTrue(WelfareService::isOverdue(['category_id' => 1, 'student_marked_urgent' => 1] + $case));
        $this->assertTrue(WelfareService::isOverdue(['category_id' => 1, 'priority' => 'urgent'] + $case));
        $this->assertFalse(WelfareService::isOverdue(['category_id' => 1] + $case));
        $this->assertFalse(WelfareService::isOverdue(['submitted_at' => $new] + $case));
        $this->assertFalse(WelfareService::isOverdue(['acknowledged_at' => $new] + $case));
        $this->assertFalse(WelfareService::isOverdue(['status' => 'closed'] + $case));
    }

    public function testEveryOtherRoleIsRefusedInsideTheService(): void
    {
        $this->inRollback(function (): void {
            $id = $this->newCase();

            foreach (['mswmember@agile.test', 'execomm@agile.test', 'head@agile.test', 'member@agile.test', 'nomember@agile.test'] as $email) {
                $this->signIn($email);

                foreach ([
                    fn () => (new WelfareService())->openCase($id),
                    fn () => (new WelfareService())->queue([], 1),
                    fn () => (new WelfareService())->addNote($id, 'x', false),
                    fn () => (new WelfareService())->close($id, 'x'),
                ] as $call) {
                    try {
                        $call();
                        $this->fail("{$email} got through.");
                    } catch (ForbiddenException) {
                        $this->addToAssertionCount(1);
                    }
                }
            }
        });
    }

    public function testThePresidentHandlesButCannotAssignCloseOrDispose(): void
    {
        $this->inRollback(function (): void {
            $id = $this->newCase();
            $this->signIn('president@agile.test');
            $service = new WelfareService();

            $this->assertNotNull($service->openCase($id));
            $service->addNote($id, 'A note', false);
            $service->addReferral($id, 'Guidance', 'Remarks');
            $service->addFollowup($id, date('Y-m-d'), (int) current_user_id());

            foreach ([
                fn () => $service->assign($id, (int) current_user_id()),
                fn () => $service->close($id, 'x'),
                fn () => $service->resolve($id, 'a', 'resolved', 's'),
                fn () => $service->classify($id, 1, 'high', 'r'),
                fn () => $service->setDisposition($id, 1, 'kept'),
            ] as $call) {
                try {
                    $call();
                    $this->fail('The President got through.');
                } catch (ForbiddenException) {
                    $this->addToAssertionCount(1);
                }
            }
        });
    }

    public function testFirstNoteStartsHandlingAndIsEncrypted(): void
    {
        $this->inRollback(function (): void {
            $id = $this->newCase();
            $this->signIn('mswhead@agile.test');
            (new WelfareService())->addNote($id, 'Private note', false);

            $this->assertSame('in_progress', $this->caseStatus($id));
            $note = (new CaseNoteRepository())->forCase($id)[0];
            $this->assertStringNotContainsString('Private', $note['note']);
            $this->assertSame('Private note', (new EncryptionService())->decrypt($note['note']));
        });
    }

    public function testOnlyAVisibleNoteTellsTheSubmitter(): void
    {
        $this->inRollback(function (): void {
            $id = $this->newCase();
            $this->signIn('mswhead@agile.test');
            $service = new WelfareService();
            $service->addNote($id, 'Internal', false);
            $this->assertSame(0, $this->notices('WELFARE_UPDATE', $id));
            $service->addNote($id, 'Visible', true);
            $this->assertSame(2, $this->notices('WELFARE_UPDATE', $id));
        });
    }

    public function testReferralMovesThroughProgressToReferred(): void
    {
        $this->inRollback(function (): void {
            $id = $this->newCase();
            $this->signIn('mswhead@agile.test');
            (new WelfareService())->addReferral($id, 'Guidance', 'Remarks');

            $this->assertSame('referred', $this->caseStatus($id));
            $statuses = array_column((new WelfareCaseRepository())->statusChanges($id), 'new_value');
            $this->assertSame(['submitted', 'in_progress', 'referred'], $statuses);
        });
    }

    public function testCloseOnlyFromResolvedThenNothingChanges(): void
    {
        $this->inRollback(function (): void {
            $id = $this->newCase();
            $this->signIn('mswhead@agile.test');
            $service = new WelfareService();

            try {
                $service->close($id, 'Too early');
                $this->fail('A case closed before it was resolved.');
            } catch (DomainException) {
                $this->assertSame('submitted', $this->caseStatus($id));
            }

            $service->resolve($id, 'Did things', 'resolved', 'All good');
            $this->assertSame('resolved', $this->caseStatus($id));
            $service->close($id, 'Done');
            $this->assertSame('closed', $this->caseStatus($id));
            $this->assertSame(2, $this->notices('WELFARE_CLOSED', $id));

            foreach ([
                fn () => $service->addNote($id, 'x', false),
                fn () => $service->addReferral($id, 'x', 'y'),
                fn () => $service->addFollowup($id, date('Y-m-d'), (int) current_user_id()),
                fn () => $service->classify($id, 2, 'low', 'r'),
                fn () => $service->assign($id, (int) current_user_id()),
                fn () => $service->acknowledge($id),
                fn () => $service->resolve($id, 'a', 'resolved', 's'),
                fn () => $service->close($id, 'again'),
            ] as $call) {
                try {
                    $call();
                    $this->fail('A closed case changed.');
                } catch (DomainException) {
                    $this->addToAssertionCount(1);
                }
            }
        });
    }

    public function testAssignIsLimitedToSelfOrAPresident(): void
    {
        $this->inRollback(function (): void {
            $id = $this->newCase();
            $this->signIn('mswhead@agile.test');
            $service = new WelfareService();
            $service->assign($id, (int) current_user_id());
            $service->assign($id, $this->userId('president@agile.test'));
            $this->assertSame($this->userId('president@agile.test'), (int) (new WelfareCaseRepository())->find($id)['assigned_to']);

            $this->expectException(DomainException::class);
            $service->assign($id, $this->userId('mswmember@agile.test'));
        });
    }

    public function testSearchFindsTheEncryptedSubject(): void
    {
        $this->inRollback(function (): void {
            $id = $this->newCase();
            $this->signIn('mswhead@agile.test');
            $service = new WelfareService();
            $ids = static fn (array $result): array => array_map(intval(...), array_column($result['data'], 'case_id'));

            $this->assertContains($id, $ids($service->queue(['q' => 'zebra'], 1, 500)));
            $this->assertNotContains($id, $ids($service->queue(['q' => 'nothing-like-this'], 1, 500)));
        });
    }

    public function testRemindersSendOnce(): void
    {
        $this->inRollback(function (): void {
            $log = static function (string $line): void {
            };
            $id = $this->newCase(6);
            $repository = new WelfareCaseRepository();
            $repository->update($id, ['submitted_at' => date('Y-m-d H:i:s', time() - 25 * 3600)]);
            $this->signIn('mswhead@agile.test');
            $service = new WelfareService();
            $before = $this->notices('WELFARE_URGENT_ALERT', $id);
            $service->addFollowup($id, date('Y-m-d'), (int) current_user_id());
            unset($_SESSION['user_id']);

            $service->sendOverdueAlerts($log);
            $this->assertSame($before + 4, $this->notices('WELFARE_URGENT_ALERT', $id));
            $this->assertSame(['sent' => 0, 'failed' => 0], $service->sendOverdueAlerts($log));

            $service->sendFollowupReminders($log);
            $this->assertSame(['sent' => 0, 'failed' => 0], $service->sendFollowupReminders($log));
        });
    }

    private function newCase(int $category = 1): int
    {
        $this->signIn('nomember@agile.test');

        return (int) (new WelfareService())->submit(['category_id' => (string) $category, 'subject' => 'A zebra problem', 'description' => 'Words', 'urgent' => false], null, [])['case_id'];
    }

    private function caseStatus(int $id): string
    {
        return (new WelfareCaseRepository())->find($id)['status'];
    }

    private function notices(string $code, int $caseId): int
    {
        $case = (new WelfareCaseRepository())->find($caseId);
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE t.code = ? AND n.payload_json LIKE ?'
        );
        $statement->execute([$code, '%' . $case['case_code'] . '%']);

        return (int) $statement->fetchColumn();
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
