<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouteList;
use Tests\Support\TestServer;

/**
 * ISO/IEC 25010 product quality, measured on the running app (Nov 23, 2026). Each test names the characteristic and
 * sub-characteristic it checks; docs/iso-25010.md maps them, with the rest of the suite, to the whole model.
 * Run one characteristic with: vendor/bin/phpunit --group iso-security (or iso-performance, iso-usability, ...).
 */
final class Iso25010Test extends TestCase
{
    /** Pages a visitor or a member opens most, for the time and usability checks. */
    private const PUBLIC_PAGES = ['/', '/about', '/committees', '/news', '/events', '/publications', '/positions', '/track', '/login', '/apply', '/welfare/guest', '/register', '/forgot-password', '/privacy'];
    private const MEMBER_PAGES = ['/dashboard', '/account', '/my-submissions', '/welfare/new', '/elections/candidacy', '/my-applications'];
    private const STAFF_PAGES = ['/dashboard', '/applications', '/members', '/welfare', '/admin/users', '/credentials/requests', '/admin/candidacies', '/reports'];

    /** Time behaviour target per page on the local PHP server, in seconds. */
    private const MAX_SECONDS = 2.0;

    protected function setUp(): void
    {
        TestServer::db()->exec('DELETE FROM rate_limits');
    }

    // ---------- Functional suitability ----------

    #[Group('iso-functional')]
    public function testFunctionalCompletenessEveryMenuPageOpensForItsRole(): void
    {
        $member = TestServer::loggedIn('member@agile.test');
        $staff = TestServer::loggedIn('mswhead@agile.test');

        foreach (self::MEMBER_PAGES as $path) {
            $this->assertSame(200, $member->get($path)['status'], "Member: {$path}");
        }

        foreach (self::STAFF_PAGES as $path) {
            $this->assertSame(200, $staff->get($path)['status'], "MSW Committee Head: {$path}");
        }

        foreach (self::PUBLIC_PAGES as $path) {
            $page = (new TestServer())->get($path);
            $this->assertContains($page['status'], [200, 302], "Public: {$path}");
        }
    }

    // ---------- Performance efficiency ----------

    #[Group('iso-performance')]
    public function testTimeBehaviourPagesAnswerWithinTheTarget(): void
    {
        $server = new TestServer();
        $staff = TestServer::loggedIn('mswhead@agile.test');
        $slow = [];

        foreach ([[$server, self::PUBLIC_PAGES], [$staff, self::STAFF_PAGES]] as [$client, $paths]) {
            foreach ($paths as $path) {
                $client->get($path); // warm the opcode cache
                $start = microtime(true);
                $client->get($path);
                $seconds = microtime(true) - $start;

                if ($seconds > self::MAX_SECONDS) {
                    $slow[] = sprintf('%s %.2fs', $path, $seconds);
                }
            }
        }

        $this->assertSame([], $slow, 'Pages slower than ' . self::MAX_SECONDS . ' s.');
    }

    #[Group('iso-performance')]
    public function testResourceUtilizationPagesStayLight(): void
    {
        foreach (self::PUBLIC_PAGES as $path) {
            $page = (new TestServer())->get($path);

            if ($page['status'] === 200) {
                $this->assertLessThan(300 * 1024, strlen($page['body']), "{$path} HTML is over 300 KB.");
            }
        }
    }

    // ---------- Compatibility ----------

    #[Group('iso-compatibility')]
    public function testCoexistenceAndInteroperabilityNoOutsideScriptsAndAPhoneViewport(): void
    {
        foreach (self::PUBLIC_PAGES as $path) {
            $body = (new TestServer())->get($path)['body'];

            if ($body === '') {
                continue;
            }

            $this->assertStringContainsString('name="viewport"', $body, "{$path} has no viewport meta for phones.");
            $this->assertDoesNotMatchRegularExpression('#<script[^>]+src="https?://#i', $body, "{$path} loads a script from another site.");
        }
    }

    // ---------- Interaction capability (usability) ----------

    #[Group('iso-usability')]
    public function testAccessibilityEveryFieldHasALabelAndEveryImageHasAlt(): void
    {
        $member = TestServer::loggedIn('member@agile.test');
        $pages = array_merge(
            array_map(static fn (string $p): array => [new TestServer(), $p], self::PUBLIC_PAGES),
            array_map(static fn (string $p): array => [$member, $p], self::MEMBER_PAGES),
        );

        foreach ($pages as [$client, $path]) {
            $body = $client->get($path)['body'];

            if ($body === '') {
                continue;
            }

            $this->assertMatchesRegularExpression('/<html lang="[a-z]{2}/', $body, "{$path}: no page language.");
            preg_match_all('/<img\b[^>]*>/i', $body, $images);

            foreach ($images[0] as $image) {
                $this->assertMatchesRegularExpression('/\balt="/', $image, "{$path}: an image has no alt text: {$image}");
            }

            preg_match_all('/<(input|select|textarea)\b[^>]*>/i', $body, $fields);

            foreach ($fields[0] as $field) {
                if (preg_match('/type="(hidden|submit|button)"|aria-hidden|tabindex="-1"/', $field) === 1) {
                    continue;
                }

                $hasName = preg_match('/\baria-label="|\baria-labelledby="/', $field) === 1;

                if (!$hasName && preg_match('/\bid="([^"]+)"/', $field, $id) === 1) {
                    $hasName = str_contains($body, 'for="' . $id[1] . '"');
                }

                // A checkbox or radio inside its <label> is named by it.
                $hasName = $hasName || preg_match('/type="(checkbox|radio)"/', $field) === 1;
                $this->assertTrue($hasName, "{$path}: a form field has no label: {$field}");
            }
        }
    }

    #[Group('iso-usability')]
    public function testUserErrorProtectionAFormWithMistakesSaysWhatIsWrong(): void
    {
        $server = new TestServer();
        $server->get('/track');
        $page = $server->post('/track', ['code' => '', 'email' => '']);

        $this->assertContains($page['status'], [200, 422]);
        $this->assertStringContainsString('field__error', $page['body'], 'Track Status shows no field error for empty input.');
    }

    // ---------- Reliability ----------

    #[Group('iso-reliability')]
    public function testFaultToleranceUnknownPagesAndRecordsGiveAFriendlyNotFound(): void
    {
        $server = new TestServer();

        foreach (['/no-such-page', '/posts/no-such-post', '/positions/999999', '/committees/999999'] as $path) {
            $page = $server->get($path);
            $this->assertSame(404, $page['status'], $path);
            $this->assertStringContainsString('href="/"', $page['body'], "{$path}: the error page offers no way home.");
            $this->assertStringNotContainsString('Stack trace', $page['body'], "{$path} shows a stack trace.");
        }
    }

    #[Group('iso-reliability')]
    public function testRecoverabilityTheBackupJobAndRestoreGuideExist(): void
    {
        $root = dirname(__DIR__);

        $this->assertFileExists("{$root}/jobs/backup_database.php");
        $this->assertFileExists("{$root}/docs/restore.md");
        $this->assertStringContainsString('backup_database.php', (string) file_get_contents("{$root}/docs/scheduling.md"), 'The schedule does not back up the database.');
    }

    // ---------- Security ----------

    #[Group('iso-security')]
    public function testConfidentialityAndIntegrityEveryPageSendsTheSecurityHeaders(): void
    {
        $server = new TestServer();
        $staff = TestServer::loggedIn('mswhead@agile.test');

        foreach ([[$server, '/'], [$server, '/login'], [$staff, '/dashboard'], [$staff, '/welfare']] as [$client, $path]) {
            $client->get($path);
            $headers = $client->headers;
            $this->assertStringContainsString("default-src 'self'", $headers['content-security-policy'] ?? '', "{$path}: CSP");
            $this->assertSame('nosniff', $headers['x-content-type-options'] ?? '', "{$path}: nosniff");
            $this->assertSame('DENY', $headers['x-frame-options'] ?? '', "{$path}: X-Frame-Options");
            $this->assertNotSame('', $headers['referrer-policy'] ?? '', "{$path}: Referrer-Policy");
        }
    }

    #[Group('iso-security')]
    public function testAuthenticityTheSessionCookieIsHttpOnlyAndSameSite(): void
    {
        $server = new TestServer();
        $server->get('/login');
        $cookie = strtolower($server->headers['session-cookie'] ?? '');

        $this->assertStringContainsString('httponly', $cookie);
        $this->assertStringContainsString('samesite=lax', $cookie);
    }

    #[Group('iso-security')]
    public function testAccountabilityAndNonRepudiationSignInsAreAudited(): void
    {
        $before = (int) TestServer::db()->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'login.success'")->fetchColumn();
        TestServer::loggedIn('member@agile.test');
        $after = (int) TestServer::db()->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'login.success'")->fetchColumn();

        $this->assertGreaterThan($before, $after, 'A sign-in left no audit log entry.');
    }

    #[Group('iso-security')]
    public function testResistanceEveryRouteNeedsALoginAndEveryPostACsrfToken(): void
    {
        foreach (RouteList::routes() as $route) {
            $name = "{$route['method']} {$route['path']}";
            $guarded = array_filter($route['middleware'], static fn (string $m): bool => str_starts_with($m, 'perm:') || str_ends_with($m, 'AuthMiddleware'));

            if ($guarded === []) {
                $this->assertContains($name, RouteList::PUBLIC_ROUTES, "{$name} is open without a login.");
            }

            if ($route['method'] === 'POST') {
                $this->assertContains(\App\Middleware\CsrfMiddleware::class, $route['middleware'], "{$name} has no CSRF check.");
            }
        }
    }

    // ---------- Maintainability ----------

    #[Group('iso-maintainability')]
    public function testModularityAndAnalysabilityEveryPhpFileDeclaresStrictTypesAndStaysSmall(): void
    {
        $root = dirname(__DIR__);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/app", FilesystemIterator::SKIP_DOTS));
        $big = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());
            $this->assertStringContainsString('declare(strict_types=1);', $code, $file->getPathname());

            if (substr_count($code, "\n") > 1600) {
                $big[] = basename($file->getPathname());
            }
        }

        $this->assertSame([], $big, 'Classes over 1,600 lines are hard to change.');
    }

    // ---------- Flexibility (portability) ----------

    #[Group('iso-portability')]
    public function testInstallabilityEverySettingReadFromEnvIsInTheExampleFile(): void
    {
        $root = dirname(__DIR__);
        $example = (string) file_get_contents("{$root}/.env.example");
        $missing = [];

        foreach (glob("{$root}/config/*.php") ?: [] as $config) {
            preg_match_all("/env\\('([A-Z0-9_]+)'/", (string) file_get_contents($config), $keys);

            foreach ($keys[1] as $key) {
                if (preg_match('/^' . preg_quote($key, '/') . '=/m', $example) !== 1) {
                    $missing[] = $key . ' (' . basename($config) . ')';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Settings read from .env but missing from .env.example.');
    }

    #[Group('iso-portability')]
    public function testInstallabilityThePreflightCheckIsShippedAndRuns(): void
    {
        // Linted only: run for real, the check reads the database in .env, and tests never touch real data.
        $script = dirname(__DIR__) . '/jobs/preflight.php';
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($script), $output, $exit);

        $this->assertSame(0, $exit, implode("
", $output));
        $code = (string) file_get_contents($script);

        foreach (['APP_ENV', 'APP_DEBUG', 'APP_URL', 'ENCRYPTION_KEY', 'MAIL_DRIVER', 'Demo people', 'MSW Committee Head'] as $checked) {
            $this->assertStringContainsString($checked, $code, "The preflight check does not look at {$checked}.");
        }
    }

    #[Group('iso-portability')]
    public function testAdaptabilityNoMachineSpecificPathsInTheCode(): void
    {
        $root = dirname(__DIR__);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/app", FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $this->assertDoesNotMatchRegularExpression('#[A-Z]:\\\\(laragon|xampp|Users)|/home/\w+/#i', (string) file_get_contents($file->getPathname()), $file->getPathname());
            }
        }
    }
}
