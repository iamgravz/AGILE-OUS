<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\TestServer;

/** CSRF: a POST without the session's token, or with a wrong one, is refused (419) and changes nothing. */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        TestServer::db()->exec('DELETE FROM rate_limits');
    }

    public function testAFormWithoutItsTokenChangesNothing(): void
    {
        $db = TestServer::db();
        $count = static fn (): int => (int) $db->query("SELECT COUNT(*) FROM service_requests WHERE subject = 'Forged request'")->fetchColumn();
        $member = TestServer::loggedIn('member@agile.test');
        $member->get('/requests');
        $form = ['type' => 'inquiry', 'subject' => 'Forged request', 'body' => 'Sent from another site.'];

        $this->assertSame(419, $member->post('/requests', $form, false)['status'], 'No token.');
        $this->assertSame(419, $member->post('/requests', $form + ['_token' => str_repeat('a', 64)])['status'], 'A wrong token.');
        $this->assertSame(0, $count(), 'Nothing was saved.');

        // The same form with the page's token goes through, so the refusal above is the token and not the form.
        $this->assertSame(302, $member->post('/requests', $form)['status']);
        $this->assertSame(1, $count());
        $db->exec("DELETE m FROM service_request_messages m JOIN service_requests r ON r.request_id = m.request_id WHERE r.subject = 'Forged request'");
        $db->exec("DELETE FROM service_requests WHERE subject = 'Forged request'");
    }

    public function testTheLoginFormNeedsItsTokenToo(): void
    {
        $guest = new TestServer();
        $guest->get('/login');
        $this->assertSame(419, $guest->post('/login', ['email' => 'member@agile.test', 'password' => 'Password123!'], false)['status']);
        $this->assertSame(302, $guest->get('/dashboard')['status'], 'Still signed out.');
    }
}
