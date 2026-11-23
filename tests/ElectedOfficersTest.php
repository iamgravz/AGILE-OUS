<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\ElectedOfficerService;
use App\Services\MembershipService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** Administration, Elected officers: the Executive Board and Year-Level Representatives (Article VI). Rolls back. */
final class ElectedOfficersTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testSeatsAreTheExecutiveBoardAndTheYearLevelCouncil(): void
    {
        $this->signIn('mswhead@agile.test');
        $seats = (new ElectedOfficerService())->seats();
        $titles = array_column($seats, 'title');

        foreach (['President', 'General Secretary', 'Auditor', '1st Year Representative', '4th Year Representative'] as $title) {
            $this->assertContains($title, $titles);
        }

        $this->assertNotContains('Committee Head', $titles);
        $this->assertNotContains('Editor-in-Chief', $titles);
        $this->assertSame(['Executive Board', 'Year-Level Representatives'], array_values(array_unique(array_column($seats, 'group'))));
    }

    public function testRecordingCreatesTheAccountMemberAndRole(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $president = $this->vacate('', 'President');
            $rep = $this->vacate('Year-Level Council', '4th Year Representative');
            $service = new ElectedOfficerService();

            $userId = $service->record($president, $this->person('new.president@agile.test', '2023-99001-OU-0', '3rd Year'));
            $user = (new UserRepository())->findWithRole($userId);
            $member = (new MemberRepository())->findByUser($userId);
            $this->assertSame('president', $user['role_slug']);
            $this->assertSame($president, (int) $member['position_id']);
            $this->assertSame('officer', $member['membership_type']);

            $repId = $service->record($rep, $this->person('new.rep@agile.test', '2023-99002-OU-0', '4th Year'));
            $this->assertSame('general_member', (new UserRepository())->findWithRole($repId)['role_slug']);
            $this->assertSame($rep, (int) (new MemberRepository())->findByUser($repId)['position_id']);

            try {
                $service->record($rep, $this->person('another.rep@agile.test', '2023-99003-OU-0', '4th Year'));
                $this->fail('A filled seat cannot take a second officer.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('already held', $exception->getMessage());
            }
        });
    }

    public function testAnExistingMemberKeepsTheirAccount(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $seat = $this->vacate('Year-Level Council', '1st Year Representative');
            $before = (new UserRepository())->findByEmail('member@agile.test');

            $userId = (new ElectedOfficerService())->record($seat, $this->person('member@agile.test', '', '1st Year'));
            $this->assertSame((int) $before['user_id'], $userId);
            $this->assertSame($seat, (int) (new MemberRepository())->findByUser($userId)['position_id']);
        });
    }

    public function testOtherPositionsAndOtherRolesAreRefused(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $head = (int) Database::connection()->query("SELECT position_id FROM positions WHERE role_type = 'committee_head' LIMIT 1")->fetchColumn();

            try {
                (new ElectedOfficerService())->record($head, $this->person('someone@agile.test', '2023-99004-OU-0', '3rd Year'));
                $this->fail('Committee positions are applied for, not recorded here.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('Executive Board or Year-Level', $exception->getMessage());
            }
        });

        $this->signIn('mswmember@agile.test');
        $this->expectException(ForbiddenException::class);
        (new ElectedOfficerService())->seats();
    }

    public function testArticleSixQualificationsAreChecked(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $president = $this->vacate('', 'President');
            $secretary = $this->vacate('', 'General Secretary');
            $rep = $this->vacate('Year-Level Council', '2nd Year Representative');
            $service = new ElectedOfficerService();

            $this->assertRefused('at least 3rd year', fn () => $service->record($president, $this->person('a@agile.test', '2023-99011-OU-0', '2nd Year')));
            $this->assertRefused('at least 2nd year', fn () => $service->record($secretary, $this->person('b@agile.test', '2023-99012-OU-0', '1st Year')));
            $this->assertRefused('must be in 2nd year', fn () => $service->record($rep, $this->person('c@agile.test', '2023-99013-OU-0', '3rd Year')));
            $this->assertRefused('confirm the Article VI', fn () => $service->record($secretary, ['confirmations' => ['full_load']] + $this->person('d@agile.test', '2023-99014-OU-0', '2nd Year')));

            $this->assertGreaterThan(0, $service->record($secretary, $this->person('e@agile.test', '2023-99015-OU-0', '2nd Year')));
        });
    }

    public function testReElectionUpToTheTermLimit(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $seat = $this->vacate('', 'Treasurer');
            $service = new ElectedOfficerService();
            $term = fn (string $year): array => ['academic_year' => $year] + $this->person('treasurer@agile.test', '2023-99021-OU-0', '3rd Year');

            $userId = $service->record($seat, $term('2025-2026'));
            $this->assertSame($userId, $service->record($seat, $term('2026-2027')), 'The holder is re-elected.');
            $this->assertRefused('already recorded', fn () => $service->record($seat, $term('2026-2027')));
            $this->assertRefused('2 consecutive terms', fn () => $service->record($seat, $term('2027-2028')));
            $this->assertRefused('already held', fn () => $service->record($seat, ['email' => 'other@agile.test', 'student_no' => '2023-99022-OU-0'] + $term('2027-2028')));

            $terms = (int) Database::connection()->query("SELECT COUNT(*) FROM officer_terms WHERE position_id = {$seat}")->fetchColumn();
            $this->assertSame(2, $terms);
        });
    }

    private function assertRefused(string $message, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a refusal: {$message}");
        } catch (DomainException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function person(string $email, string $studentNo, string $yearLevel): array
    {
        return ['first_name' => 'Sample', 'last_name' => 'Officer', 'email' => $email, 'student_no' => $studentNo, 'contact_no' => '09171234567',
            'reason' => 'Elected in the test election', 'year_level' => $yearLevel, 'section' => 'BSITOUMN 3-1', 'academic_year' => '2026-2027', 'confirmations' => ['full_load', 'clean_grades']];
    }

    /** Frees a seat the sample seeder filled, as an ended term would. */
    private function vacate(string $committee, string $title): int
    {
        $statement = Database::connection()->prepare(
            "SELECT p.position_id FROM positions p LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.title = ? AND ((? = '' AND p.committee_id IS NULL) OR c.name = ?)"
        );
        $statement->execute([$title, $committee, $committee]);
        $id = (int) $statement->fetchColumn();
        $this->assertGreaterThan(0, $id, "{$title} exists");
        Database::connection()->prepare("UPDATE members SET position_id = NULL, membership_type = 'general', role_status = 'expired' WHERE position_id = ?")->execute([$id]);
        // The sample seeder records the holders' terms; a vacated seat starts the test with none.
        Database::connection()->prepare('DELETE FROM officer_terms WHERE position_id = ?')->execute([$id]);

        return $id;
    }

    private function signIn(string $email): void
    {
        $_SESSION['user_id'] = (int) (new UserRepository())->findByEmail($email)['user_id'];
        RbacService::clearCache();
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
