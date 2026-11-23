<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\TestServer;

/** The redesigned public pages (Nov 23, 2026): real data in each, no inline styles, and Apply arriving pre-chosen. */
final class PortalPagesTest extends TestCase
{
    protected function setUp(): void
    {
        TestServer::db()->exec('DELETE FROM rate_limits');
    }

    public function testEveryPublicPageRendersTheBandWithoutInlineStyles(): void
    {
        $server = new TestServer();

        foreach (['/about' => 'about agile', '/committees' => 'committees', '/positions' => 'open positions', '/news' => 'news', '/events' => 'events', '/publications' => 'official student publication'] as $path => $eyebrow) {
            $page = $server->get($path);
            $this->assertSame(200, $page['status'], $path);
            $this->assertStringContainsString('css/portal.css', $page['body'], $path);
            $this->assertStringContainsString('class="px-eyebrow">' . $eyebrow, $page['body'], $path);
            $this->assertDoesNotMatchRegularExpression('/<main[\s\S]*style="/', $page['body'], "{$path} has an inline style, which the CSP blocks.");
        }
    }

    public function testAMemberRegistersFromTheEventPage(): void
    {
        $db = TestServer::db();
        $event = $db->query("SELECT post_id, slug FROM content_posts WHERE type = 'event' AND status = 'published' AND event_start > NOW() ORDER BY event_start LIMIT 1")->fetch();

        if ($event === false) {
            $this->markTestSkipped('No upcoming event is seeded.');
        }

        $db->prepare("DELETE r FROM event_registrations r JOIN members m ON m.member_id = r.member_id JOIN users u ON u.user_id = m.user_id WHERE r.post_id = ? AND u.email = 'member@agile.test'")
            ->execute([(int) $event['post_id']]);
        $guest = (new TestServer())->get("/posts/{$event['slug']}");
        $this->assertStringContainsString('Sign in to register', $guest['body']);

        $member = TestServer::loggedIn('member@agile.test');
        $this->assertStringContainsString('Register for this event', $member->get("/posts/{$event['slug']}")['body']);
        $page = $member->follow($member->post("/events/{$event['post_id']}/register", []))['body'];
        $this->assertStringContainsString('You are registered', $page);

        $this->assertStringContainsString('id="sub-events"', $member->get('/my-submissions')['body'], 'My Submissions lists the event.');
        $page = $member->follow($member->post("/events/{$event['post_id']}/cancel", []))['body'];
        $this->assertStringContainsString('Register for this event', $page, 'Cancelling gives the slot back.');
        $this->assertSame(403, $member->get('/events/attendance')['status'], 'Members do not take attendance.');

        $officer = TestServer::loggedIn('mswmember@agile.test');
        $this->assertStringContainsString('Event attendance', $officer->get('/events/attendance')['body']);
        $sheet = $officer->get("/events/attendance/{$event['post_id']}");
        $this->assertSame(200, $sheet['status']);
        $this->assertStringContainsString('Member number or ID QR', $sheet['body']);
        $this->assertDoesNotMatchRegularExpression('/<main[\s\S]*style="/', $sheet['body']);
    }

    public function testCommitteesShowTheStandingSevenAndTheSourceCodeApart(): void
    {
        $body = (new TestServer())->get('/committees')['body'];

        foreach (['Academics and Training', 'Membership and Student Welfare', 'Events and External Relations'] as $name) {
            $this->assertStringContainsString($name, $body);
        }

        $this->assertStringContainsString('>MSW<', $body, 'Committees are marked with their own initials.');
        $this->assertStringContainsString('px-band', $body, 'The Source Code sits apart.');
        $this->assertStringNotContainsString('Year-Level Council', $body);

        $id = (int) TestServer::db()->query("SELECT committee_id FROM committees WHERE name = 'Logistics'")->fetchColumn();
        $page = (new TestServer())->get("/committees/{$id}");
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Committee Head', $page['body']);
        $council = (int) TestServer::db()->query("SELECT committee_id FROM committees WHERE name = 'Year-Level Council'")->fetchColumn();
        $this->assertSame(404, (new TestServer())->get("/committees/{$council}")['status']);
    }

    public function testApplyFromAPositionArrivesWithItChosen(): void
    {
        // Whatever Open Positions offers right now: the Apply link on its first card.
        preg_match('/href="\/apply\?position=(\d+)"/', (new TestServer())->get('/positions')['body'], $match);

        if ($match === []) {
            $this->markTestSkipped('Nothing is open for application in this run.');
        }

        $id = (int) $match[1];
        $role = (string) TestServer::db()->query("SELECT role_type FROM positions WHERE position_id = {$id}")->fetchColumn();

        $body = (new TestServer())->get("/apply?position={$id}")['body'];
        $this->assertMatchesRegularExpression('/<option value="' . $role . '"[^>]*selected/', $body);
        $this->assertMatchesRegularExpression('/<option value="' . $id . '"[^>]*selected/', $body);
    }
}
