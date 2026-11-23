<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\DemoDataRemover;
use PHPUnit\Framework\TestCase;

/**
 * Step 24: database/remove_demo.php. The test database holds the demo seed, so the plan is checked against it, and
 * removal runs with $keep = false, which rolls back after every check has passed.
 */
final class DemoDataRemoverTest extends TestCase
{
    public function testThePlanCoversEveryDemoPersonAndNothingElse(): void
    {
        $db = Database::connection();
        $plan = (new DemoDataRemover($db))->plan(allowNoAdmin: true);

        $demoUsers = (int) $db->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@agile.test'")->fetchColumn();
        $demoApplicants = (int) $db->query("SELECT COUNT(*) FROM applicants WHERE email LIKE '%@agile.test'")->fetchColumn();

        $this->assertSame([], $plan['problems']);
        $this->assertGreaterThan(0, $demoUsers);
        $this->assertCount($demoUsers, $plan['delete']['users']);
        $this->assertCount($demoApplicants, $plan['delete']['applicants']);
        $this->assertArrayNotHasKey('roles', $plan['delete']);
        $this->assertArrayNotHasKey('committees', $plan['delete']);
        $this->assertArrayNotHasKey('positions', $plan['delete']);
        // The demo posts are written by the demo MSW Committee Head.
        $this->assertArrayHasKey('content_posts', $plan['madeByDemo']);
    }

    public function testItRefusesWhenNoRealMswHeadWouldBeLeft(): void
    {
        $plan = (new DemoDataRemover(Database::connection()))->plan();

        $this->assertNotEmpty(array_filter($plan['problems'], static fn (string $p): bool => str_contains($p, 'MSW Committee Head')));
        $this->expectException(RuntimeException::class);
        (new DemoDataRemover(Database::connection()))->apply($plan);
    }

    public function testRemovingLeavesNoBrokenLinks(): void
    {
        $db = Database::connection();
        $remover = new DemoDataRemover($db);
        $plan = $remover->plan(allowNoAdmin: true);
        $before = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

        $result = $remover->apply($plan, keep: false);

        $this->assertSame(count($plan['delete']['users']), $result['removed']['users']);
        $this->assertSame([], $result['moved']);
        // Rolled back: the demo data is still there for the other tests.
        $this->assertSame($before, (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testARealApplicationDecidedByADemoAccountIsKept(): void
    {
        $this->withRealApplication(function (PDO $db, int $applicantId, int $applicationId): void {
            $db->exec("UPDATE membership_applications SET status = 'approved', decided_by = (SELECT user_id FROM users WHERE email = 'mswhead@agile.test'), decided_at = NOW() WHERE application_id = {$applicationId}");

            $plan = (new DemoDataRemover($db))->plan(allowNoAdmin: true);

            $this->assertSame([], $plan['problems']);
            $this->assertNotContains($applicantId, $plan['delete']['applicants']);
            $this->assertNotContains($applicationId, $plan['delete']['membership_applications'] ?? []);
            $cleared = array_values(array_filter($plan['clear'], static fn (array $c): bool => $c['table'] === 'membership_applications' && $c['column'] === 'decided_by'));
            $this->assertContains($applicationId, $cleared[0]['ids'] ?? []);
        });
    }

    public function testADemoEvaluationOfARealApplicationStopsEverything(): void
    {
        $this->withRealApplication(function (PDO $db, int $applicantId, int $applicationId): void {
            $db->exec("INSERT INTO evaluations (application_id, evaluator_id, score, rubric_json, recommendation, remarks)
                SELECT {$applicationId}, user_id, 80, '{}', 'recommend', 'Demo review' FROM users WHERE email = 'head@agile.test'");

            $plan = (new DemoDataRemover($db))->plan(allowNoAdmin: true);

            $this->assertNotEmpty(array_filter($plan['problems'], static fn (string $p): bool => str_starts_with($p, 'evaluations #')));
        });
    }

    /** @param callable(PDO, int, int): void $test */
    private function withRealApplication(callable $test): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $db->exec("INSERT INTO applicants (email, first_name, last_name, student_no) VALUES ('real.person@example.test', 'Real', 'Person', '2030-00999-OU-0')");
            $applicantId = (int) $db->lastInsertId();
            $db->exec("INSERT INTO membership_applications (applicant_id, tracking_code, status, submitted_at) VALUES ({$applicantId}, 'REALAPPTEST1', 'submitted', NOW())");
            $test($db, $applicantId, (int) $db->lastInsertId());
        } finally {
            $db->rollBack();
        }
    }
}
