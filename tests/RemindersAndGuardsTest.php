<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\ApplicationAccessLinkRepository;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\ApplicantAccessService;
use App\Services\CredentialService;
use App\Services\MembershipService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** Alignment rows 2, 3 and 4. Needs the seeded database. Every test rolls back. */
final class RemindersAndGuardsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testTheLastMswHeadCannotLoseTheRole(): void
    {
        $this->inRollback(function (): void {
            $headId = $this->userId('mswhead@agile.test');
            $memberId = (int) (new MemberRepository())->findByUser($headId)['member_id'];

            try {
                (new MembershipService())->expireRole($memberId, 'Confirmed by mistake', $this->userId('president@agile.test'));
                $this->fail('The last MSW Committee Head lost the role.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('only active MSW Committee Head', $exception->getMessage());
            }

            $this->assertSame('msw_head', (new UserRepository())->findWithRole($headId)['role_slug']);
            $this->assertNotNull((new MemberRepository())->findByUser($headId)['position_id']);
        });
    }

    public function testTheRoleCanEndOnceAnotherMswHeadExists(): void
    {
        $this->inRollback(function (): void {
            $headId = $this->userId('mswhead@agile.test');
            $db = Database::connection();
            $db->prepare("UPDATE users SET role_id = (SELECT role_id FROM roles WHERE slug = 'msw_head') WHERE user_id = ?")->execute([$this->userId('mswdeputy@agile.test')]);
            $memberId = (int) (new MemberRepository())->findByUser($headId)['member_id'];

            (new MembershipService())->expireRole($memberId, 'Failed subject confirmed', $this->userId('president@agile.test'));

            $this->assertSame('general_member', (new UserRepository())->findWithRole($headId)['role_slug']);
        });
    }

    public function testAnOrdinaryRoleChangeIsNotBlocked(): void
    {
        $this->inRollback(function (): void {
            $memberId = (int) (new MemberRepository())->findByUser($this->userId('head@agile.test'))['member_id'];

            (new MembershipService())->changeRole($memberId, null, 'Term ended', $this->userId('mswhead@agile.test'));

            $this->assertSame('general_member', (new UserRepository())->findWithRole($this->userId('head@agile.test'))['role_slug']);
        });
    }

    public function testPendingReminderGoesToMswStaffOnceADay(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $db->exec("UPDATE membership_applications SET submitted_at = NOW() - INTERVAL 5 DAY WHERE status = 'submitted'");
            $service = new \App\Services\ApplicationReminderService();

            $first = $service->sendPendingReminders(static function (): void {
            });
            $second = $service->sendPendingReminders(static function (): void {
            });

            $this->assertSame(3, $first['sent'], 'One MSW Committee Head and two MSW Members.');
            $this->assertSame(0, $first['failed']);
            $this->assertSame(0, $second['sent']);
            $this->assertSame(1, $this->emailsTo('mswhead@agile.test', 'APP_PENDING_REMINDER'));
            $this->assertSame(0, $this->emailsTo('head@agile.test', 'APP_PENDING_REMINDER'));
        });
    }

    public function testNoPendingReminderForRecentApplications(): void
    {
        $this->inRollback(function (): void {
            Database::connection()->exec("UPDATE membership_applications SET submitted_at = NOW() WHERE status IN ('submitted', 'under_review')");

            $this->assertSame(0, (new \App\Services\ApplicationReminderService())->sendPendingReminders(static function (): void {
            })['sent']);
        });
    }

    public function testRevisionReminderSendsAFreshLinkOnceADay(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $id = (int) $db->query("SELECT application_id FROM membership_applications WHERE tracking_code = 'FTAVJNRA69R4'")->fetchColumn();
            $db->prepare('DELETE FROM application_access_links WHERE application_id = ?')->execute([$id]);
            $db->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, remarks, changed_at) VALUES (?, 'under_review', 'for_revision', 'Please upload a clearer ID', NOW())")->execute([$id]);
            $service = new \App\Services\ApplicationReminderService();

            $first = $service->sendRevisionReminders(static function (): void {
            });
            $second = $service->sendRevisionReminders(static function (): void {
            });

            $this->assertGreaterThanOrEqual(1, $first['sent']);
            $this->assertSame(0, $first['failed']);
            $this->assertSame(0, $second['sent']);
            $this->assertTrue((new ApplicationAccessLinkRepository())->hasUsable($id, ApplicantAccessService::PURPOSE_REVISE));

            $row = $db->query("SELECT payload_json FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE t.code = 'APP_FOR_REVISION' AND n.recipient_email = 'jose.delacruz@agile.test' ORDER BY n.notification_id DESC LIMIT 1")->fetch();
            $payload = json_decode($row['payload_json'], true);
            $this->assertSame('Please upload a clearer ID', $payload['remarks']);
            $this->assertStringContainsString('token=', $payload['link']);
        });
    }

    public function testNoRevisionReminderOnTheDayTheLinkWentOut(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $id = (int) $db->query("SELECT application_id FROM membership_applications WHERE tracking_code = 'FTAVJNRA69R4'")->fetchColumn();
            (new ApplicantAccessService())->newRevisionLink($id);
            $service = new \App\Services\ApplicationReminderService();

            $service->sendRevisionReminders(static function (): void {
            });

            $this->assertSame(0, $this->emailsToAddress('jose.delacruz@agile.test', 'APP_FOR_REVISION'));
        });
    }

    public function testInternalMethodsRefuseSomeoneWithoutThePermission(): void
    {
        $this->inRollback(function (): void {
            $memberId = (int) (new MemberRepository())->findByUser($this->userId('member@agile.test'))['member_id'];
            $this->signIn('member@agile.test');
            $membership = new MembershipService();
            $credentials = new CredentialService();
            $calls = [
                static fn () => $credentials->issueFor($memberId),
                static fn () => $credentials->reissue($memberId, 'Forced'),
                static fn () => $membership->changeRole($memberId, null, 'Forced', 1),
                static fn () => $membership->expireRole($memberId, 'Forced', 1),
                static fn () => $membership->ensureMember(1, 'Forced'),
            ];

            foreach ($calls as $index => $call) {
                try {
                    $call();
                    $this->fail("Call {$index} was allowed.");
                } catch (ForbiddenException) {
                    $this->addToAssertionCount(1);
                }
            }
        });
    }

    public function testThePresidentCanStillReachRoleExpiry(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('president@agile.test');
            authorize_any('eligibility.manage', 'eligibility.decide_msw_head');
            $this->addToAssertionCount(1);
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
        $_SESSION['user_id'] = $this->userId($email);
        RbacService::clearCache();
    }

    private function userId(string $email): int
    {
        return (int) (new UserRepository())->findBy(['email' => $email])[0]['user_id'];
    }

    private function emailsTo(string $email, string $code): int
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE n.user_id = ? AND t.code = ? AND n.channel = 'email'");
        $statement->execute([$this->userId($email), $code]);

        return (int) $statement->fetchColumn();
    }

    private function emailsToAddress(string $email, string $code): int
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE n.recipient_email = ? AND t.code = ?");
        $statement->execute([$email, $code]);

        return (int) $statement->fetchColumn();
    }
}
