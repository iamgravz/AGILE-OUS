<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\UserRepository;
use App\Models\WelfareCaseRepository;
use App\Services\RbacService;
use App\Services\ReportService;
use App\Services\SearchService;
use App\Services\WelfareService;
use PHPUnit\Framework\TestCase;

/** Reports and search rules. Needs the seeded database. Database tests roll back. */
final class ReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testCsvHasABomAndKeepsNames(): void
    {
        $csv = ReportService::toCsv(['columns' => ['Name', 'Note'], 'rows' => [['Peñaflor', 'a,"b"']]]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Peñaflor', $csv);
        $this->assertStringContainsString('"a,""b"""', $csv);
    }

    public function testCsvMakesFormulasHarmless(): void
    {
        foreach (['=1+1', '+1', '-1', '@SUM(A1)', "\tx"] as $cell) {
            $this->assertSame("'" . $cell, ReportService::safeCell($cell), $cell);
        }

        $this->assertSame('Normal', ReportService::safeCell('Normal'));
        $this->assertSame('2026-10-31', ReportService::safeCell('2026-10-31'));
    }

    public function testFiltersKeepOnlyValidValues(): void
    {
        $this->assertSame(
            ['from' => '2026-01-01', 'committee_id' => 3, 'type' => 'officer'],
            ReportService::filters('membership', ['from' => '2026-01-01', 'to' => "' OR 1=1 --", 'committee' => '3', 'type' => 'officer', 'status' => 'x'])
        );
        $this->assertSame([], ReportService::filters('membership', ['from' => '2026-02-31', 'committee' => 'abc', 'type' => 'boss']));
        $this->assertSame(['category_id' => 2], ReportService::filters('welfare', ['category' => '2', 'committee' => '3']));
        $this->assertSame(['term_id' => 9], ReportService::filters('eligibility', ['term' => '9', 'from' => '2026-01-01']));
    }

    public function testOnlyTheMswHeadGetsReports(): void
    {
        foreach (['president@agile.test', 'execomm@agile.test', 'mswmember@agile.test', 'head@agile.test', 'member@agile.test'] as $email) {
            $this->signIn($email);

            try {
                (new ReportService())->build('membership', []);
                $this->fail("{$email} got a report.");
            } catch (ForbiddenException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testWelfareReportIsCountsOnly(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('nomember@agile.test');
            $case = (new WelfareService())->submit(['category_id' => '6', 'subject' => 'SECRETSUBJECT', 'description' => 'SECRETWORDS', 'urgent' => false], null, []);
            $this->signIn('mswhead@agile.test');

            $report = (new ReportService())->build('welfare', []);
            $this->assertSame(['Category', 'Status', 'Priority', 'Cases'], $report['columns']);
            $dump = json_encode($report, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString($case['case_code'], $dump);
            $this->assertStringNotContainsString('Andrea', $dump);
            $this->assertStringNotContainsString('SECRET', $dump);
            $this->assertStringContainsString('Harassment', $dump);
        });
    }

    public function testExportIsAudited(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $count = static fn (): int => (int) Database::connection()->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'report.exported'")->fetchColumn();
            $before = $count();
            $file = (new ReportService())->export('membership', [], 'csv');

            $this->assertSame($before + 1, $count());
            $this->assertStringStartsWith("\xEF\xBB\xBF", $file['contents']);
            $this->assertStringEndsWith('.csv', $file['filename']);
            $this->assertStringStartsWith('%PDF', (new ReportService())->export('membership', [], 'pdf')['contents']);
            $this->assertSame($before + 2, $count());
        });
    }

    public function testSearchModulesFollowPermissions(): void
    {
        $expected = [
            'mswhead@agile.test' => ['applications', 'members', 'vacancies', 'content', 'welfare'],
            'mswmember@agile.test' => ['applications', 'members', 'content'],
            'head@agile.test' => ['applications', 'content'],
            'president@agile.test' => ['welfare'],
            'execomm@agile.test' => [],
            'member@agile.test' => [],
            'nomember@agile.test' => [],
        ];

        foreach ($expected as $email => $modules) {
            $this->signIn($email);
            $this->assertSame($modules, SearchService::modules(), $email);
        }
    }

    public function testWelfareSearchIsByCodeOnly(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('nomember@agile.test');
            $case = (new WelfareService())->submit(['category_id' => '1', 'subject' => 'Findable zebra', 'description' => 'Words', 'urgent' => false], null, []);
            $repository = new WelfareCaseRepository();
            $rows = $repository->searchByCode($case['case_code'], 5);

            $this->assertCount(1, $rows);
            $this->assertSame(['case_id', 'case_code', 'status'], array_keys($rows[0]));
            $this->assertCount(1, $repository->searchByCode(strtolower(substr($case['case_code'], 0, 6)), 5));
            $this->assertSame([], $repository->searchByCode('zebra', 5));
            $this->assertSame([], $repository->searchByCode("' OR 1=1 --", 5));
        });
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
