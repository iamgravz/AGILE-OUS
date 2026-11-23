<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\TestServer;

/** Service requests (Nov 23, 2026), over HTTP: a member sends one, replies and cancels; others cannot see it. */
final class ServiceRequestTest extends TestCase
{
    protected function setUp(): void
    {
        TestServer::db()->exec('DELETE FROM rate_limits');
    }

    public function testTheRulesForANewRequest(): void
    {
        $this->assertArrayHasKey('type', App\Services\ServiceRequestService::errors('welfare', 'x', 'y'));
        $this->assertArrayHasKey('subject', App\Services\ServiceRequestService::errors('inquiry', '', 'y'));
        $this->assertArrayHasKey('body', App\Services\ServiceRequestService::errors('inquiry', 'x', str_repeat('a', 3001)));
        $this->assertSame([], App\Services\ServiceRequestService::errors('inquiry', 'x', 'y'));
        $this->assertSame('REQ-000042', App\Services\ServiceRequestService::code(42));
    }

    public function testAMemberSendsRepliesAndCancels(): void
    {
        $db = TestServer::db();
        $member = TestServer::loggedIn('member@agile.test');
        $this->assertStringContainsString('New request', $member->get('/requests')['body']);

        $refused = $member->follow($member->post('/requests', ['type' => 'record_update', 'subject' => '', 'body' => 'x']))['body'];
        $this->assertStringContainsString('Enter a subject', $refused);

        $response = $member->post('/requests', ['type' => 'record_update', 'subject' => 'Wrong section on my record', 'body' => 'It should be BSITOUMN 3-2.']);
        $this->assertSame(1, preg_match('#/requests/(\d+)#', (string) $response['location'], $match));
        $id = (int) $match[1];
        $page = $member->follow($response)['body'];
        $this->assertStringContainsString('was sent to the MSW Committee', $page);
        $this->assertStringContainsString('It should be BSITOUMN 3-2.', $page);

        // Someone else gets a 403; MSW staff can open and reply, and the reply is marked as staff.
        $this->assertSame(403, TestServer::loggedIn('head@agile.test')->get("/requests/{$id}")['status']);
        $staff = TestServer::loggedIn('mswmember@agile.test');
        $staff->get("/requests/{$id}");
        $staff->follow($staff->post("/requests/{$id}/reply", ['body' => 'We will fix it today.']));
        $this->assertSame(1, (int) $db->query("SELECT from_staff FROM service_request_messages WHERE request_id = {$id} AND body = 'We will fix it today.'")->fetchColumn());

        $member->get("/requests/{$id}");
        $member->follow($member->post("/requests/{$id}/cancel", []));
        $this->assertSame('cancelled', $db->query("SELECT status FROM service_requests WHERE request_id = {$id}")->fetchColumn());

        $member->get("/requests/{$id}");
        $closed = $member->follow($member->post("/requests/{$id}/reply", ['body' => 'One more thing']))['body'];
        $this->assertStringContainsString('takes no more replies', $closed);

        $db->exec("DELETE FROM service_request_messages WHERE request_id = {$id}");
        $db->exec("DELETE FROM service_requests WHERE request_id = {$id}");
    }

    public function testMswStaffWorkTheQueueAndTheMemberIsTold(): void
    {
        $db = TestServer::db();
        $member = TestServer::loggedIn('member@agile.test');
        $response = $member->post('/requests', ['type' => 'document', 'subject' => 'Certification of membership', 'body' => 'For my scholarship.']);
        preg_match('#/requests/(\d+)#', (string) $response['location'], $match);
        $id = (int) $match[1];
        $this->assertGreaterThan(0, (int) $db->query("SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE t.code = 'SERVICE_REQUEST_NEW' AND n.payload_json LIKE '%REQ-" . sprintf('%06d', $id) . "%'")->fetchColumn(), 'MSW staff hear about it.');

        $this->assertSame(403, $member->get('/service-requests')['status'], 'Members have no queue.');
        $staff = TestServer::loggedIn('mswmember@agile.test');
        $this->assertStringContainsString('Certification of membership', $staff->get('/service-requests')['body']);
        $this->assertStringContainsString('Handle this request', $staff->get("/requests/{$id}")['body']);

        // A staff reply takes an open request in progress and assigns it; resolving tells the member.
        $staff->follow($staff->post("/requests/{$id}/reply", ['body' => 'We will email it tomorrow.']));
        $row = $db->query("SELECT status, assigned_to FROM service_requests WHERE request_id = {$id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('in_progress', $row['status']);
        $this->assertNotNull($row['assigned_to']);

        $staff->get("/requests/{$id}");
        $staff->follow($staff->post("/requests/{$id}/manage", ['status' => 'resolved', 'assigned_to' => (string) $row['assigned_to']]));
        $this->assertSame('resolved', $db->query("SELECT status FROM service_requests WHERE request_id = {$id}")->fetchColumn());
        $told = (int) $db->query("SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id JOIN users u ON u.user_id = n.user_id WHERE t.code = 'SERVICE_REQUEST_UPDATE' AND u.email = 'member@agile.test' AND n.payload_json LIKE '%Resolved%'")->fetchColumn();
        $this->assertGreaterThan(0, $told, 'The member is told it was resolved.');

        $staff->get("/requests/{$id}");
        $refused = $staff->follow($staff->post("/requests/{$id}/manage", ['status' => 'cancelled', 'assigned_to' => '']))['body'];
        $this->assertStringContainsString('Choose open, in progress, resolved or closed', $refused);

        $db->exec("DELETE FROM service_request_messages WHERE request_id = {$id}");
        $db->exec("DELETE FROM service_requests WHERE request_id = {$id}");
    }
}
