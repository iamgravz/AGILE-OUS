<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\RouteList;

/** Every sidebar link opens a real page: a GET route of its own, not a stand-in. */
final class MenuLinksTest extends TestCase
{
    public function testEverySidebarLinkHasItsOwnPage(): void
    {
        $actions = [];

        foreach (RouteList::routes() as $route) {
            if ($route['method'] === 'GET') {
                $actions[$route['path']] = $route['action'];
            }
        }

        foreach (config('menu.dashboard', []) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertArrayHasKey($item['url'], $actions, "{$item['label']} ({$item['url']}) has no page.");
                $this->assertStringNotContainsString('Placeholder', $actions[$item['url']], "{$item['label']} is a stand-in page.");
            }
        }
    }
}
