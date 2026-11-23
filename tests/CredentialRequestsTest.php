<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Models\CredentialRepository;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\CredentialService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestServer;

/** ID and certificate requests (Nov 23, 2026): members ask, the MSW Committee Head uploads from AGILE's template. */
final class CredentialRequestsTest extends TestCase
{
    protected function setUp(): void
    {
        TestServer::db()->exec('DELETE FROM rate_limits');
    }

    protected function tearDown(): void
    {
        Config::set('credentials.mode', 'generated');
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testManualModeDrawsNothing(): void
    {
        Config::set('credentials.mode', 'manual');
        $this->assertTrue(CredentialService::manual());

        $sentinel = new RuntimeException('Roll back the test.');

        try {
            Database::transaction(function () use ($sentinel): void {
                $_SESSION['user_id'] = (int) (new UserRepository())->findByEmail('mswhead@agile.test')['user_id'];
                RbacService::clearCache();
                $member = (new MemberRepository())->findByUser((int) (new UserRepository())->findByEmail('member@agile.test')['user_id']);
                $before = count((new CredentialRepository())->forMember((int) $member['member_id']));

                (new CredentialService())->issueFor((int) $member['member_id']);
                (new CredentialService())->reissue((int) $member['member_id'], 'Role change');
                $this->assertCount($before, (new CredentialRepository())->forMember((int) $member['member_id']), 'Nothing drawn, nothing revoked.');

                throw $sentinel;
            });
        } catch (RuntimeException $exception) {
            if ($exception !== $sentinel) {
                throw $exception;
            }
        }
    }

    public function testAMemberRequestsAndTheMswHeadUploads(): void
    {
        $db = TestServer::db();
        $email = $this->makeMember();
        $member = TestServer::loggedIn($email);

        // The digital ID needs a half-body picture; only JPG or PNG.
        $member->post('/account/credential-requests', ['type' => 'digital_id', 'note' => 'No picture']);
        $member->uploadFiles('/account/credential-requests', ['type' => 'digital_id', 'note' => 'A PDF'], ['id_photo' => ['me.pdf', "%PDF-1.4\n", 'application/pdf']]);
        $this->assertSame([], $this->requests($email), 'Refused without a JPG or PNG picture.');

        $member->uploadFiles('/account/credential-requests', ['type' => 'digital_id', 'note' => 'For the general assembly'], ['id_photo' => ['me.png', $this->png(), 'image/png']]);
        $member->uploadFiles('/account/credential-requests', ['type' => 'digital_id', 'note' => 'Again'], ['id_photo' => ['me.png', $this->png(), 'image/png']]);
        $member->post('/account/credential-requests', ['type' => 'certificate', 'note' => '']);
        $rows = $this->requests($email);
        $this->assertEquals(['certificate' => 'requested', 'digital_id' => 'requested'], array_column($rows, 'status', 'type'), 'One open request per kind.');
        $this->assertCount(2, $rows);

        $head = TestServer::loggedIn('mswhead@agile.test');
        $list = $head->get('/credentials/requests');
        $this->assertSame(200, $list['status']);
        $this->assertStringContainsString('For the general assembly', $list['body']);
        $this->assertSame(403, TestServer::loggedIn('mswmember@agile.test')->get('/credentials/requests')['status']);

        $byType = array_column($rows, null, 'type');
        $this->assertNotNull($byType['digital_id']['photo_path']);
        $this->assertNull($byType['certificate']['photo_path'], 'The certificate needs no picture.');
        $photo = $head->get('/credentials/requests/' . $byType['digital_id']['request_id'] . '/photo?inline=1');
        $this->assertSame(200, $photo['status']);
        $this->assertSame('image/png', (new finfo(FILEINFO_MIME_TYPE))->buffer($photo['body']));
        $this->assertSame(403, $member->get('/credentials/requests/' . $byType['digital_id']['request_id'] . '/photo')['status']);
        $idRequest = (int) $byType['digital_id']['request_id'];
        $refused = $head->uploadFiles("/credentials/requests/{$idRequest}/upload", [], ['file' => ['id.exe', "MZ\x90\x00 not a document", 'application/octet-stream']]);
        $this->assertSame(302, $refused['status']);
        $this->assertSame('requested', $this->statusOf($idRequest), 'Only PDF, JPG or PNG.');

        $head->uploadFiles("/credentials/requests/{$idRequest}/upload", [], ['file' => ['id.png', $this->png(), 'image/png']]);
        $this->assertSame('issued', $this->statusOf($idRequest));
        $credentialId = (int) $db->query("SELECT credential_id FROM credential_requests WHERE request_id = {$idRequest}")->fetchColumn();
        $credential = $db->query("SELECT status, revoked_at, file_path FROM digital_credentials WHERE credential_id = {$credentialId}")->fetch();
        $this->assertSame('approved', $credential['status']);
        $this->assertNull($credential['revoked_at']);
        $this->assertStringEndsWith('.png', $credential['file_path']);

        // The member downloads it; another member cannot.
        $this->assertSame(200, $member->get("/files/credential/{$credentialId}")['status']);
        $this->assertNotSame(200, TestServer::loggedIn('member@agile.test')->get("/files/credential/{$credentialId}")['status']);

        // One QR code per member: the MSW Committee Head downloads it; scanning it shows the current ID and active status.
        $memberId = (int) $byType['digital_id']['member_id'];
        // The QR is on AGILE's own ID template now (Nov 23, 2026), so the system offers no QR download.
        $this->assertSame(404, $head->get("/credentials/qr/{$memberId}")['status']);
        TestServer::db()->prepare('UPDATE members SET verify_token = ? WHERE member_id = ? AND verify_token IS NULL')->execute([bin2hex(random_bytes(32)), $memberId]);
        $token = (string) $db->query("SELECT verify_token FROM members WHERE member_id = {$memberId}")->fetchColumn();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertSame($token, (string) $db->query("SELECT verify_token FROM members WHERE member_id = {$memberId}")->fetchColumn(), 'The same code every time.');

        $public = new TestServer();
        $page = $public->get("/verify/member/{$token}");
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Active AGILE member', $page['body']);
        $this->assertStringContainsString('Seeded Requester', $page['body']);
        $this->assertSame(200, $public->get("/verify/member/{$token}/id")['status']);
        $this->assertSame(404, $public->get('/verify/member/' . str_repeat('a', 64))['status']);

        // Declining needs the reason, which the member sees.
        $certRequest = (int) $byType['certificate']['request_id'];
        $head->post("/credentials/requests/{$certRequest}/decline", ['remarks' => '']);
        $this->assertSame('requested', $this->statusOf($certRequest));
        $head->post("/credentials/requests/{$certRequest}/decline", ['remarks' => 'Please update your photo first']);
        $this->assertSame('declined', $this->statusOf($certRequest));
    }

    private function statusOf(int $id): string
    {
        return (string) TestServer::db()->query("SELECT status FROM credential_requests WHERE request_id = {$id}")->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    private function requests(string $email): array
    {
        $statement = TestServer::db()->prepare('SELECT r.* FROM credential_requests r JOIN members m ON m.member_id = r.member_id JOIN users u ON u.user_id = m.user_id WHERE u.email = ? ORDER BY r.type');
        $statement->execute([$email]);

        return $statement->fetchAll();
    }

    private function makeMember(): string
    {
        $db = TestServer::db();
        $email = 'idrequest.' . bin2hex(random_bytes(4)) . '@agile.test';
        $role = (int) $db->query("SELECT role_id FROM roles WHERE slug = 'general_member'")->fetchColumn();
        $db->prepare("INSERT INTO users (role_id, email, password_hash, first_name, last_name, contact_no, status, email_verified_at) VALUES (?, ?, ?, 'Seeded', 'Requester', '09170000000', 'active', NOW())")
            ->execute([$role, $email, password_hash('Password123!', PASSWORD_DEFAULT)]);
        $db->prepare("INSERT INTO members (user_id, member_no, membership_type, joined_at, registered_at) VALUES (?, ?, 'general', CURDATE(), NOW())")
            ->execute([(int) $db->lastInsertId(), 'TR-' . bin2hex(random_bytes(4))]);

        return $email;
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
