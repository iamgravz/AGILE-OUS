<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\AcademicTermRepository;
use App\Models\CredentialRepository;
use App\Models\EligibilityScreeningRepository;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\CredentialService;
use App\Services\EligibilityService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** Eligibility screening. Needs the seeded database. Every database test rolls back, including PDFs. */
final class EligibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testTransitionsMatchSectionEight(): void
    {
        $allowed = [
            ['pending', 'submitted'], ['submitted', 'eligible'], ['submitted', 'flagged'], ['pending', 'incomplete'],
            ['incomplete', 'flagged'], ['flagged', 'confirmed_ineligible'], ['flagged', 'cleared'],
        ];

        foreach (EligibilityService::STATUSES as $from) {
            foreach (EligibilityService::STATUSES as $to) {
                $this->assertSame(in_array([$from, $to], $allowed, true), EligibilityService::canTransition($from, $to), "{$from} to {$to}");
            }
        }
    }

    public function testBrNineResult(): void
    {
        $this->assertSame('eligible', EligibilityService::resultFor(false, true));
        $this->assertSame('flagged', EligibilityService::resultFor(true, true));
        $this->assertSame('flagged', EligibilityService::resultFor(false, false));
        $this->assertSame('flagged', EligibilityService::resultFor(true, false));
    }

    public function testTermRules(): void
    {
        $ok = ['academic_year' => '2026-2027', 'semester' => '1st', 'screening_deadline' => '2026-12-01', 'grace_days' => '7'];
        $this->assertSame([], EligibilityService::termErrors($ok));
        $this->assertArrayHasKey('academic_year', EligibilityService::termErrors(['academic_year' => '2026-2028'] + $ok));
        $this->assertArrayHasKey('semester', EligibilityService::termErrors(['semester' => '3rd'] + $ok));
        $this->assertArrayHasKey('screening_deadline', EligibilityService::termErrors(['screening_deadline' => '2026-02-31'] + $ok));
        $this->assertArrayHasKey('grace_days', EligibilityService::termErrors(['grace_days' => 'x'] + $ok));
        $this->assertSame('2026-12-08', EligibilityService::lastUploadDate(['screening_deadline' => '2026-12-01', 'grace_days' => 7]));
    }

    public function testOnlyOneTermIsCurrent(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $service = new EligibilityService();
            $service->createTerm(['academic_year' => '2090-2091', 'semester' => '1st', 'screening_deadline' => '2090-12-01', 'grace_days' => '7'], true);
            $second = $service->createTerm(['academic_year' => '2090-2091', 'semester' => '2nd', 'screening_deadline' => '2091-05-01', 'grace_days' => '7'], true);

            $this->assertCount(1, (new AcademicTermRepository())->findBy(['is_current' => 1]));
            $this->assertSame($second, (int) (new AcademicTermRepository())->current()['term_id']);

            $this->expectException(DomainException::class);
            $service->createTerm(['academic_year' => '2090-2091', 'semester' => '2nd', 'screening_deadline' => '2091-05-01', 'grace_days' => '7'], false);
        });
    }

    public function testScreeningCoversOnlyOfficers(): void
    {
        $this->inRollback(function (): void {
            $term = $this->startedTerm();
            $covered = array_column((new EligibilityScreeningRepository())->search(['term_id' => $term], 1, 500)['data'], 'user_id');

            foreach (['head@agile.test', 'execomm@agile.test', 'mswmember@agile.test', 'mswdeputy@agile.test', 'mswhead@agile.test', 'president@agile.test'] as $email) {
                $this->assertContains($this->userId($email), array_map(intval(...), $covered), $email);
            }

            $this->assertNotContains($this->userId('member@agile.test'), array_map(intval(...), $covered));
        });
    }

    public function testStartingAgainDuplicatesNothing(): void
    {
        $this->inRollback(function (): void {
            $this->startedTerm();
            $this->expectException(DomainException::class);
            (new EligibilityService())->startScreening();
        });
    }

    public function testFailedSubjectFlagsThenConfirmIneligibleExpiresTheRole(): void
    {
        $this->inRollback(function (): void {
            $term = $this->startedTerm();
            $userId = $this->userId('head@agile.test');
            $member = (new MemberRepository())->findByUser($userId);
            $id = $this->submitted($term, (int) $member['member_id']);
            (new CredentialService())->issueFor((int) $member['member_id']);
            $before = (new CredentialRepository())->forMember((int) $member['member_id']);
            $service = new EligibilityService();

            $this->assertSame('flagged', $service->verify($id, true, true));
            $row = (new EligibilityScreeningRepository())->find($id);
            $this->assertNotNull($row['flagged_at']);
            $this->assertSame(1, (int) $row['has_failed_subject']);

            $service->decide($id, 'confirm_ineligible', 'Failed a subject');

            $after = (new MemberRepository())->findDetailed((int) $member['member_id']);
            $this->assertSame('active', $after['status']);
            $this->assertSame('general_member', $after['role_slug']);
            $this->assertSame('general', $after['membership_type']);
            $this->assertNull($after['position_id']);
            $this->assertSame('expired', $after['role_status']);
            $this->assertNotNull($after['role_expired_at']);
            $this->assertSame('confirmed_ineligible', (new EligibilityScreeningRepository())->find($id)['result']);

            $credentials = (new CredentialRepository())->forMember((int) $member['member_id']);
            $this->assertCount(count($before) + 2, $credentials);
            $this->assertCount(2, array_filter($credentials, static fn (array $c): bool => $c['revoked_at'] === null));
            $this->assertGreaterThanOrEqual(2, $this->noticeCount($userId, 'ROLE_EXPIRED'));
        });
    }

    public function testClearKeepsTheRole(): void
    {
        $this->inRollback(function (): void {
            $term = $this->startedTerm();
            $member = (new MemberRepository())->findByUser($this->userId('head@agile.test'));
            $id = $this->submitted($term, (int) $member['member_id']);
            $service = new EligibilityService();
            $service->verify($id, false, false);
            $service->decide($id, 'clear', 'It was an error');

            $after = (new MemberRepository())->findDetailed((int) $member['member_id']);
            $this->assertSame('committee_head', $after['role_slug']);
            $this->assertSame('active', $after['role_status']);
            $this->assertSame('cleared', (new EligibilityScreeningRepository())->find($id)['result']);
        });
    }

    public function testRemarksAreRequired(): void
    {
        $this->inRollback(function (): void {
            $term = $this->startedTerm();
            $id = $this->submitted($term, (int) (new MemberRepository())->findByUser($this->userId('head@agile.test'))['member_id']);
            $service = new EligibilityService();
            $service->verify($id, true, true);
            $this->expectException(DomainException::class);
            $service->decide($id, 'clear', '  ');
        });
    }

    public function testTheMswHeadCannotReviewTheirOwnScreening(): void
    {
        $this->inRollback(function (): void {
            $term = $this->startedTerm();
            $member = (new MemberRepository())->findByUser($this->userId('mswhead@agile.test'));
            $id = $this->submitted($term, (int) $member['member_id']);

            try {
                (new EligibilityService())->verify($id, false, true);
                $this->fail('The MSW Head verified their own screening.');
            } catch (ForbiddenException) {
                $this->addToAssertionCount(1);
            }

            $this->signIn('president@agile.test');
            $this->assertSame('eligible', (new EligibilityService())->verify($id, false, true));
        });
    }

    public function testThePresidentReviewsOnlyTheMswHead(): void
    {
        $this->inRollback(function (): void {
            $term = $this->startedTerm();
            $id = $this->submitted($term, (int) (new MemberRepository())->findByUser($this->userId('head@agile.test'))['member_id']);
            $own = $this->submitted($term, (int) (new MemberRepository())->findByUser($this->userId('president@agile.test'))['member_id']);
            $this->signIn('president@agile.test');
            $service = new EligibilityService();

            foreach ([$id, $own] as $screening) {
                try {
                    $service->verify($screening, false, true);
                    $this->fail('The President reviewed someone other than the MSW Head.');
                } catch (ForbiddenException) {
                    $this->addToAssertionCount(1);
                }
            }

            $this->signIn('mswhead@agile.test');
            $this->assertSame('eligible', (new EligibilityService())->verify($own, false, true));
        });
    }

    public function testOtherRolesAreRefused(): void
    {
        $this->inRollback(function (): void {
            $this->startedTerm();

            foreach (['head@agile.test', 'mswmember@agile.test', 'execomm@agile.test', 'member@agile.test'] as $email) {
                $this->signIn($email);

                try {
                    (new EligibilityService())->startScreening();
                    $this->fail("{$email} started a screening.");
                } catch (ForbiddenException) {
                    $this->addToAssertionCount(1);
                }
            }
        });
    }

    public function testJobRemindsOnceAWeekdayAndFlagsAfterGrace(): void
    {
        $this->inRollback(function (): void {
            $log = static function (string $line): void {
            };
            $term = $this->startedTerm();
            $userId = $this->userId('head@agile.test');
            $service = new EligibilityService();
            unset($_SESSION['user_id']);

            // Asked today when the screening started, so nothing more goes out today.
            $this->assertSame(['reminded' => 0, 'flagged' => 0, 'failed' => 0], $service->runReminders($log));

            (new AcademicTermRepository())->update($term, ['screening_deadline' => date('Y-m-d', time() - 10 * 86400), 'grace_days' => 7]);
            $counts = $service->runReminders($log);
            $this->assertGreaterThan(0, $counts['flagged']);
            $member = (new MemberRepository())->findByUser($userId);
            $row = (new EligibilityScreeningRepository())->findFor($term, (int) $member['member_id']);
            $this->assertSame('flagged', $row['result']);
            $this->assertNotNull($row['flagged_at']);
            $this->assertSame(['reminded' => 0, 'flagged' => 0, 'failed' => 0], $service->runReminders($log));
        });
    }

    public function testRoleExpirationReminderOnlyOnItsDays(): void
    {
        $this->inRollback(function (): void {
            $log = static function (string $line): void {
            };
            $term = $this->startedTerm();
            $service = new EligibilityService();
            unset($_SESSION['user_id']);
            $terms = new AcademicTermRepository();

            $terms->update($term, ['screening_deadline' => date('Y-m-d', time() + 3 * 86400)]);
            $this->assertSame(0, $service->runRoleExpirationReminders($log)['sent']);

            $terms->update($term, ['screening_deadline' => date('Y-m-d', strtotime('+7 days'))]);
            $this->assertGreaterThan(0, $service->runRoleExpirationReminders($log)['sent']);
            $this->assertSame(0, $service->runRoleExpirationReminders($log)['sent']);
        });
    }

    private function startedTerm(): int
    {
        $this->signIn('mswhead@agile.test');
        $service = new EligibilityService();
        $id = $service->createTerm(['academic_year' => '2090-2091', 'semester' => 'summer', 'screening_deadline' => date('Y-m-d', time() + 60 * 86400), 'grace_days' => '7'], true);
        $service->startScreening();

        return $id;
    }

    /** Skips the upload, which needs a real HTTP request. */
    private function submitted(int $termId, int $memberId): int
    {
        $repository = new EligibilityScreeningRepository();
        $row = $repository->findFor($termId, $memberId);
        $repository->update((int) $row['screening_id'], ['result' => 'submitted']);

        return (int) $row['screening_id'];
    }

    private function noticeCount(int $userId, string $code): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE n.user_id = ? AND t.code = ?'
        );
        $statement->execute([$userId, $code]);

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
