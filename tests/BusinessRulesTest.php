<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\ApplicationAccessLinkRepository;
use App\Models\ContentPostRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\UserRepository;
use App\Services\ApplicantAccessService;
use App\Services\AppointmentService;
use App\Services\AuthService;
use App\Services\CmsService;
use App\Services\EmailQueueService;
use App\Services\GuestVerificationService;
use App\Services\Mail\MailerInterface;
use App\Services\MemberRegistrationService;
use App\Services\MembershipService;
use App\Services\NotificationService;
use App\Services\RbacService;
use App\Services\TrackingService;
use App\Services\WelfareService;
use PHPUnit\Framework\TestCase;

/**
 * Section 7 rules that no other test covered directly. docs/test-cases.md lists which test covers each rule.
 * Needs the seeded test database. Every test rolls back.
 */
final class BusinessRulesTest extends TestCase
{
    protected function setUp(): void
    {
        // Opening an emailed link starts a new session id, so these tests need a session that sends no cookie.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_cookies', '0');
            session_cache_limiter('');
            session_start();
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        session_write_close();
        RbacService::clearCache();
    }

    public function testBr03ApprovalWaitsForEveryRequiredDocument(): void
    {
        $this->inRollback(function (): void {
            $id = $this->underReviewApplication('pending');
            $this->signIn('mswhead@agile.test');

            try {
                (new MembershipService())->approve($id, 'Looks good');
                $this->fail('An application with unverified documents was approved.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('verified', $exception->getMessage());
            }

            $this->assertSame('under_review', (new MembershipApplicationRepository())->find($id)['status']);

            Database::connection()->prepare("UPDATE application_documents SET verification_status = 'verified' WHERE application_id = ?")->execute([$id]);
            (new MembershipService())->approve($id, 'Looks good');
            $this->assertSame('approved', (new MembershipApplicationRepository())->find($id)['status']);
        });
    }

    public function testBr05OnlyTheMswHeadApprovesAppointments(): void
    {
        foreach (['mswmember@agile.test', 'mswdeputy@agile.test', 'head@agile.test', 'execomm@agile.test', 'president@agile.test', 'member@agile.test'] as $email) {
            $this->signIn($email);

            try {
                (new AppointmentService())->approve(999999, 'Remarks', null, '');
                $this->fail("{$email} got past the permission check.");
            } catch (ForbiddenException $exception) {
                $this->assertSame('appointment.approve', $exception->permission);
            }
        }
    }

    public function testBr06EveryCaseViewIsLoggedAndOthersAreRefused(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('member@agile.test');
            $id = (int) (new WelfareService())->submit(['category_id' => '1', 'subject' => 'S', 'description' => 'D', 'urgent' => false], null, [])['case_id'];

            foreach (['mswmember@agile.test', 'mswdeputy@agile.test', 'head@agile.test', 'execomm@agile.test', 'member@agile.test'] as $email) {
                $this->signIn($email);

                try {
                    (new WelfareService())->openCase($id);
                    $this->fail("{$email} opened a welfare case.");
                } catch (ForbiddenException) {
                    $this->addToAssertionCount(1);
                }
            }

            foreach (['mswhead@agile.test', 'president@agile.test'] as $email) {
                $this->signIn($email);
                $before = $this->rowCount('audit_logs', "action = 'welfare.viewed' AND entity_id = {$id}");
                $this->assertSame('S', (new WelfareService())->openCase($id)['case']['subject']);
                $this->assertSame($before + 1, $this->rowCount('audit_logs', "action = 'welfare.viewed' AND entity_id = {$id}"));
            }

            $this->assertSame(2, $this->rowCount('welfare_case_history', "case_id = {$id} AND event_type = 'viewed'"));
        });
    }

    public function testBr12ContentIsPublicOnlyAfterTheMswHeadApproves(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('head@agile.test');
            $cms = new CmsService();
            $title = 'Rule twelve ' . bin2hex(random_bytes(4));
            $id = $cms->save(null, ['type' => 'announcement', 'title' => $title, 'excerpt' => 'E', 'body' => '<p>Body</p>', 'slug' => '', 'event_start' => null, 'event_end' => null, 'venue_or_link' => null], null);
            $isPublic = static fn (): bool => in_array($title, array_column((new ContentPostRepository())->publishedList('announcement', '', 1, 50)['data'], 'title'), true);

            $this->assertFalse($isPublic(), 'A draft is not public.');
            $cms->submit($id);
            $this->assertFalse($isPublic(), 'Waiting for approval is not public.');

            try {
                $cms->publish($id, 'Publishing my own');
                $this->fail('A committee head published a post.');
            } catch (ForbiddenException) {
                $this->assertFalse($isPublic());
            }

            $this->signIn('mswhead@agile.test');
            (new CmsService())->publish($id, 'Approved');
            $this->assertTrue($isPublic());
        });
    }

    public function testBr13ActiveIsTheOnlyMembershipStatus(): void
    {
        $type = (string) Database::connection()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND COLUMN_NAME = 'status'")->fetchColumn();

        $this->assertSame("enum('active')", $type);
    }

    public function testBr14ApplicationDecisionsNeedRemarks(): void
    {
        $this->inRollback(function (): void {
            $id = $this->underReviewApplication('verified');
            $this->signIn('mswhead@agile.test');
            $service = new MembershipService();

            foreach (['approve', 'reject', 'returnForRevision'] as $decision) {
                try {
                    $service->{$decision}($id, '   ');
                    $this->fail("{$decision} worked with no remarks.");
                } catch (DomainException $exception) {
                    $this->assertStringContainsString('emarks', $exception->getMessage());
                }
            }

            $this->assertSame('under_review', (new MembershipApplicationRepository())->find($id)['status']);
        });
    }

    public function testBr17AGuestTracksACaseOnlyWithTheMatchingEmail(): void
    {
        $this->inRollback(function (): void {
            $case = (new WelfareService())->submitAsGuest(
                ['name' => 'Guest Person', 'email' => 'guest.person@example.test', 'student_no' => ''],
                ['category_id' => '1', 'subject' => 'PRIVATE SUBJECT', 'description' => 'PRIVATE WORDS', 'urgent' => false],
                '',
                [],
            );
            $tracking = new TrackingService();
            $code = $case['case_code'];

            $this->assertNull($tracking->lookup($code, 'someone.else@example.test', '198.51.100.' . random_int(1, 250)));

            $found = $tracking->lookup(strtolower($code), 'GUEST.person@example.test', '198.51.100.' . random_int(1, 250));
            $this->assertSame('submitted', $found['status']);
            $this->assertStringNotContainsString('PRIVATE', (string) json_encode($found), 'Tracking shows status and updates only.');
        });
    }

    public function testBr18EditLinksAreSingleUse(): void
    {
        $this->inRollback(function (): void {
            $id = $this->forRevisionApplication();
            $access = new ApplicantAccessService();
            $token = $this->tokenFrom($access->newRevisionLink($id));

            $this->assertTrue($access->isLinkUsable($token));
            $this->assertSame($id, $access->openLink($token));
            $this->assertFalse($access->isLinkUsable($token), 'A used link stops working.');
            $this->assertNull($access->openLink($token));
        });
    }

    public function testBr18EditLinksExpire(): void
    {
        $this->inRollback(function (): void {
            $id = $this->forRevisionApplication();
            $access = new ApplicantAccessService();
            $token = $this->tokenFrom($access->newRevisionLink($id));
            Database::connection()->prepare('UPDATE application_access_links SET expires_at = ? WHERE application_id = ?')->execute([date('Y-m-d H:i:s', time() - 1), $id]);

            $this->assertFalse($access->isLinkUsable($token));
            $this->assertNull($access->openLink($token));
        });
    }

    public function testBr18ANewLinkCancelsTheOlderOne(): void
    {
        $this->inRollback(function (): void {
            $id = $this->forRevisionApplication();
            $access = new ApplicantAccessService();
            $old = $this->tokenFrom($access->newRevisionLink($id));
            $new = $this->tokenFrom($access->newRevisionLink($id));

            $this->assertFalse($access->isLinkUsable($old));
            $this->assertTrue($access->isLinkUsable($new));
        });
    }

    public function testBr18ARegistrationCodeExpires(): void
    {
        $this->inRollback(function (): void {
            $this->insertCode('lito.ramos@agile.test', '123456', time() - 1);
            $this->assertNull((new MemberRegistrationService())->confirmCode('2024-00114-OU-0', '123456'));
        });
    }

    public function testBr18ARightCodeOnlyWorksForAnApprovedMemberWithNoAccount(): void
    {
        $this->inRollback(function (): void {
            $registration = new MemberRegistrationService();

            // Approved in the seeded data, with no account yet.
            $this->insertCode('lito.ramos@agile.test', '123456', time() + 600);
            $this->assertNotNull($registration->confirmCode('2024-00114-OU-0', '123456'));

            // Under review, For Revision and Submitted, with a code that would otherwise be right.
            foreach ([['maria.lim@agile.test', '2024-00113-OU-0'], ['jose.delacruz@agile.test', '2024-00112-OU-0'], ['andrea.santos@agile.test', '2024-00111-OU-0']] as [$email, $number]) {
                $this->insertCode($email, '123456', time() + 600);
                $this->assertNull($registration->confirmCode($number, '123456'), $number);
            }

            // A member who already has an account, and a number nobody has.
            $this->assertNull($registration->confirmCode('2023-00102-OU-0', '123456'));
            $this->assertNull($registration->confirmCode('2099-99999-OU-0', '123456'));
        });
    }

    public function testBr18ApprovalCreatesNoAccount(): void
    {
        $this->inRollback(function (): void {
            $id = $this->underReviewApplication('verified');
            $email = (string) Database::connection()->query("SELECT ap.email FROM membership_applications a JOIN applicants ap ON ap.applicant_id = a.applicant_id WHERE a.application_id = {$id}")->fetchColumn();
            $this->signIn('mswhead@agile.test');
            (new MembershipService())->approve($id, 'Welcome');

            $this->assertNull((new UserRepository())->findByEmail($email));
        });
    }

    public function testBr21APendingAccountCannotLogIn(): void
    {
        $this->inRollback(function (): void {
            $email = 'waiting.account@example.test';
            (new UserRepository())->insert([
                'role_id' => (new UserRepository())->findByEmail('member@agile.test')['role_id'],
                'email' => $email,
                'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
                'first_name' => 'W',
                'last_name' => 'A',
                'contact_no' => '',
                'status' => 'pending',
            ]);
            unset($_SESSION['user_id']);

            $this->assertSame(AuthService::LOGIN_PENDING, (new AuthService())->attempt($email, 'Password123!', '198.51.100.' . random_int(1, 250)));
            $this->assertNull(current_user_id());
        });
    }

    public function testTc18ClosureThenDispositionIsRecordedOnce(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('member@agile.test');
            $db = Database::connection();
            $id = (int) (new WelfareService())->submit(['category_id' => '1', 'subject' => 'S', 'description' => 'D', 'urgent' => false], null, [])['case_id'];
            $db->prepare("INSERT INTO welfare_attachments (case_id, original_name, stored_path, mime_type, size_bytes) VALUES (?, 'x', ?, 'application/pdf', 1)")
                ->execute([$id, 'welfare/2026/11/' . bin2hex(random_bytes(16)) . '.enc']);
            $attachment = (int) $db->lastInsertId();
            $this->signIn('mswhead@agile.test');
            $service = new WelfareService();

            try {
                $service->setDisposition($id, $attachment, 'kept');
                $this->fail('A disposition was recorded before closure.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $service->resolve($id, 'Did things', 'resolved', 'All good');
            $service->close($id, 'Done');
            $service->setDisposition($id, $attachment, 'deleted');

            $this->assertSame('deleted', $db->query("SELECT disposition FROM welfare_attachments WHERE attachment_id = {$attachment}")->fetchColumn());
            $this->assertNull($service->openAttachment($attachment), 'A deleted attachment cannot be opened.');

            try {
                $service->setDisposition($id, $attachment, 'kept');
                $this->fail('A disposition changed.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $this->assertGreaterThan(0, $this->rowCount('notifications n JOIN email_templates t ON t.template_id = n.template_id', "t.code = 'WELFARE_CLOSED' AND n.user_id = " . (int) (new UserRepository())->findByEmail('member@agile.test')['user_id']));
        });
    }

    public function testTc24AFailedEmailIsTriedThreeTimes(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $id = (new NotificationService())->notifyEmail('retry.me@example.test', 'APP_REJECTED', ['name' => 'R', 'tracking_code' => 'X', 'remarks' => 'R']);
            $mailer = new class () implements MailerInterface {
                public function send(string $to, string $subject, string $html): void
                {
                    throw new RuntimeException('The mail server said no.');
                }
            };
            $row = static fn (): array => $db->query("SELECT status, attempts, last_error FROM notifications WHERE notification_id = {$id}")->fetch();
            $queue = new EmailQueueService();
            $log = static function (string $line): void {
            };

            $queue->run($mailer, $log, 500);
            $this->assertSame(['queued', 1], [$row()['status'], (int) $row()['attempts']]);
            $queue->run($mailer, $log, 500);
            $this->assertSame(['queued', 2], [$row()['status'], (int) $row()['attempts']]);
            $queue->run($mailer, $log, 500);
            $this->assertSame(['failed', 3], [$row()['status'], (int) $row()['attempts']]);
            $this->assertStringContainsString('said no', $row()['last_error']);

            $queue->run($mailer, $log, 500);
            $this->assertSame(3, (int) $row()['attempts'], 'A fourth try never happens.');
        });
    }

    private function underReviewApplication(string $documents): int
    {
        $db = Database::connection();
        $number = '2025-' . random_int(10000, 99999) . '-OU-0';
        $email = 'rules.' . bin2hex(random_bytes(4)) . '@example.test';
        $db->prepare("INSERT INTO applicants (email, first_name, last_name, student_no, contact_no, year_level, email_verified_at) VALUES (?, 'Rules', 'Person', ?, '09170000000', '2nd Year', NOW())")->execute([$email, $number]);
        $applicantId = (int) $db->lastInsertId();
        $id = (new MembershipApplicationRepository())->insert([
            'applicant_id' => $applicantId,
            'tracking_code' => MembershipService::generateTrackingCode(),
            'status' => 'under_review',
            'submitted_at' => date('Y-m-d H:i:s'),
            'wizard_step' => 6,
        ]);

        foreach (['school_id', 'cor'] as $type) {
            $db->prepare("INSERT INTO application_documents (application_id, doc_type, original_name, stored_path, mime_type, size_bytes, verification_status) VALUES (?, ?, 'x.pdf', ?, 'application/pdf', 1, ?)")
                ->execute([$id, $type, 'applications/seed/' . md5("seed-{$type}") . '.pdf', $documents]);
        }

        return $id;
    }

    private function forRevisionApplication(): int
    {
        return (int) Database::connection()->query("SELECT application_id FROM membership_applications WHERE tracking_code = 'FTAVJNRA69R4'")->fetchColumn();
    }

    private function tokenFrom(string $path): string
    {
        return substr($path, (int) strpos($path, 'token=') + 6);
    }

    private function insertCode(string $email, string $code, int $expires): void
    {
        Database::connection()->prepare("INSERT INTO guest_verifications (email, code_hash, purpose, expires_at) VALUES (?, ?, 'member_registration', ?)")
            ->execute([$email, GuestVerificationService::hash($code), date('Y-m-d H:i:s', $expires)]);
    }

    private function rowCount(string $table, string $where): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    private function signIn(string $email): void
    {
        $_SESSION['user_id'] = (int) (new UserRepository())->findByEmail($email)['user_id'];
        RbacService::clearCache();
    }

    private function inRollback(callable $test): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $test();
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    }
}
