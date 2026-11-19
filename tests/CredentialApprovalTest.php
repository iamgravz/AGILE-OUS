<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\CredentialRepository;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\CredentialService;
use App\Services\FileAccessPolicy;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** BR-19, TC-33. Needs the seeded database. Everything rolls back, and rolled-back PDFs are removed. */
final class CredentialApprovalTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testNewCredentialsWaitAndTheQrSaysNotYetIssued(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberId();
            $service = new CredentialService();
            $service->issueFor($memberId);
            $id = (new CredentialRepository())->findActive($memberId, 'digital_id');

            $this->assertSame('pending_approval', $id['status']);
            $this->assertSame('new_member', $id['reason']);
            $this->assertSame('not_issued', $service->verify($id['qr_token'])['state']);
            $this->assertFalse($service->verify($id['qr_token'])['valid']);
            $this->assertSame(0, $this->mail('member@agile.test', 'CREDENTIAL_READY'));
        });
    }

    public function testApprovingRegeneratesTheFileAndEmailsTheMemberOnce(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberId();
            $service = new CredentialService();
            $repository = new CredentialRepository();
            $service->issueFor($memberId);
            $id = $repository->findActive($memberId, 'digital_id');
            $certificate = $repository->findActive($memberId, 'certificate');
            $head = $this->signIn('mswhead@agile.test');

            $this->assertSame(2, $service->approve([(int) $id['credential_id'], (int) $certificate['credential_id']], $head));

            $approved = $repository->find((int) $id['credential_id']);
            $this->assertSame('approved', $approved['status']);
            $this->assertSame($head, (int) $approved['approved_by']);
            $this->assertNotSame($id['file_path'], $approved['file_path']);
            $this->assertFileExists(CredentialService::path($approved['file_path']));
            $this->assertFileDoesNotExist(CredentialService::path($id['file_path']));
            $this->assertSame($approved['approved_at'], $approved['issued_at']);
            $this->assertSame($id['qr_token'], $approved['qr_token'], 'A printed QR code keeps working.');
            $this->assertSame('valid', $service->verify($id['qr_token'])['state']);
            $this->assertSame(1, $this->mail('member@agile.test', 'CREDENTIAL_READY'), 'One email per member, not per file.');
            $this->assertSame(1, $this->audit('credential.bulk_approve'));
            $this->assertSame(2, $this->audit('credential.approved'));

            $this->assertSame(0, $service->approve([(int) $id['credential_id']], $head), 'Already approved.');
        });
    }

    public function testReturnNeedsRemarksAndRegenerateMakesANewPendingFile(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberId();
            $service = new CredentialService();
            $repository = new CredentialRepository();
            $service->issueFor($memberId);
            $row = $repository->findActive($memberId, 'digital_id');
            $id = (int) $row['credential_id'];
            $head = $this->signIn('mswhead@agile.test');

            try {
                $service->returnForCorrection($id, '  ', $head);
                $this->fail('Remarks are required.');
            } catch (DomainException) {
                $this->assertSame('pending_approval', $repository->find($id)['status']);
            }

            $service->returnForCorrection($id, 'Name is misspelled', $head);
            $this->assertSame('returned', $repository->find($id)['status']);
            $this->assertSame('Name is misspelled', $repository->find($id)['review_remarks']);
            $this->assertSame('not_issued', $service->verify($row['qr_token'])['state']);
            $this->assertSame(0, $service->approve([$id], $head), 'A returned one cannot be approved directly.');

            $service->regenerate($id, $head);
            $after = $repository->find($id);
            $this->assertSame('pending_approval', $after['status']);
            $this->assertNull($after['review_remarks']);
            $this->assertNotSame($row['file_path'], $after['file_path']);
            $this->assertFileExists(CredentialService::path($after['file_path']));

            $this->expectException(DomainException::class);
            $service->regenerate($id, $head);
        });
    }

    public function testAManualReissueKeepsTheOldOneValidUntilTheNewOneIsApproved(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberId();
            $service = new CredentialService();
            $repository = new CredentialRepository();
            $service->issueFor($memberId);
            $old = $repository->findActive($memberId, 'digital_id');
            $head = $this->signIn('mswhead@agile.test');
            $service->approveAllPending($head);

            $service->reissue($memberId, 'New photo', CredentialService::REASON_REISSUE);
            $this->assertNull($repository->find((int) $old['credential_id'])['revoked_at']);
            $this->assertSame('valid', $service->verify($old['qr_token'])['state']);
            $new = array_values(array_filter($repository->forMember($memberId), static fn (array $c): bool => $c['status'] === 'pending_approval' && $c['type'] === 'digital_id'))[0];
            $this->assertSame((int) $old['credential_id'], (int) $new['replaces_credential_id']);
            $this->assertSame('reissue', $new['reason']);

            $service->approve([(int) $new['credential_id']], $head);
            $this->assertNotNull($repository->find((int) $old['credential_id'])['revoked_at']);
            $this->assertSame('revoked', $service->verify($old['qr_token'])['state']);
        });
    }

    public function testARoleChangeRevokesAtOnceAndTheNewOneWaits(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberId();
            $service = new CredentialService();
            $repository = new CredentialRepository();
            $service->issueFor($memberId);
            $old = $repository->findActive($memberId, 'digital_id');
            $service->approveAllPending($this->signIn('mswhead@agile.test'));

            $service->reissue($memberId, 'Role expired');
            $this->assertSame('revoked', $service->verify($old['qr_token'])['state']);
            $new = $repository->findActive($memberId, 'digital_id');
            $this->assertSame('pending_approval', $new['status']);
            $this->assertSame('role_change', $new['reason']);
        });
    }

    public function testOnlyTheMswHeadActs(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberId();
            $service = new CredentialService();
            $service->issueFor($memberId);
            $id = (int) (new CredentialRepository())->findActive($memberId, 'digital_id')['credential_id'];

            foreach (['mswmember@agile.test', 'president@agile.test', 'member@agile.test'] as $email) {
                $actor = $this->signIn($email);

                foreach ([
                    static fn () => $service->approve([$id], $actor),
                    static fn () => $service->approveAllPending($actor),
                    static fn () => $service->returnForCorrection($id, 'x', $actor),
                    static fn () => $service->regenerate($id, $actor),
                    static fn () => $service->forReview([]),
                ] as $action) {
                    try {
                        $action();
                        $this->fail("{$email} should be refused.");
                    } catch (ForbiddenException) {
                        $this->addToAssertionCount(1);
                    }
                }
            }
        });
    }

    public function testFilesFollowTheStatus(): void
    {
        $this->inRollback(function (): void {
            $memberId = $this->memberId();
            $service = new CredentialService();
            $policy = new FileAccessPolicy();
            $service->issueFor($memberId);
            $id = (int) (new CredentialRepository())->findActive($memberId, 'digital_id')['credential_id'];

            $this->signIn('member@agile.test');
            $this->assertRefused($policy, $id);

            $head = $this->signIn('mswhead@agile.test');
            $this->assertTrue($policy->credential($id)['previewable'], 'The MSW Head previews any status.');
            $service->approve([$id], $head);

            $this->signIn('member@agile.test');
            $this->assertStringEndsWith('.pdf', $policy->credential($id)['absolute_path']);

            $this->signIn('mswmember@agile.test');
            $this->assertRefused($policy, $id);

            $this->signIn('mswhead@agile.test');
            $service->reissue($memberId, 'Role change');
            $this->signIn('member@agile.test');
            $this->assertRefused($policy, $id);
        });
    }

    public function testTheDigestGoesOutOnceADayAndOnlyWhenSomethingWaits(): void
    {
        $this->inRollback(function (): void {
            $service = new CredentialService();
            Database::connection()->exec("UPDATE digital_credentials SET status = 'approved' WHERE status = 'pending_approval'");
            $this->assertSame(0, $service->sendDigest());

            $service->issueFor($this->memberId());
            $this->assertSame(1, $service->sendDigest());
            $this->assertSame(0, $service->sendDigest(), 'A second run the same day sends nothing.');
            $this->assertSame(1, $this->mail('mswhead@agile.test', 'CREDENTIALS_FOR_APPROVAL'));
        });
    }

    private function assertRefused(FileAccessPolicy $policy, int $id): void
    {
        try {
            $policy->credential($id);
            $this->fail('This should be refused.');
        } catch (ForbiddenException) {
            $this->addToAssertionCount(1);
        }
    }

    private function memberId(): int
    {
        return (int) (new MemberRepository())->findByUser((int) (new UserRepository())->findByEmail('member@agile.test')['user_id'])['member_id'];
    }

    private function signIn(string $email): int
    {
        $userId = (int) (new UserRepository())->findByEmail($email)['user_id'];
        $_SESSION['user_id'] = $userId;
        RbacService::clearCache();

        return $userId;
    }

    private function mail(string $email, string $template): int
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM notifications n
             JOIN email_templates t ON t.template_id = n.template_id
             JOIN users u ON u.user_id = n.user_id
             WHERE u.email = ? AND t.code = ? AND n.channel = 'email'"
        );
        $statement->execute([$email, $template]);

        return (int) $statement->fetchColumn();
    }

    private function audit(string $action): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND created_at >= ?');
        $statement->execute([$action, date('Y-m-d H:i:s', time() - 120)]);

        return (int) $statement->fetchColumn();
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
