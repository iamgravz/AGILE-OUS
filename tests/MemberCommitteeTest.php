<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\MemberCommitteeService;
use App\Services\MembershipService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** P4: a second standing committee for a Committee Member or Year-Level Representative (Article VI Section 4-A). */
final class MemberCommitteeTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testACommitteeMemberJoinsOneOtherStandingCommittee(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $service = new MemberCommitteeService();
            $member = $this->memberHolding('Academics and Training', 'Committee Member');
            // The demo seeder may have given this member a second committee already.
            Database::connection()->prepare('DELETE FROM member_committees WHERE member_id = ?')->execute([$member]);
            $names = array_column($service->options($member), 'name');

            $this->assertNotContains('Academics and Training', $names, 'Not their own committee.');
            $this->assertNotContains('The Source Code', $names, 'Standing committees only.');
            $this->assertNotContains('Year-Level Council', $names);
            $this->assertContains('Logistics', $names);

            $service->add($member, $this->committee('Logistics'), 'Approved application');
            $this->assertSame(['Logistics'], array_column($service->extras($member), 'name'));
            $this->assertSame([], $service->options($member), 'Two committees at most.');
            $this->assertRefused('already in two committees', fn () => $service->add($member, $this->committee('Finance and Fundraising'), 'Again'));

            // The Members list for Logistics shows them too.
            $found = (new MemberRepository())->search(['committee_id' => $this->committee('Logistics')], 1, 200);
            $this->assertContains($member, array_map('intval', array_column($found['data'], 'member_id')));

            $service->remove($member, $this->committee('Logistics'), 'Left the committee');
            $this->assertSame([], $service->extras($member));
        });
    }

    public function testAYearLevelRepresentativeMayJoinButOfficersMayNot(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $service = new MemberCommitteeService();

            $rep = $this->memberHolding('Year-Level Council', '1st Year Representative');
            $service->add($rep, $this->committee('Logistics'), 'Approved application');
            $this->assertCount(1, $service->extras($rep));

            $head = $this->memberHolding('Logistics', 'Committee Head');
            $this->assertSame([], $service->options($head));
            $this->assertRefused('one office', fn () => $service->add($head, $this->committee('Finance and Fundraising'), 'No'));
            $this->assertRefused('one office', fn () => $service->add($this->memberHolding('', 'Treasurer'), $this->committee('Logistics'), 'No'));
        });
    }

    public function testMswAsTheSecondCommitteeRaisesTheRoleAndAPromotionEndsIt(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $service = new MemberCommitteeService();
            $users = new UserRepository();
            $userId = (int) $users->findByEmail('member@agile.test')['user_id'];
            $memberId = (int) (new MemberRepository())->findByUser($userId)['member_id'];
            Database::connection()->prepare("UPDATE members SET position_id = ?, membership_type = 'officer', role_status = 'active' WHERE member_id = ?")
                ->execute([$this->position('Logistics', 'Committee Member'), $memberId]);

            $service->add($memberId, $this->committee(config('role_mapping.msw_committee')), 'Approved application');
            $this->assertSame('msw_member', $users->findWithRole($userId)['role_slug']);

            // Becoming a Committee Head ends the second committee (one office) and the role follows the new position.
            (new MembershipService())->changeRole($memberId, $this->position('Finance and Fundraising', 'Deputy Committee Head'), 'Appointed deputy', (int) current_user_id());
            $this->assertSame([], $service->extras($memberId));
            $this->assertSame('general_member', $users->findWithRole($userId)['role_slug']);
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

    private function memberHolding(string $committee, string $title): int
    {
        $statement = Database::connection()->prepare(
            "SELECT m.member_id FROM members m JOIN positions p ON p.position_id = m.position_id LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.title = ? AND ((? = '' AND p.committee_id IS NULL) OR c.name = ?) AND m.role_status = 'active' LIMIT 1"
        );
        $statement->execute([$title, $committee, $committee]);
        $id = (int) $statement->fetchColumn();
        $this->assertGreaterThan(0, $id, "Someone holds {$title} {$committee}");

        return $id;
    }

    private function position(string $committee, string $title): int
    {
        $statement = Database::connection()->prepare('SELECT p.position_id FROM positions p JOIN committees c ON c.committee_id = p.committee_id WHERE p.title = ? AND c.name = ?');
        $statement->execute([$title, $committee]);

        return (int) $statement->fetchColumn();
    }

    private function committee(string $name): int
    {
        $statement = Database::connection()->prepare('SELECT committee_id FROM committees WHERE name = ?');
        $statement->execute([$name]);

        return (int) $statement->fetchColumn();
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
