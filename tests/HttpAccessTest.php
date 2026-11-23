<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouteList;
use Tests\Support\TestServer;

/**
 * Every protected route in config/routes.php, for every sample role, over real HTTP. The expected answer comes from
 * the section 6 table in PROJECT_CONTEXT.md, never from the code being tested. Record ids do not exist, so a request
 * that gets past the permission check finds nothing to change.
 */
final class HttpAccessTest extends TestCase
{
    private const ROLES = [
        'general_member' => 'member@agile.test',
        'committee_head' => 'head@agile.test',
        'execomm' => 'execomm@agile.test',
        'msw_member' => 'mswmember@agile.test',
        'msw_head' => 'mswhead@agile.test',
        'president' => 'president@agile.test',
    ];

    // Reached by a role allowed in section 6, these would change what the rest of this test relies on: an empty form
    // would clear every role's permissions, and the other logs the browser out. Every other role still gets a real 403.
    private const NOT_SENT_WHEN_ALLOWED = ['POST /admin/roles', 'POST /logout'];

    // Two permissions reach these, so EligibilityService makes the check instead of the route (BR-10).
    private const SERVICE_CHECKED = [
        'GET /eligibility/review' => ['msw_head', 'president'],
        'GET /eligibility/review/{id}' => ['msw_head', 'president'],
        'POST /eligibility/review/{id}/verify' => ['msw_head', 'president'],
        'POST /eligibility/review/{id}/decide' => ['msw_head', 'president'],
    ];

    // These check the form before EligibilityService checks the person, so a real form is what reaches the permission check.
    private const FORMS = [
        'POST /eligibility/review/{id}/verify' => ['has_failed_subject' => 'no', 'is_officially_enrolled' => 'yes'],
        'POST /eligibility/review/{id}/decide' => ['decision' => 'clear', 'remarks' => 'Test'],
    ];

    /** @return array<string, array{string, string}> */
    public static function roles(): array
    {
        $cases = [];

        foreach (self::ROLES as $role => $email) {
            $cases[$role] = [$role, $email];
        }

        return $cases;
    }

    #[DataProvider('roles')]
    public function testEveryProtectedRouteFollowsSectionSix(string $role, string $email): void
    {
        $browser = TestServer::loggedIn($email);
        $table = RouteList::sectionSix();
        $wrong = [];
        $checked = 0;

        foreach ($this->protectedRoutes() as $route) {
            $name = RouteList::name($route);
            $permission = RouteList::permission($route);
            $allowed = match (true) {
                isset(self::SERVICE_CHECKED[$name]) => in_array($role, self::SERVICE_CHECKED[$name], true),
                $permission !== null => in_array($role, $table[$permission] ?? [], true),
                default => null,
            };

            if ($allowed !== false && in_array($name, self::NOT_SENT_WHEN_ALLOWED, true)) {
                continue;
            }

            $response = $this->request($browser, $route);
            $checked++;
            $toLogin = str_contains($response['location'], '/login');

            // A login route with no permission: any logged-in role reaches it. A record of someone else's may still refuse.
            $problem = match (true) {
                $response['status'] >= 500 => "server error {$response['status']}",
                $response['status'] === 419 => 'CSRF token was refused',
                $toLogin => 'sent to log in',
                $allowed === true && $response['status'] === 403 => 'expected allowed, got 403',
                $allowed === false && $response['status'] !== 403 => "expected 403, got {$response['status']}",
                default => null,
            };

            if ($problem !== null) {
                $wrong[] = "{$name}: {$problem}";
            }
        }

        $this->assertGreaterThan(100, $checked);
        $this->assertSame([], $wrong, "{$role} ({$email})");
    }

    public function testEveryProtectedRouteSendsSomeoneNotLoggedInToLogin(): void
    {
        $browser = new TestServer();
        $browser->get('/login');
        $wrong = [];

        foreach ($this->protectedRoutes() as $route) {
            $response = $this->request($browser, $route);

            if ($response['status'] !== 302 || !str_contains($response['location'], '/login')) {
                $wrong[] = RouteList::name($route) . ": {$response['status']} {$response['location']}";
            }
        }

        $this->assertSame([], $wrong);
    }

    public function testEveryPostRouteAnswers419WithoutAValidToken(): void
    {
        $browser = TestServer::loggedIn('mswhead@agile.test');
        $wrong = [];
        $posts = array_filter(RouteList::routes(), static fn (array $route): bool => $route['method'] === 'POST');

        foreach ($posts as $route) {
            $path = $this->fill($route['path']);

            foreach ([$browser->post($path, [], false), $browser->post($path, ['_token' => 'wrong'])] as $response) {
                if ($response['status'] !== 419) {
                    $wrong[] = RouteList::name($route) . ": {$response['status']}";
                }
            }
        }

        $this->assertGreaterThan(50, count($posts));
        $this->assertSame([], $wrong);
    }

    /** @return list<array{method: string, path: string, middleware: list<string>}> */
    private function protectedRoutes(): array
    {
        return array_values(array_filter(
            RouteList::routes(),
            static fn (array $route): bool => !in_array(RouteList::name($route), RouteList::PUBLIC_ROUTES, true),
        ));
    }

    /** @param array{method: string, path: string} $route */
    private function request(TestServer $browser, array $route): array
    {
        $path = $this->fill($route['path']);

        return $route['method'] === 'GET' ? $browser->get($path) : $browser->post($path, self::FORMS[RouteList::name($route)] ?? []);
    }

    private function fill(string $path): string
    {
        return (string) preg_replace(['/\{type\}/', '/\{step\}/', '/\{slug\}/', '/\{token\}/', '/\{\w+\}/'], ['avatar', '1', 'none', 'none', '999999'], $path);
    }
}
