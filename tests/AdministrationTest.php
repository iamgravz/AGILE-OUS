<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\MemberRepository;
use App\Models\PermissionRepository;
use App\Models\RoleRepository;
use App\Models\SettingRepository;
use App\Models\UserRepository;
use App\Services\AuthService;
use App\Services\CommitteeAdminService;
use App\Services\EmailDraftService;
use App\Services\EmailTemplateService;
use App\Services\HtmlSanitizer;
use App\Services\RbacService;
use App\Services\RoleMatrixService;
use App\Services\SettingsService;
use App\Services\TemplateRenderer;
use App\Services\UserAdminService;
use PHPUnit\Framework\TestCase;

/** Step 20. Needs the seeded database. Every test rolls back. */
final class AdministrationTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testTheLastMswHeadCannotBeRemovedOrDisabled(): void
    {
        $this->inRollback(function (): void {
            $head = $this->userId('mswhead@agile.test');
            $this->signIn('mswhead@agile.test');
            $service = new UserAdminService();

            foreach ([static fn () => $service->changeRole($head, 'general_member', 'Test'), static fn () => $service->setEnabled($head, false, 'Test')] as $call) {
                try {
                    $call();
                    $this->fail('The last MSW Committee Head was removed.');
                } catch (DomainException $exception) {
                    $this->assertMatchesRegularExpression('/only active MSW Committee Head|your own/', $exception->getMessage());
                }
            }

            $this->assertSame('msw_head', (new UserRepository())->findWithRole($head)['role_slug']);
            $this->assertSame('active', (new UserRepository())->find($head)['status']);
        });
    }

    public function testAnMswHeadCannotRemoveTheirOwnAccessEvenWithAnother(): void
    {
        $this->inRollback(function (): void {
            $deputy = $this->userId('mswdeputy@agile.test');
            Database::connection()->prepare("UPDATE users SET role_id = (SELECT role_id FROM roles WHERE slug = 'msw_head') WHERE user_id = ?")->execute([$deputy]);
            $head = $this->userId('mswhead@agile.test');
            $this->signIn('mswhead@agile.test');
            $service = new UserAdminService();

            try {
                $service->changeRole($head, 'general_member', 'Test');
                $this->fail('They removed their own access.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('your own admin access', $exception->getMessage());
            }

            $service->changeRole($deputy, 'msw_member', 'Back to deputy');
            $this->assertSame('msw_member', (new UserRepository())->findWithRole($deputy)['role_slug']);
        });
    }

    public function testAccountActionsAreLoggedWithBeforeAndAfter(): void
    {
        $this->inRollback(function (): void {
            $member = $this->userId('member@agile.test');
            $this->signIn('mswhead@agile.test');
            $service = new UserAdminService();

            $service->changeRole($member, 'execomm', 'Test');
            $row = Database::connection()->query("SELECT details_json FROM audit_logs WHERE action = 'user.role_changed' ORDER BY log_id DESC LIMIT 1")->fetchColumn();
            $details = json_decode($row, true);
            $this->assertSame('general_member', $details['before']);
            $this->assertSame('execomm', $details['after']);

            $service->setEnabled($member, false, 'Test');
            $this->assertSame('disabled', (new UserRepository())->find($member)['status']);
            $service->setEnabled($member, true, 'Test');
            $this->assertSame('active', (new UserRepository())->find($member)['status']);
        });
    }

    public function testUnlockAndForcedReset(): void
    {
        $this->inRollback(function (): void {
            $member = $this->userId('member@agile.test');
            (new UserRepository())->update($member, ['failed_logins' => 5, 'locked_until' => date('Y-m-d H:i:s', time() + 600)]);
            $this->signIn('mswhead@agile.test');
            $service = new UserAdminService();

            $service->unlock($member);
            $this->assertNull((new UserRepository())->find($member)['locked_until']);

            $service->forcePasswordReset($member);
            $this->assertSame('', (new UserRepository())->find($member)['password_hash']);
        });
    }

    public function testExtraPermissionsAddAccessAndCanBeRevoked(): void
    {
        $this->inRollback(function (): void {
            $member = $this->userId('member@agile.test');
            $this->signIn('mswhead@agile.test');
            $service = new UserAdminService();

            $service->setExtraPermissions($member, ['report.export', 'not.a.code']);
            $this->assertSame(['report.export'], (new PermissionRepository())->extraForUser($member));
            $this->signIn('member@agile.test');
            $this->assertTrue(can('report.export'));

            $this->signIn('mswhead@agile.test');
            $service->setExtraPermissions($member, []);
            $this->signIn('member@agile.test');
            $this->assertFalse(can('report.export'));
        });
    }

    public function testTheMatrixKeepsTheMswHeadAdminPermissions(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $roleId = (int) (new RoleRepository())->findBySlug('msw_head')['role_id'];
            $service = new RoleMatrixService();
            $current = $service->matrix()['granted'];
            $current[$roleId] = [];

            $service->save($current);

            $kept = (new PermissionRepository())->codesForRole($roleId);
            foreach (RoleMatrixService::LOCKED_FOR_MSW_HEAD as $code) {
                $this->assertContains($code, $kept);
            }
            $this->assertNotContains('content.publish', $kept);

            $row = Database::connection()->query("SELECT details_json FROM audit_logs WHERE action = 'role.permissions_changed' ORDER BY log_id DESC LIMIT 1")->fetchColumn();
            $this->assertContains('content.publish', json_decode($row, true)['before']);
        });
    }

    public function testACreatedHeadGetsTheRolePositionAndMenus(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $userId = (new AuthService())->createAccount('new.head@example.test', 'New', 'Head', 'general_member');
            $db = Database::connection();
            $committeeId = (int) $db->query("SELECT committee_id FROM committees WHERE name = 'Events and External Relations'")->fetchColumn();
            $service = new CommitteeAdminService();

            $service->setHead($committeeId, $userId, 'Elected by the committee');

            $this->assertSame('committee_head', (new UserRepository())->findWithRole($userId)['role_slug']);
            $this->assertSame($userId, (int) $service->committee($committeeId)['head_user_id']);
            $this->assertNotNull((new MemberRepository())->findByUser($userId)['position_id']);
            $this->signIn('new.head@example.test');
            $this->assertTrue(can('hr_request.submit'));
            $this->assertContains('HR Requests', array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 'label'), sidebar_menu())));
        });
    }

    public function testReplacingAHeadEndsThePreviousRole(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $db = Database::connection();
            $committeeId = (int) $db->query("SELECT committee_id FROM committees WHERE name = 'Academics and Training'")->fetchColumn();
            $newHead = $this->userId('member@agile.test');

            (new CommitteeAdminService())->setHead($committeeId, $newHead, 'New head');

            $this->assertSame('committee_head', (new UserRepository())->findWithRole($newHead)['role_slug']);
            $this->assertSame('general_member', (new UserRepository())->findWithRole($this->userId('head@agile.test'))['role_slug']);
        });
    }

    public function testAnythingInUseCannotBeDeleted(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $db = Database::connection();
            $service = new CommitteeAdminService();
            $committeeId = (int) $db->query("SELECT committee_id FROM committees WHERE name = 'Academics and Training'")->fetchColumn();
            $positionId = (int) $db->query("SELECT position_id FROM positions WHERE committee_id = {$committeeId} AND role_type = 'committee_head'")->fetchColumn();

            foreach ([static fn () => $service->deleteCommittee($committeeId), static fn () => $service->deletePosition($positionId)] as $call) {
                try {
                    $call();
                    $this->fail('Something in use was deleted.');
                } catch (DomainException) {
                    $this->addToAssertionCount(1);
                }
            }

            $newCommittee = $service->createCommittee('Temporary', '');
            $newPosition = $service->createPosition($newCommittee, 'Temporary Member', 'committee_member');

            try {
                $service->updatePosition($positionId, 'Head', 'deputy');
                $this->fail('A position in use changed type.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $service->deletePosition($newPosition);
            $service->deleteCommittee($newCommittee);
            $this->assertSame([], $db->query("SELECT 1 FROM committees WHERE name = 'Temporary'")->fetchAll());
        });
    }

    public function testEditingATemplateChangesTheNextEmail(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $id = (int) Database::connection()->query("SELECT template_id FROM email_templates WHERE code = 'APP_SUBMITTED'")->fetchColumn();

            (new EmailTemplateService())->update($id, 'New subject {tracking_code}', '<p>Dear {name},</p><p class="details">Code: {tracking_code}</p><p><a class="button" href="{link}">Track</a></p>');

            $rendered = (new TemplateRenderer())->render('APP_SUBMITTED', ['name' => 'Ana', 'tracking_code' => 'ABC', 'link' => '/track']);
            $this->assertSame('New subject ABC', $rendered['subject']);
            $this->assertStringContainsString('Code: ABC', $rendered['html']);
            $this->assertStringContainsString('background-color:#7a1a1a;color:#ffffff', $rendered['html']);
            $this->assertStringContainsString('border-left:3px solid #c9a227', $rendered['html']);
            $this->assertStringContainsString('/track"', $rendered['html']);
            $this->assertStringNotContainsString('class=', $rendered['html']);
            $this->assertDoesNotMatchRegularExpression('/<a [^>]*style="[^"]*"[^>]*style=/', $rendered['html'], 'A link has two style attributes, so the browser ignores the second.');
        });
    }

    public function testATemplateCannotUseAValueItsCodeDoesNotPass(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $id = (int) Database::connection()->query("SELECT template_id FROM email_templates WHERE code = 'APP_SUBMITTED'")->fetchColumn();

            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('{member_no}');
            (new EmailTemplateService())->update($id, 'Subject', '<p>{member_no}</p>');
        });
    }

    public function testTheEmailSanitizerKeepsWhatTemplatesNeedAndRemovesTheRest(): void
    {
        $clean = (new HtmlSanitizer())->cleanEmail('<p class="details" onclick="x()">Hi {name}</p><script>alert(1)</script><a class="button evil" href="{link}">Go</a><a href="mailto:{contact_email}">Mail</a><a href="javascript:alert(1)">Bad</a>');

        $this->assertStringContainsString('<p class="details">Hi {name}</p>', $clean);
        $this->assertStringContainsString('href="{link}"', $clean);
        $this->assertStringContainsString('class="button"', $clean);
        $this->assertStringContainsString('mailto:{contact_email}', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
    }

    public function testThePreviewUsesSampleValuesInTheRealWrapper(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $id = (int) Database::connection()->query("SELECT template_id FROM email_templates WHERE code = 'APP_SUBMITTED'")->fetchColumn();

            $html = (new EmailTemplateService())->preview($id, 'Unsaved {tracking_code}', '<p>Unsaved text for {name}</p>');

            $this->assertStringContainsString('Unsaved text for Andrea', $html);
            $this->assertStringContainsString('Warm regards,', $html);
            $this->assertStringContainsString('#7a1a1a', $html);
            $this->assertSame('We received your AGILE application ({tracking_code})', Database::connection()->query("SELECT subject FROM email_templates WHERE code = 'APP_SUBMITTED'")->fetchColumn());
        });
    }

    public function testADraftQueuesOneEmailPerPersonWithTheUnsubscribeNote(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswmember@agile.test');
            $service = new EmailDraftService();
            $id = $service->create('Assembly on Friday', '<p>Please <strong>attend</strong>.</p><script>alert(1)</script>', 'officers');
            $count = $service->recipientCount('officers');
            $this->assertGreaterThan(0, $count);

            $this->assertSame($count, $service->send($id));

            $db = Database::connection();
            $this->assertSame('sent', $service->find($id)['status']);
            $row = $db->query("SELECT n.payload_json, n.title FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE t.code = 'ANNOUNCEMENT' ORDER BY n.notification_id DESC LIMIT 1")->fetch();
            $this->assertSame('Assembly on Friday', $row['title']);
            $payload = json_decode($row['payload_json'], true);
            $payload += ['name' => 'Ana'];
            $html = (new TemplateRenderer())->render('ANNOUNCEMENT', $payload)['html'];
            $this->assertStringContainsString('<strong>attend</strong>', $html);
            $this->assertStringNotContainsString('alert(1)', $html);
            $this->assertStringContainsString('stop receiving announcements', $html);

            try {
                $service->send($id);
                $this->fail('A sent email went out twice.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('already sent', $exception->getMessage());
            }
        });
    }

    public function testDraftGroupsReachTheRightPeople(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $service = new EmailDraftService();
            $committeeId = (int) Database::connection()->query("SELECT committee_id FROM committees WHERE name = 'Academics and Training'")->fetchColumn();

            $this->assertGreaterThan($service->recipientCount('officers'), $service->recipientCount('all_members'));
            // The demo head, plus the sample deputy and member from seeds/demo_organization.php.
            $this->assertSame(3, $service->recipientCount("committee:{$committeeId}"));
            $this->assertGreaterThan(0, $service->recipientCount('applicants'));
            $this->assertSame(0, $service->recipientCount('everyone_else'));
        });
    }

    public function testSettingsAreCheckedBeforeAnythingIsSaved(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $service = new SettingsService();
            $input = $this->validSettings($service);

            $bad = ['upload_max_mb' => '500', 'org_contact_email' => 'not-an-email', 'org_mission' => 'Changed'] + $input;
            $errors = $service->save($bad);
            $this->assertArrayHasKey('upload_max_mb', $errors);
            $this->assertArrayHasKey('org_contact_email', $errors);
            $this->assertNotSame('Changed', (new SettingRepository())->value('org.mission'));

            $weights = $input;
            $weights['rubric_weight'][0] = '1';
            $this->assertArrayHasKey('rubric', $service->save($weights));

            $this->assertSame([], $service->save(['org_mission' => 'Changed', 'session_idle_minutes' => '45', 'welfare_guest_submission' => '0'] + $input));
            $this->assertSame('Changed', (new SettingRepository())->value('org.mission'));
            $this->assertSame('0', (new SettingRepository())->value('welfare.guest_submission'));
            $this->assertSame(45, session_idle_minutes());
        });
    }

    /** @return array<string, mixed> */
    private function validSettings(SettingsService $service): array
    {
        $current = $service->current();
        $input = ['rubric_min' => (string) $current['rubric']['scale']['min'], 'rubric_max' => (string) $current['rubric']['scale']['max']];

        foreach ($current['values'] as $key => $value) {
            $input[SettingsService::fieldName($key)] = $value;
        }

        foreach ($current['rubric']['criteria'] as $i => $row) {
            $input['rubric_key'][$i] = $row['key'];
            $input['rubric_label'][$i] = $row['label'];
            $input['rubric_weight'][$i] = (string) $row['weight'];
        }

        return $input;
    }

    public function testNoRouteChangesOrRemovesAnAuditRow(): void
    {
        $routes = file_get_contents(dirname(__DIR__) . '/config/routes.php');
        preg_match_all("#->(post|put|patch|delete)\('(/admin/audit[^']*)'#", $routes, $matches);

        $this->assertSame(['/admin/audit-logs/export'], $matches[2]);
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
}
