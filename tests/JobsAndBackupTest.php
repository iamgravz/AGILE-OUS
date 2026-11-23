<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\ApplicationAccessLinkRepository;
use App\Models\JobRunRepository;
use App\Models\NotificationRepository;
use App\Models\UserRepository;
use App\Services\BackupService;
use App\Services\MembershipService;
use App\Services\RbacService;
use App\Services\SystemHealthService;
use PHPUnit\Framework\TestCase;

/** Step 21. Backups go to a temporary folder here, never to storage/backups. */
final class JobsAndBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/agile_backup_test_' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (array_diff(scandir($this->directory) ?: [], ['.', '..']) as $file) {
            unlink("{$this->directory}/{$file}");
        }

        rmdir($this->directory);
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testPruningRemovesOnlyOldBackupsOfItsOwn(): void
    {
        $today = strtotime('2026-11-01 02:00:00');

        foreach (['agile_2026-09-01.sql.gz', 'agile_2026-10-01.sql.gz', 'agile_2026-10-02.sql.gz', 'agile_2026-11-01.sql.gz', 'before_msw_head_restore_20261031_133405.sql', 'agile_notes.sql.gz', 'agile_2020-01-01.sql.gz.tmp', '.gitkeep'] as $name) {
            file_put_contents("{$this->directory}/{$name}", 'x');
        }

        $removed = (new BackupService($this->directory))->prune($today);
        sort($removed);

        $this->assertSame(['agile_2026-09-01.sql.gz', 'agile_2026-10-01.sql.gz'], $removed);
        $this->assertFileExists("{$this->directory}/before_msw_head_restore_20261031_133405.sql");
        $this->assertFileExists("{$this->directory}/agile_notes.sql.gz");
        $this->assertFileExists("{$this->directory}/agile_2020-01-01.sql.gz.tmp");
        $this->assertFileExists("{$this->directory}/.gitkeep");
        $this->assertFileExists("{$this->directory}/agile_2026-10-02.sql.gz");
    }

    public function testABackupIsARealGzippedDump(): void
    {
        $result = (new BackupService($this->directory))->run();

        $this->assertSame('agile_' . date('Y-m-d') . '.sql.gz', $result['file']);
        $this->assertGreaterThan(1000, $result['size']);
        $contents = (string) file_get_contents('compress.zlib://' . "{$this->directory}/{$result['file']}");
        $this->assertStringContainsString('CREATE TABLE `users`', $contents);
        $this->assertStringContainsString('Dump completed', $contents);
        $this->assertSame([], glob("{$this->directory}/*.tmp"));
        $this->assertSame($result['file'], (new BackupService($this->directory))->latest()['file']);
    }

    public function testAFailedDumpLeavesTheLastGoodBackupAlone(): void
    {
        $file = 'agile_' . date('Y-m-d') . '.sql.gz';
        file_put_contents("{$this->directory}/{$file}", 'the last good backup');
        $service = new BackupService($this->directory, $this->directory . '/no-such-program.exe');

        try {
            $service->run();
            $this->fail('A backup with no mysqldump reported success.');
        } catch (Throwable) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('the last good backup', file_get_contents("{$this->directory}/{$file}"));
        $this->assertSame([], glob("{$this->directory}/*.tmp"));
    }

    public function testRetryRequeuesFailedEmailsButNotErasedOnes(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $this->signIn('mswhead@agile.test');
            $notifications = new NotificationRepository();
            $templateId = (int) $db->query("SELECT template_id FROM email_templates WHERE code = 'APP_REJECTED'")->fetchColumn();
            $good = $notifications->insert(['recipient_email' => 'a@example.test', 'template_id' => $templateId, 'channel' => 'email', 'title' => 'T', 'payload_json' => '{"name":"A","tracking_code":"X","remarks":"R"}', 'status' => 'failed', 'attempts' => 3, 'last_error' => 'Boom']);
            $erased = $notifications->insert(['recipient_email' => 'b@example.test', 'template_id' => $templateId, 'channel' => 'email', 'title' => 'T', 'payload_json' => '{}', 'status' => 'failed', 'attempts' => 3]);
            $service = new SystemHealthService();
            $before = $service->overview();

            $this->assertSame($before['failed'] - 1, $before['retryable']);
            $this->assertGreaterThanOrEqual(1, $service->retryFailed());

            $row = $notifications->find($good);
            $this->assertSame('queued', $row['status']);
            $this->assertSame(0, (int) $row['attempts']);
            $this->assertNull($row['last_error']);
            $this->assertSame('failed', $notifications->find($erased)['status']);
        });
    }

    public function testTheHealthPageIsForTheMswHeadOnly(): void
    {
        $this->signIn('mswmember@agile.test');
        $this->expectException(\App\Core\ForbiddenException::class);
        (new SystemHealthService())->overview();
    }

    public function testEveryJobIsListedWithItsLastRun(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $runs = new JobRunRepository();
            $id = $runs->start('close_vacancies');
            $runs->finish($id, true, '0 closed');

            $rows = array_column((new SystemHealthService())->overview()['jobs'], null, 'job');

            $this->assertSame('ok', $rows['close_vacancies']['run']['result']);
            $this->assertSame('0 closed', $rows['close_vacancies']['run']['summary']);
            $this->assertFalse($rows['close_vacancies']['late']);
            $this->assertArrayHasKey('backup_database', $rows);
        });
    }

    public function testRevisionRemindersComeEveryThreeDays(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $id = (int) $db->query("SELECT application_id FROM membership_applications WHERE tracking_code = 'FTAVJNRA69R4'")->fetchColumn();
            $db->prepare('DELETE FROM application_access_links WHERE application_id = ?')->execute([$id]);
            $service = new \App\Services\ApplicationReminderService();
            $log = static function (): void {
            };
            $emails = static function () use ($db): int {
                return (int) $db->query("SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE t.code = 'APP_FOR_REVISION' AND n.recipient_email = 'jose.delacruz@agile.test'")->fetchColumn();
            };
            $age = static function (int $days) use ($db, $id): void {
                $db->prepare('UPDATE application_access_links SET created_at = ? WHERE application_id = ?')->execute([date('Y-m-d H:i:s', time() - $days * 86400), $id]);
            };

            $service->sendRevisionReminders($log);
            $first = $emails();
            $this->assertSame(1, $first);

            $age(2);
            $service->sendRevisionReminders($log);
            $this->assertSame($first, $emails(), 'Two days is too soon.');

            $age(3);
            $service->sendRevisionReminders($log);
            $this->assertSame($first + 1, $emails());
            $this->assertTrue((new ApplicationAccessLinkRepository())->hasUsable($id, 'revise'));
        });
    }

    private function inRollback(callable $test): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $test();
        } finally {
            $db->rollBack();
        }
    }

    private function signIn(string $email): void
    {
        $_SESSION['user_id'] = (int) (new UserRepository())->findBy(['email' => $email])[0]['user_id'];
        RbacService::clearCache();
    }
}
