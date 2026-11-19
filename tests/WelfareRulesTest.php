<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\UserRepository;
use App\Models\WelfareCaseRepository;
use App\Services\EncryptionService;
use App\Services\GuestVerificationService;
use App\Services\RbacService;
use App\Services\WelfareService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The submitter's side of welfare. Needs the seeded database. Database tests roll back, so nothing is left behind. */
final class WelfareRulesTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testTransitionsMatchSectionEight(): void
    {
        $allowed = [['submitted', 'in_progress'], ['in_progress', 'referred'], ['in_progress', 'resolved'], ['referred', 'in_progress'], ['referred', 'resolved'], ['resolved', 'closed']];

        foreach (WelfareService::STATUSES as $from) {
            foreach (WelfareService::STATUSES as $to) {
                $this->assertSame(in_array([$from, $to], $allowed, true), WelfareService::canTransition($from, $to), "{$from} to {$to}");
            }
        }
    }

    public function testAClosedCaseCannotChange(): void
    {
        foreach ([null, ...WelfareService::STATUSES] as $to) {
            try {
                WelfareService::assertCanChange(['status' => 'closed'], $to);
                $this->fail('A closed case was allowed to change.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testUploadsAreSplitAndEmptySlotsDropped(): void
    {
        $uploads = WelfareService::uploadsFrom([
            'name' => ['a.pdf', ''],
            'type' => ['application/pdf', ''],
            'tmp_name' => ['/tmp/a', ''],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
            'size' => [5, 0],
        ]);

        $this->assertCount(1, $uploads);
        $this->assertSame('a.pdf', $uploads[0]['name']);
        $this->assertSame([], WelfareService::uploadsFrom(null));
        $this->assertSame([], WelfareService::uploadsFrom(['name' => 'not-an-array']));
    }

    /** @return list<array{int}> */
    public static function categories(): array
    {
        return [[1], [2], [3], [4], [5], [6], [7]];
    }

    #[DataProvider('categories')]
    public function testEachCategoryIsSavedAndTheTextIsEncrypted(int $category): void
    {
        $this->inRollback(function () use ($category): void {
            $this->signIn('nomember@agile.test');
            $case = (new WelfareService())->submit($this->input($category), null, []);
            $row = (new WelfareCaseRepository())->find((int) $case['case_id']);

            $this->assertSame($category, (int) $row['category_id']);
            $this->assertSame('submitted', $row['status']);
            $this->assertSame('medium', $row['priority']);
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{12}$/', $row['case_code']);
            $this->assertStringNotContainsString('Private words', $row['description']);
            $this->assertStringNotContainsString('Help please', $row['subject']);
            $this->assertSame('Private words', (new EncryptionService())->decrypt($row['description']));
            $this->assertSame('Help please', (new EncryptionService())->decrypt($row['subject']));
        });
    }

    public function testOnlyHarassmentOrUrgentAlertsTheHandlers(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('nomember@agile.test');
            $service = new WelfareService();

            $plain = $service->submit($this->input(1), null, []);
            $this->assertSame(0, $this->alerts($plain['case_code']));

            $harassment = $service->submit($this->input(6), null, []);
            $this->assertSame(4, $this->alerts($harassment['case_code']));

            $urgent = $service->submit(['urgent' => true] + $this->input(1), null, []);
            $this->assertSame(4, $this->alerts($urgent['case_code']));
            $this->assertSame('high', $urgent['priority']);

            $statement = Database::connection()->prepare("SELECT payload_json FROM notifications WHERE payload_json LIKE ? ");
            $statement->execute(['%Private words%']);
            $this->assertSame([], $statement->fetchAll());
        });
    }

    public function testHistoryAndAuditHoldNoConfidentialText(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('nomember@agile.test');
            $case = (new WelfareService())->submit($this->input(1), null, []);
            $id = (int) $case['case_id'];

            $history = Database::connection()->prepare('SELECT event_type, new_value FROM welfare_case_history WHERE case_id = ?');
            $history->execute([$id]);
            $this->assertSame([['event_type' => 'status_change', 'new_value' => 'submitted']], $history->fetchAll());

            $audit = Database::connection()->prepare("SELECT details_json FROM audit_logs WHERE action = 'welfare.submitted' AND entity_id = ?");
            $audit->execute([$id]);
            $details = (string) $audit->fetchColumn();
            $this->assertStringNotContainsString('Private', $details);
            $this->assertStringNotContainsString('Help', $details);
        });
    }

    public function testRelatedCaseMustBeOwnAndClosed(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('nomember@agile.test');
            $service = new WelfareService();
            $mine = $service->submit($this->input(1), null, []);

            try {
                $service->submit($this->input(1), (int) $mine['case_id'], []);
                $this->fail('A case that is not closed was accepted as related.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            (new WelfareCaseRepository())->update((int) $mine['case_id'], ['status' => 'closed']);
            $this->assertSame((int) $mine['case_id'], (int) $service->submit($this->input(1), (int) $mine['case_id'], [])['related_case_id']);

            $this->signIn('member@agile.test');
            $this->expectException(DomainException::class);
            $service->submit($this->input(1), (int) $mine['case_id'], []);
        });
    }

    public function testGuestCaseSavesTheGuestAndChecksRelatedEmail(): void
    {
        $this->inRollback(function (): void {
            $service = new WelfareService();
            $guest = ['name' => 'Guest One', 'email' => 'guest-one@example.test', 'student_no' => ''];
            $case = $service->submitAsGuest($guest, $this->input(6), '', []);

            $this->assertNull($case['user_id']);
            $this->assertNotSame('guest-one@example.test', $case['guest_email'], 'The guest email is stored encrypted (M3).');
            $this->assertSame(['name' => 'Guest One', 'email' => 'guest-one@example.test', 'student_no' => ''], $service->guestOf($case));
            $this->assertSame(4, $this->alerts($case['case_code']));
            $this->assertSame((int) $case['case_id'], (int) $service->submitAsGuest($guest, $this->input(1), $case['case_code'], [])['related_case_id']);

            $this->expectException(DomainException::class);
            $service->submitAsGuest(['email' => 'someone-else@example.test'] + $guest, $this->input(1), $case['case_code'], []);
        });
    }

    public function testGuestCasesLinkToTheNewAccount(): void
    {
        $this->inRollback(function (): void {
            $service = new WelfareService();
            $case = $service->submitAsGuest(['name' => 'G', 'email' => 'link-me@example.test', 'student_no' => ''], $this->input(1), '', []);
            $other = $service->submitAsGuest(['name' => 'H', 'email' => 'not-me@example.test', 'student_no' => ''], $this->input(1), '', []);
            $userId = $this->userId('nomember@agile.test');

            $this->assertSame(1, $service->linkGuestCases($userId, 'link-me@example.test'));
            $this->assertSame($userId, (int) (new WelfareCaseRepository())->find((int) $case['case_id'])['user_id']);
            $this->assertNull((new WelfareCaseRepository())->find((int) $other['case_id'])['user_id']);
            $this->assertSame(0, $service->linkGuestCases($userId, 'link-me@example.test'));
        });
    }

    public function testCodeIsHashedExpiresAndLocksAfterFiveWrongTries(): void
    {
        $this->inRollback(function (): void {
            $service = new GuestVerificationService();
            $db = Database::connection();
            $insert = $db->prepare("INSERT INTO guest_verifications (email, code_hash, purpose, expires_at) VALUES (?, ?, 'welfare_submit', ?)");
            $future = date('Y-m-d H:i:s', time() + 600);

            $insert->execute(['right@example.test', GuestVerificationService::hash('123456'), $future]);
            $this->assertSame(GuestVerificationService::RESULT_WRONG, $service->verify('right@example.test', '000000'));
            $this->assertSame(GuestVerificationService::RESULT_OK, $service->verify('right@example.test', '123456'));
            $this->assertSame(GuestVerificationService::RESULT_EXPIRED, $service->verify('right@example.test', '123456'));

            $insert->execute(['old@example.test', GuestVerificationService::hash('123456'), date('Y-m-d H:i:s', time() - 1)]);
            $this->assertSame(GuestVerificationService::RESULT_EXPIRED, $service->verify('old@example.test', '123456'));

            $insert->execute(['lock@example.test', GuestVerificationService::hash('123456'), $future]);

            foreach (range(1, 4) as $try) {
                $this->assertSame(GuestVerificationService::RESULT_WRONG, $service->verify('lock@example.test', '000000'), "try {$try}");
            }

            $this->assertSame(GuestVerificationService::RESULT_LOCKED, $service->verify('lock@example.test', '000000'));
            $this->assertSame(GuestVerificationService::RESULT_LOCKED, $service->verify('lock@example.test', '123456'));
            $this->assertSame(GuestVerificationService::RESULT_EXPIRED, $service->verify('nobody@example.test', '123456'));
        });
    }

    /** @return array{category_id: string, subject: string, description: string, urgent: bool} */
    private function input(int $category): array
    {
        return ['category_id' => (string) $category, 'subject' => 'Help please', 'description' => 'Private words', 'urgent' => false];
    }

    /** Email and in-app rows for this case's alert. */
    private function alerts(string $caseCode): int
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id
             WHERE t.code = 'WELFARE_URGENT_ALERT' AND n.title LIKE ?"
        );
        $statement->execute(["%{$caseCode}%"]);

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
