<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\TestServer;

/** The section 13 test cases that only mean something over real HTTP. Runs against the test server's own database. */
final class HttpFlowsTest extends TestCase
{
    protected function setUp(): void
    {
        TestServer::db()->exec('DELETE FROM rate_limits');
    }

    public function testTc01HomeRenders(): void
    {
        $browser = new TestServer();
        $response = $browser->get('/');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('name="viewport"', $response['body']);
        $this->assertStringContainsString('Academic Guild of IT Learners and Experts', $response['body']);
    }

    public function testSecurityHeadersAndCookieFlags(): void
    {
        $browser = new TestServer();
        $browser->get('/login');

        $this->assertStringContainsString("default-src 'self'", $browser->headers['content-security-policy']);
        $this->assertSame('nosniff', $browser->headers['x-content-type-options']);
        $this->assertSame('DENY', $browser->headers['x-frame-options']);
        $this->assertArrayHasKey('referrer-policy', $browser->headers);
        $this->assertStringContainsStringIgnoringCase('HttpOnly', $browser->headers['session-cookie']);
        $this->assertStringContainsStringIgnoringCase('SameSite=Lax', $browser->headers['session-cookie']);
    }

    public function testTc02AndTc03TrackingAndItsLimit(): void
    {
        $browser = new TestServer();
        $browser->get('/track');

        $response = $browser->post('/track', ['code' => 'FJKBRQJTPMC5', 'email' => 'andrea.santos@agile.test']);
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Submitted', $response['body']);

        $message = 'We could not find a match for that code and email.';
        $wrongEmail = $browser->post('/track', ['code' => 'FJKBRQJTPMC5', 'email' => 'someone.else@example.test']);
        $unknownCode = $browser->post('/track', ['code' => 'NOSUCHCODE12', 'email' => 'andrea.santos@agile.test']);
        $this->assertStringContainsString($message, $wrongEmail['body']);
        $this->assertStringContainsString($message, $unknownCode['body'], 'Both failures read the same.');

        do {
            $last = $browser->post('/track', ['code' => 'NOSUCHCODE12', 'email' => 'x@example.test']);
        } while ($last['status'] === 200 && ($tries = ($tries ?? 0) + 1) < 10);

        $this->assertSame(429, $last['status']);
        $this->assertStringContainsString('Too many tries', $last['body']);
    }

    public function testTc05WrongTypesAndLargeFilesAreRefused(): void
    {
        $browser = TestServer::loggedIn('member@agile.test');
        $browser->get('/welfare/new');
        $count = static fn (): int => (int) TestServer::db()->query('SELECT COUNT(*) FROM welfare_attachments')->fetchColumn();
        $before = $count();
        $form = ['category_id' => '1', 'subject' => 'Upload check', 'description' => 'Words', 'privacy_consent' => '1', 'year_level' => '2nd Year', 'section' => 'BSITOUMN 2-1', 'contact_no' => '09171234567'];

        $exe = $browser->upload('/welfare/new', $form, 'attachments[]', 'program.exe', "MZ\x90\x00 not a document", 'application/octet-stream');
        $this->assertStringContainsString('not allowed', $exe['body']);

        $disguised = $browser->upload('/welfare/new', $form, 'attachments[]', 'report.pdf', 'plain text, not a PDF', 'application/pdf');
        $this->assertStringContainsString('do not match', $disguised['body']);

        // A real PDF header, over the 5 MB limit but under PHP's own post limit where the server allows it.
        $large = $browser->upload('/welfare/new', $form, 'attachments[]', 'large.pdf', '%PDF-1.4' . str_repeat('0', 10 * 1024 * 1024), 'application/pdf');
        $page = $browser->follow($large)['body'];
        $this->assertStringContainsString('too large', $page);

        $this->assertSame($before, $count(), 'Nothing was saved.');
    }

    public function testTc06AnMswMemberCannotApproveByDirectPost(): void
    {
        $browser = TestServer::loggedIn('mswmember@agile.test');
        $id = (int) TestServer::db()->query("SELECT application_id FROM membership_applications WHERE tracking_code = '6M3DRHUUHT49'")->fetchColumn();

        $page = $browser->get("/applications/{$id}");
        $this->assertSame(200, $page['status']);
        $this->assertStringNotContainsString("/applications/{$id}/approve", $page['body'], 'The button is hidden.');
        $this->assertStringContainsString('Interview questions (8)', $page['body'], 'The Interviews tab lists the core and general questions of the bank.');
        $this->assertStringContainsString('Please introduce yourself', $page['body']);

        $response = $browser->post("/applications/{$id}/approve", ['remarks' => 'Forced']);
        $this->assertSame(403, $response['status']);

        $status = TestServer::db()->query("SELECT status FROM membership_applications WHERE application_id = {$id}")->fetchColumn();
        $this->assertSame('under_review', $status);
        $this->assertGreaterThan(0, (int) TestServer::db()->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'access.denied' AND details_json LIKE '%/applications/{$id}/approve%'")->fetchColumn());
    }

    public function testTc15OtherRolesOpeningACaseAreDeniedAndLogged(): void
    {
        $owner = TestServer::loggedIn('nomember@agile.test');
        $owner->get('/welfare/new');
        $response = $owner->post('/welfare/new', ['category_id' => '1', 'subject' => 'Private', 'description' => 'Words', 'privacy_consent' => '1', 'year_level' => '2nd Year', 'section' => 'BSITOUMN 2-1', 'contact_no' => '09171234567']);
        $this->assertStringContainsString('/my-welfare-cases/', $response['location']);
        $id = (int) substr((string) strrchr($response['location'], '/'), 1);

        foreach (['mswmember@agile.test', 'execomm@agile.test', 'head@agile.test'] as $email) {
            $before = (int) TestServer::db()->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'welfare.access_denied'")->fetchColumn();
            $this->assertSame(403, TestServer::loggedIn($email)->get("/welfare/{$id}")['status'], $email);
            $this->assertSame($before + 1, (int) TestServer::db()->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'welfare.access_denied'")->fetchColumn());
        }

        $this->assertSame(200, TestServer::loggedIn('president@agile.test')->get("/welfare/{$id}")['status']);
    }

    public function testTc19FiveWrongPasswordsLockTheAccount(): void
    {
        $browser = new TestServer();
        $browser->get('/login');

        for ($i = 0; $i < 5; $i++) {
            $browser->post('/login', ['email' => 'mswdeputy@agile.test', 'password' => 'wrong-password']);
        }

        $until = (string) TestServer::db()->query("SELECT locked_until FROM users WHERE email = 'mswdeputy@agile.test'")->fetchColumn();
        $this->assertGreaterThan(date('Y-m-d H:i:s', time() + 14 * 60), $until);
        $this->assertLessThanOrEqual(date('Y-m-d H:i:s', time() + 15 * 60 + 5), $until);

        $page = $browser->follow($browser->post('/login', ['email' => 'mswdeputy@agile.test', 'password' => 'Password123!']));
        $this->assertStringContainsString('name="password"', $page['body'], 'Even the right password stays on the login page.');

        TestServer::db()->exec("UPDATE users SET locked_until = NULL, failed_logins = 0 WHERE email = 'mswdeputy@agile.test'");
    }

    public function testTc20ExecommDashboardIsReadOnly(): void
    {
        $browser = TestServer::loggedIn('execomm@agile.test');
        $page = $browser->get('/dashboard');

        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('View only', $page['body']);
        $this->assertSame(403, $browser->post('/recruitment/vacancies/create', ['title' => 'x'])['status']);
    }

    public function testTc21AMissingTokenGets419(): void
    {
        $browser = new TestServer();
        $browser->get('/login');

        $this->assertSame(419, $browser->post('/login', ['email' => 'x@example.test', 'password' => 'y'], false)['status']);
    }

    public function testTc22InjectionInSearchIsHarmless(): void
    {
        $browser = TestServer::loggedIn('mswhead@agile.test');
        $users = (int) TestServer::db()->query('SELECT COUNT(*) FROM users')->fetchColumn();

        foreach (["' OR 1=1; DROP TABLE users; -- ", '" UNION SELECT password_hash FROM users -- ', '%_%'] as $attack) {
            $page = $browser->get('/search?q=' . rawurlencode($attack));
            $this->assertSame(200, $page['status']);
            $this->assertStringNotContainsString('$2y$', $page['body']);
        }

        $this->assertSame($users, (int) TestServer::db()->query('SELECT COUNT(*) FROM users')->fetchColumn());

        $page = $browser->get('/search?q=' . rawurlencode('<script>alert(1)</script>'));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page['body']);
        $this->assertStringContainsString('&lt;script&gt;', $page['body']);
    }

    public function testTc23AnUnapprovedPostIsNotPublic(): void
    {
        $db = TestServer::db();
        $db->exec("INSERT INTO content_posts (type, title, slug, excerpt, body, status, author_id) SELECT 'announcement', 'Secret draft', 'secret-draft-post', 'E', '<p>B</p>', 'for_approval', user_id FROM users WHERE email = 'head@agile.test'");
        $browser = new TestServer();

        $this->assertSame(404, $browser->get('/posts/secret-draft-post')['status']);
        $this->assertStringNotContainsString('Secret draft', $browser->get('/announcements')['body']);
    }

    public function testARecordThatDoesNotExistIsNotFound(): void
    {
        $browser = TestServer::loggedIn('mswhead@agile.test');

        foreach (['/admin/users/999999', '/admin/committees/999999', '/admin/positions/999999', '/email-templates/999999', '/emails/999999', '/applications/999999', '/welfare/999999'] as $path) {
            $this->assertSame(404, $browser->get($path)['status'], $path);
        }
    }

    public function testTheLogoutEndsTheSession(): void
    {
        $browser = TestServer::loggedIn('member@agile.test');
        $this->assertSame(200, $browser->get('/dashboard')['status']);
        $browser->post('/logout');
        $this->assertStringContainsString('/login', $browser->get('/dashboard')['location']);
    }
}
