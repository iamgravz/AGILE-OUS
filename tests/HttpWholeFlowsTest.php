<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\TestServer;

/** TC-04, TC-29, TC-30 and TC-31, start to finish over real HTTP, the way a person without an account goes through them. */
final class HttpWholeFlowsTest extends TestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 72 72]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    protected function setUp(): void
    {
        TestServer::db()->exec('DELETE FROM rate_limits');
    }

    public function testTc04AndTc31ApplyWithoutAnAccountThenApproval(): void
    {
        $db = TestServer::db();
        $email = 'flow.applicant@example.test';
        $number = '2025-55501-OU-0';
        $browser = new TestServer();

        $browser->get('/apply');
        // The first page asks what they apply for and the documents that choice needs (the COR for General Membership).
        $person = ['first_name' => 'Flow', 'last_name' => 'Applicant', 'email' => $email, 'student_no' => $number, 'year_level' => '2nd Year', 'section' => 'bsitoumn 3-2', 'contact_no' => '09171234500', 'privacy_consent' => '1'];
        $browser->post('/apply/code', $person);
        $this->assertSame(0, $this->rowCount('notifications', 'recipient_email = ?', [$email]), 'No code without choosing what they apply for.');

        $browser->get('/apply');
        $browser->post('/apply/code', $person + ['applying_for' => 'general']);
        $this->assertSame(0, $this->rowCount('notifications', 'recipient_email = ?', [$email]), 'No code without the COR.');

        $browser->get('/apply');
        $browser->upload('/apply/code', $person + ['applying_for' => 'general'], 'cor', 'cor.pdf', self::PDF, 'application/pdf');
        $this->assertSame(0, $this->rowCount('applicants', 'email = ?', [$email]), 'Nothing is saved before the code.');

        $browser->get('/apply');
        $page = $browser->follow($browser->post('/apply/verify', ['code' => $this->lastPayload($email, 'GUEST_EMAIL_CODE')['code']]));
        $this->assertStringContainsString('Personal info', $page['body']);

        $response = $browser->post('/apply/new', ['first_name' => 'Flow', 'last_name' => 'Applicant', 'student_no' => $number, 'year_level' => '2nd Year', 'section' => 'BSITOUMN 3-2', 'contact_no' => '09171234500', 'intent' => 'next']);
        $this->assertSame(1, preg_match('#apply/(\d+)#', $response['location'], $match), $response['location']);
        $id = (int) $match[1];
        $browser->follow($response);
        $this->assertSame(1, $this->rowCount('application_documents', "application_id = ? AND doc_type = 'cor'", [$id]), 'The COR from the first page joined the application.');
        $this->assertSame(1, $this->rowCount('applicants', "email = ? AND section = 'BSITOUMN 3-2' AND year_level = '2nd Year' AND contact_no = '09171234500'", [$email]), 'Year level, section and contact number are saved.');
        $this->assertStringContainsString('value="2nd Year" selected', $browser->get("/apply/{$id}/step/2")['body'], 'The year level answers step 2.');

        $browser->follow($browser->post("/apply/{$id}/step/2", $this->answers('academic', '2nd Year') + ['enrolled' => '1', 'intent' => 'next']));
        $this->assertStringContainsString('value="general" checked', $browser->get("/apply/{$id}/step/3")['body'], 'The first page\'s choice is already selected.');
        $browser->follow($browser->post("/apply/{$id}/step/3", ['membership_type' => 'general', 'intent' => 'next']));
        $browser->follow($browser->post("/apply/{$id}/step/4", $this->answers('common', '5 hours a week') + ['intent' => 'next']));

        // General Membership: the current COR only. A School ID is no longer asked for, so its upload is refused; a new
        // COR replaces the one from the first page, which is still pending.
        foreach (['school_id', 'cor'] as $type) {
            $browser->get("/apply/{$id}/step/5");
            $browser->upload("/apply/{$id}/documents/{$type}", [], 'document', "{$type}.pdf", self::PDF, 'application/pdf');
        }

        $this->assertSame(1, $this->rowCount('application_documents', 'application_id = ?', [$id]));
        $browser->get("/apply/{$id}/step/5");
        $browser->follow($browser->post("/apply/{$id}/step/5", ['intent' => 'next']));

        $browser->follow($browser->post("/apply/{$id}/submit", []));
        $this->assertSame('draft', $this->application($id)['status'], 'Consent is required.');

        // TC-04: Submitted, with a tracking code and an email queued.
        $page = $browser->follow($browser->post("/apply/{$id}/submit", ['privacy_consent' => '1']));
        $application = $this->application($id);
        $this->assertSame('submitted', $application['status']);
        $this->assertStringContainsString($application['tracking_code'], $page['body']);
        $this->assertNull($application['user_id'], 'An applicant has no account.');
        $this->assertSame(1, $this->countMail($email, 'APP_SUBMITTED'));

        // Someone else cannot open it.
        $this->assertSame(302, (new TestServer())->get("/apply/{$id}/step/1")['status']);
        $this->assertSame(403, TestServer::loggedIn('member@agile.test')->get("/apply/{$id}/step/1")['status']);

        // TC-31: approval makes a member with a number and no account, and the email explains /register.
        $db->prepare("UPDATE membership_applications SET status = 'under_review' WHERE application_id = ?")->execute([$id]);
        $db->prepare("UPDATE application_documents SET verification_status = 'verified' WHERE application_id = ?")->execute([$id]);
        $head = TestServer::loggedIn('mswhead@agile.test');
        $head->get("/applications/{$id}");
        $head->follow($head->post("/applications/{$id}/approve", ['remarks' => 'Welcome to AGILE']));

        $this->assertSame('approved', $this->application($id)['status']);
        $this->assertSame(0, $this->rowCount('users', 'email = ?', [$email]));
        $member = $db->prepare('SELECT m.member_no, m.user_id FROM members m JOIN applicants ap ON ap.applicant_id = m.applicant_id WHERE ap.email = ?');
        $member->execute([$email]);
        $row = $member->fetch();
        $this->assertNotEmpty($row['member_no']);
        $this->assertNull($row['user_id']);
        $this->assertStringContainsString('/register', json_encode($this->lastPayload($email, 'APP_APPROVED')));
    }

    public function testTc29AndTc30AGuestSubmitsAndTracksAConcern(): void
    {
        $email = 'flow.guest@example.test';
        $browser = new TestServer();

        $browser->get('/welfare/guest');
        $guest = ['name' => 'Flow Guest', 'email' => $email, 'student_no' => '', 'privacy_consent' => '1', 'year_level' => '3rd Year', 'section' => 'bsitoumn 3-1', 'contact_no' => '09171234599'];

        // Year level, section and contact number are required (Nov 23, 2026).
        $page = $browser->post('/welfare/guest/code', ['contact_no' => '', 'section' => ''] + $guest + ['category_id' => '1', 'description' => 'Words']);
        $this->assertSame(422, $page['status']);
        $this->assertStringContainsString('Section is required', $page['body']);
        $this->assertStringContainsString('Contact number is required', $page['body']);

        // The first page asks for the category and the concern too, and a page with a mistake keeps them.
        $page = $browser->post('/welfare/guest/code', $guest + ['category_id' => '1']);
        $this->assertSame(422, $page['status']);
        $this->assertStringContainsString('Description', $page['body']);
        $this->assertSame(0, $this->countMail($email, 'GUEST_EMAIL_CODE'), 'No code without the concern.');

        $browser->follow($browser->post('/welfare/guest/code', $guest + ['category_id' => '1', 'description' => 'FLOWSECRET first page']));

        $page = $browser->follow($browser->post('/welfare/guest/verify', ['code' => '000000']));
        $this->assertStringContainsString('name="code"', $page['body'], 'A wrong code stays on the code page.');
        $this->assertStringNotContainsString('FLOWSECRET', $page['body'], 'The concern is not shown before the email is confirmed.');

        $page = $browser->follow($browser->post('/welfare/guest/verify', ['code' => $this->lastPayload($email, 'GUEST_EMAIL_CODE')['code']]));
        $this->assertStringContainsString('FLOWSECRET first page', $page['body'], 'Once confirmed, the form holds what they wrote on the first page.');
        $page = $browser->follow($browser->post('/welfare/guest/submit', ['category_id' => '1', 'subject' => 'FLOWSECRET subject', 'description' => 'FLOWSECRET words']));

        $details = TestServer::db()->prepare('SELECT submitter_contact_no, submitter_year_level, submitter_section FROM welfare_cases WHERE guest_email_hash = ?');
        $details->execute([(new \App\Services\EncryptionService())->blindIndex($email)]);
        $stored = $details->fetch();
        $this->assertNotSame('09171234599', $stored['submitter_contact_no'], 'The contact number is encrypted.');
        $this->assertSame(['contact_no' => '09171234599', 'year_level' => '3rd Year', 'section' => 'BSITOUMN 3-1'], (new \App\Services\WelfareService())->submitterDetails($stored));

        $case = TestServer::db()->prepare('SELECT case_code, subject, guest_name FROM welfare_cases WHERE guest_email_hash = ?');
        $case->execute([(new \App\Services\EncryptionService())->blindIndex($email)]);
        $row = $case->fetch();
        $this->assertStringContainsString($row['case_code'], $page['body'], 'The case code is shown.');
        $this->assertStringNotContainsString('FLOWSECRET', $row['subject'], 'The text is encrypted.');
        $this->assertSame(1, $this->countMail($email, 'WELFARE_RECEIVED'));

        $tracker = new TestServer();
        $tracker->get('/track');
        $found = $tracker->post('/track', ['code' => $row['case_code'], 'email' => $email]);
        $this->assertStringContainsString('Welfare concern', $found['body']);
        $this->assertStringNotContainsString('FLOWSECRET', $found['body'], 'Status and updates only.');

        $wrong = $tracker->post('/track', ['code' => $row['case_code'], 'email' => 'not.them@example.test']);
        $this->assertStringContainsString('We could not find a match', $wrong['body']);
        $this->assertStringNotContainsString('Welfare concern', $wrong['body']);
    }

    /** @return array<string, string> */
    private function answers(string $roleType, string $value): array
    {
        $statement = TestServer::db()->prepare('SELECT question_id FROM application_questions WHERE role_type = ? AND is_required = 1');
        $statement->execute([$roleType]);
        $answers = [];

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $answers["q[{$id}]"] = $value;
        }

        return $answers;
    }

    /** @return array<string, mixed> */
    private function application(int $id): array
    {
        $statement = TestServer::db()->prepare('SELECT * FROM membership_applications WHERE application_id = ?');
        $statement->execute([$id]);

        return $statement->fetch();
    }

    /** @return array<string, mixed> */
    private function lastPayload(string $email, string $template): array
    {
        $statement = TestServer::db()->prepare('SELECT n.payload_json FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE n.recipient_email = ? AND t.code = ? ORDER BY n.notification_id DESC LIMIT 1');
        $statement->execute([$email, $template]);

        return json_decode((string) $statement->fetchColumn(), true) ?? [];
    }

    private function countMail(string $email, string $template): int
    {
        $statement = TestServer::db()->prepare('SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE n.recipient_email = ? AND t.code = ?');
        $statement->execute([$email, $template]);

        return (int) $statement->fetchColumn();
    }

    /** @param list<mixed> $params */
    private function rowCount(string $table, string $where, array $params): int
    {
        $statement = TestServer::db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }
}
