<?php

declare(strict_types=1);

use Tests\Support\TestServer;
use PHPUnit\Framework\TestCase;

/**
 * Run for office (Article VI), through the running app: filing, the checks, the decision and marking elected. Uses a
 * candidate made here, so the shared demo accounts keep their roles for the other tests.
 */
final class CandidaciesTest extends TestCase
{
    private const PDF = "%PDF-1.4\n% candidacy test file\n";

    protected function setUp(): void
    {
        TestServer::db()->exec('DELETE FROM rate_limits');
    }

    public function testAMemberFilesAndTheMswHeadDecidesAndMarksElected(): void
    {
        $this->setFiling(false);
        $email = $this->makeMember();
        $candidate = TestServer::loggedIn($email);

        $page = $candidate->get('/elections/candidacy');
        $this->assertStringContainsString('Filing of candidacy is closed', $page['body']);
        $this->setFiling(true);

        $seat = $this->vacate('Year-Level Council', '3rd Year Representative');
        $president = $this->position('', 'President');
        $form = ['year_level' => '3rd Year', 'section' => 'bsitoumn 3-1', 'contact_no' => '09171230001', 'statement' => 'Serve my batch.'];
        $files = ['cor' => ['cor.pdf', self::PDF, 'application/pdf'], 'grades' => ['grades.pdf', self::PDF, 'application/pdf']];

        // Article VI checks at filing: the President needs 3rd year, a representative their own year, and both files.
        $refused = $candidate->uploadFiles('/elections/candidacy', ['position_id' => (string) $president, 'year_level' => '2nd Year'] + $form, $files);
        $this->assertSame(422, $refused['status']);
        $this->assertStringContainsString('at least 3rd year', $refused['body']);
        $refused = $candidate->uploadFiles('/elections/candidacy', ['position_id' => (string) $seat, 'year_level' => '2nd Year'] + $form, $files);
        $this->assertStringContainsString('must be in 3rd year', $refused['body']);
        $refused = $candidate->uploadFiles('/elections/candidacy', ['position_id' => (string) $seat, 'section' => '', 'contact_no' => ''] + $form, $files);
        $this->assertStringContainsString('Section is required', $refused['body']);
        $refused = $candidate->uploadFiles('/elections/candidacy', ['position_id' => (string) $seat] + $form, ['cor' => $files['cor']]);
        $this->assertStringContainsString('previous semester copy of grades', $refused['body']);

        $filed = $candidate->uploadFiles('/elections/candidacy', ['position_id' => (string) $seat] + $form, $files);
        $this->assertSame(302, $filed['status']);
        $row = $this->candidacy($email);
        $this->assertSame('filed', $row['status']);
        $this->assertSame('BSITOUMN 3-1', $row['section']);

        // One candidacy per academic year.
        $again = $candidate->uploadFiles('/elections/candidacy', ['position_id' => (string) $president] + $form, $files);
        $this->assertStringContainsString('already filed', $again['body']);

        // Only the candidate and the MSW Committee Head open the documents.
        $this->assertSame(200, $candidate->get('/elections/candidacy/' . $row['candidacy_id'] . '/files/cor')['status']);
        $this->assertSame(404, TestServer::loggedIn('member@agile.test')->get('/elections/candidacy/' . $row['candidacy_id'] . '/files/cor')['status']);

        $head = TestServer::loggedIn('mswhead@agile.test');
        $this->assertStringContainsString($email, $head->get('/admin/candidacies')['body']);
        $head->post('/admin/candidacies/' . $row['candidacy_id'] . '/decide', ['decision' => 'disqualified', 'remarks' => '']);
        $this->assertSame('filed', $this->candidacy($email)['status'], 'Disqualifying needs the reason.');
        $head->post('/admin/candidacies/' . $row['candidacy_id'] . '/decide', ['decision' => 'qualified', 'remarks' => 'Documents complete']);
        $this->assertSame('qualified', $this->candidacy($email)['status']);

        $head->post('/admin/candidacies/' . $row['candidacy_id'] . '/elected', ['remarks' => 'Elected in the test election']);
        $this->assertSame('elected', $this->candidacy($email)['status']);
        $holder = TestServer::db()->prepare('SELECT m.position_id FROM members m JOIN users u ON u.user_id = m.user_id WHERE u.email = ?');
        $holder->execute([$email]);
        $this->assertSame($seat, (int) $holder->fetchColumn());
        $this->assertSame(1, (int) TestServer::db()->query("SELECT COUNT(*) FROM officer_terms WHERE position_id = {$seat}")->fetchColumn());

        $this->setFiling(false);
    }

    public function testOnlyMembersFileAndOnlyTheMswHeadDecides(): void
    {
        $this->setFiling(true);
        $this->assertStringContainsString('Only AGILE members can run', TestServer::loggedIn('nomember@agile.test')->get('/elections/candidacy')['body']);
        $this->assertSame(403, TestServer::loggedIn('mswmember@agile.test')->get('/admin/candidacies')['status']);
        $this->setFiling(false);
    }

    private function makeMember(): string
    {
        $db = TestServer::db();
        $email = 'candidate.' . bin2hex(random_bytes(4)) . '@agile.test';
        $role = (int) $db->query("SELECT role_id FROM roles WHERE slug = 'general_member'")->fetchColumn();
        $db->prepare("INSERT INTO users (role_id, email, password_hash, first_name, last_name, student_no, contact_no, status, email_verified_at) VALUES (?, ?, ?, 'Sample', 'Candidate', ?, '09170000000', 'active', NOW())")
            ->execute([$role, $email, password_hash('Password123!', PASSWORD_DEFAULT), sprintf('2023-%05d-OU-0', random_int(10000, 99999))]);
        $userId = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO members (user_id, member_no, membership_type, joined_at, registered_at) VALUES (?, ?, 'general', CURDATE(), NOW())")
            ->execute([$userId, 'TC-' . bin2hex(random_bytes(4))]);

        return $email;
    }

    private function setFiling(bool $open): void
    {
        TestServer::db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('elections.filing_open', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
            ->execute([$open ? '1' : '0']);
    }

    private function position(string $committee, string $title): int
    {
        $statement = TestServer::db()->prepare(
            "SELECT p.position_id FROM positions p LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.title = ? AND ((? = '' AND p.committee_id IS NULL) OR c.name = ?)"
        );
        $statement->execute([$title, $committee, $committee]);

        return (int) $statement->fetchColumn();
    }

    /** Frees a seat the demo seeder filled, as an ended term would. */
    private function vacate(string $committee, string $title): int
    {
        $id = $this->position($committee, $title);
        TestServer::db()->prepare("UPDATE members SET position_id = NULL, membership_type = 'general', role_status = 'expired' WHERE position_id = ?")->execute([$id]);
        TestServer::db()->prepare('DELETE FROM officer_terms WHERE position_id = ?')->execute([$id]);

        return $id;
    }

    /** @return array<string, mixed> */
    private function candidacy(string $email): array
    {
        $statement = TestServer::db()->prepare('SELECT c.* FROM election_candidacies c JOIN users u ON u.user_id = c.user_id WHERE u.email = ? ORDER BY c.candidacy_id DESC LIMIT 1');
        $statement->execute([$email]);

        return $statement->fetch();
    }
}
