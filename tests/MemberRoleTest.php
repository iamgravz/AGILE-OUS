<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\CredentialRepository;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\CredentialService;
use App\Services\FileAccessPolicy;
use App\Services\MembershipService;
use App\Services\RbacService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Member credentials and changeRole(). Needs the seeded database. Every test runs in a transaction that rolls
 * back, which also removes the PDFs it wrote, so nothing is left behind.
 */
final class MemberRoleTest extends TestCase
{
    private const MSW = 'Membership and Student Welfare';

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testIssuingCreatesBothPdfsWithTokensAndSerials(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberIdOf('member@agile.test');
            (new CredentialService())->issueFor($memberId);

            $credentials = (new CredentialRepository())->forMember($memberId);
            $this->assertCount(2, $credentials);

            foreach ($credentials as $credential) {
                $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $credential['qr_token']);
                $this->assertMatchesRegularExpression('/^(ID|CERT)-AGILE-\d{4}-\d{4}-01$/', $credential['serial_no']);
                $this->assertStringStartsWith('%PDF-', (string) file_get_contents(CredentialService::path($credential['file_path'])));
            }
        });
    }

    public function testIssuingTwiceDoesNotDuplicate(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberIdOf('member@agile.test');
            $service = new CredentialService();
            $service->issueFor($memberId);
            $service->issueFor($memberId);

            $this->assertCount(2, (new CredentialRepository())->forMember($memberId));
        });
    }

    public function testRolledBackIssueRemovesItsFiles(): void
    {
        $paths = [];

        $this->inRollback(function () use (&$paths): void {
            $memberId = $this->memberIdOf('member@agile.test');
            (new CredentialService())->issueFor($memberId);
            $paths = array_map(static fn (array $c): string => CredentialService::path($c['file_path']), (new CredentialRepository())->forMember($memberId));
            $this->assertFileExists($paths[0]);
        });

        $this->assertCount(2, $paths);

        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function testVerifyShowsValidThenRevokedWithOnlyAllowedFields(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberIdOf('member@agile.test');
            $service = new CredentialService();
            $service->issueFor($memberId);
            $token = (new CredentialRepository())->findActive($memberId, 'digital_id')['qr_token'];

            $this->assertSame(['valid', 'state', 'revoked_at'], array_keys($service->verify($token)), 'Nothing about the person while it waits.');

            $_SESSION['user_id'] = $this->userIdOf('mswhead@agile.test');
            RbacService::clearCache();
            $service->approveAllPending($this->userIdOf('mswhead@agile.test'));
            $result = $service->verify($token);
            $this->assertTrue($result['valid']);
            $this->assertSame(['valid', 'state', 'revoked_at', 'name', 'member_no', 'role', 'issued_at'], array_keys($result));
            $this->assertSame('Miguel Reyes', $result['name']);

            $service->reissue($memberId, 'Lost ID');
            $this->assertFalse($service->verify($token)['valid']);
        });
    }

    public function testVerifyRejectsUnknownAndMalformedTokens(): void
    {
        $service = new CredentialService();

        $this->assertNull($service->verify(str_repeat('0', 64)));
        $this->assertNull($service->verify("' OR 1=1 --"));
    }

    public function testReissueRequiresAReason(): void
    {
        $this->expectException(DomainException::class);
        (new CredentialService())->reissue($this->memberIdOf('member@agile.test'), '  ');
    }

    public function testChangeRoleToGeneralMemberRevokesIssuesAndAddsHistory(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberIdOf('head@agile.test');
            $actorId = $this->userIdOf('mswhead@agile.test');
            $members = new MemberRepository();
            $credentials = new CredentialRepository();
            $service = new CredentialService();
            $service->issueFor($memberId);
            $oldToken = $credentials->findActive($memberId, 'digital_id')['qr_token'];
            $historyBefore = count($members->history($memberId));

            (new MembershipService())->changeRole($memberId, null, 'Term ended', $actorId);

            $member = $members->findDetailed($memberId);
            $this->assertSame('general_member', $member['role_slug']);
            $this->assertNull($member['position_id']);
            $this->assertSame('general', $member['membership_type']);
            $this->assertSame('expired', $member['role_status']);
            $this->assertNotNull($member['role_expired_at']);
            $this->assertSame('active', $member['status'], 'BR-13: the person stays an active member.');

            $this->assertFalse($service->verify($oldToken)['valid']);
            $all = $credentials->forMember($memberId);
            $this->assertCount(4, $all);
            $this->assertCount(2, array_filter($all, static fn (array $c): bool => $c['revoked_at'] === null));
            $this->assertStringEndsWith('-02', $credentials->findActive($memberId, 'certificate')['serial_no']);

            $history = $members->history($memberId);
            $this->assertCount($historyBefore + 1, $history);
            $last = end($history);
            $this->assertSame('Committee Head, Academics and Training', $last['old_role']);
            $this->assertSame('General Member', $last['new_role']);
            $this->assertSame('Term ended', $last['reason']);

            $head = Database::connection()->query("SELECT head_user_id FROM committees WHERE name = 'Academics and Training'")->fetchColumn();
            $this->assertNull($head);

            $audit = Database::connection()->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'member.role_changed' AND entity_id = ?");
            $audit->execute([$memberId]);
            $this->assertSame(1, (int) $audit->fetchColumn());
        });
    }

    /** @return array<string, array{?string, string, string, string}> */
    public static function positions(): array
    {
        return [
            'President' => [null, 'President', 'president', 'officer'],
            'Vice President' => [null, 'Vice President for Internal Affairs', 'execomm', 'officer'],
            'MSW head' => [self::MSW, 'Committee Head', 'msw_head', 'officer'],
            'MSW deputy' => [self::MSW, 'Deputy Committee Head', 'msw_member', 'officer'],
            'MSW member' => [self::MSW, 'Committee Member', 'msw_member', 'officer'],
            'other head' => ['Events and External Relations', 'Committee Head', 'committee_head', 'officer'],
            'other deputy' => ['Events and External Relations', 'Deputy Committee Head', 'general_member', 'officer'],
            'other member' => ['Events and External Relations', 'Committee Member', 'general_member', 'officer'],
            'Source Code member' => ['The Source Code', 'Writer', 'general_member', 'officer'],
        ];
    }

    #[DataProvider('positions')]
    public function testChangeRoleMapsPositionsToSectionFiveRoles(?string $committee, string $title, string $slug, string $type): void
    {
        $this->inRollback(function () use ($committee, $title, $slug, $type): void {
            $memberId = $this->memberIdOf('member@agile.test');
            $positionId = $this->positionId($committee, $title);

            (new MembershipService())->changeRole($memberId, $positionId, 'Appointed', $this->userIdOf('mswhead@agile.test'));

            $member = (new MemberRepository())->findDetailed($memberId);
            $this->assertSame($slug, $member['role_slug']);
            $this->assertSame($positionId, (int) $member['position_id']);
            $this->assertSame($type, $member['membership_type']);
            $this->assertSame('active', $member['role_status']);
            $this->assertNull($member['role_expired_at']);
        });
    }

    public function testAppointingACommitteeHeadSetsTheCommitteeHead(): void
    {
        $this->inRollback(function (): void {
            $userId = $this->userIdOf('member@agile.test');

            (new MembershipService())->changeRole($this->memberIdOf('member@agile.test'), $this->positionId('Events and External Relations', 'Committee Head'), 'Appointed', $this->userIdOf('mswhead@agile.test'));

            $head = Database::connection()->query("SELECT head_user_id FROM committees WHERE name = 'Events and External Relations'")->fetchColumn();
            $this->assertSame($userId, (int) $head);
        });
    }

    public function testChangeRoleRefusesNoChangeUnknownPositionAndMissingReason(): void
    {
        $service = new MembershipService();
        $actorId = $this->userIdOf('mswhead@agile.test');
        $general = $this->memberIdOf('member@agile.test');
        $head = $this->memberIdOf('head@agile.test');
        $cases = [
            [$general, null, 'Reason'],
            [$head, $this->positionId('Academics and Training', 'Committee Head'), 'Reason'],
            [$general, 999999, 'Reason'],
            [$general, $this->positionId('Events and External Relations', 'Deputy Committee Head'), '   '],
            [999999, null, 'Reason'],
        ];

        foreach ($cases as [$memberId, $positionId, $reason]) {
            try {
                $service->changeRole($memberId, $positionId, $reason, $actorId);
                $this->fail('changeRole should have refused this.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFailedChangeRoleLeavesNothingBehind(): void
    {
        $memberId = $this->memberIdOf('head@agile.test');
        $members = new MemberRepository();
        $before = $members->findDetailed($memberId);

        try {
            // A user that does not exist breaks the history row's foreign key, after the role has been updated.
            (new MembershipService())->changeRole($memberId, null, 'Term ended', 999999);
            $this->fail('The foreign key should have stopped this.');
        } catch (PDOException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $members->findDetailed($memberId));
    }

    public function testOnlyTheOwnerCanDownloadAndOnlyTheCurrentCopy(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberIdOf('member@agile.test');
            $service = new CredentialService();
            $repository = new CredentialRepository();
            $policy = new FileAccessPolicy();
            $service->issueFor($memberId);
            $id = (int) $repository->findActive($memberId, 'digital_id')['credential_id'];

            $_SESSION['user_id'] = $this->userIdOf('mswhead@agile.test');
            RbacService::clearCache();
            $service->approve([$id], $this->userIdOf('mswhead@agile.test'));

            $_SESSION['user_id'] = $this->userIdOf('member@agile.test');
            RbacService::clearCache();
            $this->assertStringEndsWith('.pdf', $policy->credential($id)['absolute_path']);

            foreach (['head@agile.test', 'mswmember@agile.test', 'president@agile.test'] as $email) {
                $_SESSION['user_id'] = $this->userIdOf($email);
                RbacService::clearCache();

                try {
                    $policy->credential($id);
                    $this->fail("{$email} should not be able to download this.");
                } catch (ForbiddenException) {
                    $this->addToAssertionCount(1);
                }
            }

            $service->reissue($memberId, 'Lost ID');
            $_SESSION['user_id'] = $this->userIdOf('member@agile.test');
            RbacService::clearCache();
            $this->expectException(ForbiddenException::class);
            $policy->credential($id);
        });
    }

    public function testCredentialPathStaysInsideItsFolder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CredentialService::path('../uploads/secret.pdf');
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

    private function userIdOf(string $email): int
    {
        return (int) (new UserRepository())->findByEmail($email)['user_id'];
    }

    private function memberIdOf(string $email): int
    {
        return (int) (new MemberRepository())->findByUser($this->userIdOf($email))['member_id'];
    }

    private function positionId(?string $committee, string $title): int
    {
        $statement = Database::connection()->prepare(
            'SELECT p.position_id FROM positions p LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.title = ? AND ' . ($committee === null ? 'p.committee_id IS NULL' : 'c.name = ?')
        );
        $statement->execute($committee === null ? [$title] : [$title, $committee]);

        return (int) $statement->fetchColumn();
    }
}
