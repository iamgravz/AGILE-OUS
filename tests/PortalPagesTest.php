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
            $this->assertStringContainsString('// ' . $eyebrow, $page['body'], $path);
            $this->assertDoesNotMatchRegularExpression('/<main[\s\S]*style="/', $page['body'], "{$path} has an inline style, which the CSP blocks.");
        }
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
        $id = (int) TestServer::db()->query("SELECT p.position_id FROM positions p WHERE p.is_open = 1 AND p.role_type = 'committee_member' LIMIT 1")->fetchColumn();
        $this->assertGreaterThan(0, $id);

        $body = (new TestServer())->get("/apply?position={$id}")['body'];
        $this->assertMatchesRegularExpression('/<option value="committee_member"[^>]*selected/', $body);
        $this->assertMatchesRegularExpression('/<option value="' . $id . '"[^>]*selected/', $body);
    }
}
