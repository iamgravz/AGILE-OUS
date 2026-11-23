<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Middleware\CsrfMiddleware;
use App\Models\ReferralRepository;
use App\Models\UserRepository;
use App\Services\AuthService;
use App\Services\EncryptionService;
use App\Services\RateLimiter;
use App\Services\RbacService;
use App\Services\SessionGuard;
use App\Services\WelfareService;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouteList;

/** Step 22. These keep the review's findings closed, including for routes added later. */
final class SecurityReviewTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id'], $_SERVER['REMOTE_ADDR']);
        RbacService::clearCache();
    }

    public function testEveryPostRouteChecksCsrf(): void
    {
        foreach (RouteList::routes() as $route) {
            if ($route['method'] === 'POST') {
                $this->assertContains(CsrfMiddleware::class, $route['middleware'], $route['path']);
            }
        }
    }

    public function testEveryRouteNeedsALoginUnlessListedAsPublic(): void
    {
        foreach (RouteList::routes() as $route) {
            $name = "{$route['method']} {$route['path']}";
            $checked = array_filter($route['middleware'], static fn (string $m): bool => str_starts_with($m, 'perm:') || str_ends_with($m, 'AuthMiddleware'));

            if ($checked === []) {
                $this->assertContains($name, RouteList::PUBLIC_ROUTES, "{$name} has no login check and is not listed as public.");
            }
        }
    }

    public function testASessionEndsWhenIdleInactiveOrThePasswordChanged(): void
    {
        $user = ['status' => 'active', 'password_hash' => 'hash-one'];
        $_SESSION = [];
        SessionGuard::remember('hash-one');
        $print = $_SESSION['password_fingerprint'];
        $now = 1_000_000;

        $this->assertNull(SessionGuard::reasonToEnd($user, $now - 1800, 1800, $print, $now));
        $this->assertSame(SessionGuard::REASON_IDLE, SessionGuard::reasonToEnd($user, $now - 1801, 1800, $print, $now));
        $this->assertSame(SessionGuard::REASON_NOT_ACTIVE, SessionGuard::reasonToEnd(['status' => 'disabled'] + $user, $now, 1800, $print, $now));
        $this->assertSame(SessionGuard::REASON_NOT_ACTIVE, SessionGuard::reasonToEnd(null, $now, 1800, $print, $now));
        $this->assertSame(SessionGuard::REASON_PASSWORD_CHANGED, SessionGuard::reasonToEnd(['password_hash' => 'hash-two'] + $user, $now, 1800, $print, $now));
        // A forced reset empties the hash.
        $this->assertSame(SessionGuard::REASON_PASSWORD_CHANGED, SessionGuard::reasonToEnd(['password_hash' => ''] + $user, $now, 1800, $print, $now));
        $this->assertSame(SessionGuard::REASON_PASSWORD_CHANGED, SessionGuard::reasonToEnd($user, $now, 1800, null, $now));
    }

    public function testOneAddressCannotTryAPasswordOnManyAccounts(): void
    {
        $this->inRollback(function (): void {
            $ip = '203.0.113.9';
            $limiter = new RateLimiter();

            for ($i = 0; $i < 50; $i++) {
                $limiter->hit("login_ip:{$ip}", 50, 15);
            }

            // The right password, on an account this address has not tried.
            $this->assertSame(AuthService::LOGIN_FAILED, (new AuthService())->attempt('member@agile.test', 'Password123!', $ip));
            $this->assertNull(current_user_id());
        });
    }

    public function testThereIsNoPasswordlessSampleLogin(): void
    {
        // Removed for go-live (Nov 23, 2026): every account signs in with its password, even on this computer.
        $this->assertFalse(method_exists(AuthService::class, 'loginAsSampleUser'));

        foreach (\Tests\Support\RouteList::routes() as $route) {
            $this->assertStringNotContainsString('/dev/', $route['path']);
        }

        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $this->assertFalse(dev_tools_allowed());
    }

    public function testReferralNamesAreStoredEncryptedAndReadBack(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('member@agile.test');
            $case = (new WelfareService())->submit(['category_id' => '1', 'subject' => 'S', 'description' => 'D', 'urgent' => false], null, []);
            $this->signIn('mswhead@agile.test');
            $service = new WelfareService();
            $service->addReferral((int) $case['case_id'], 'Dr. Secret Name', 'Remarks');

            $stored = (new ReferralRepository())->forCase((int) $case['case_id'])[0]['referred_to'];
            $this->assertStringNotContainsString('Secret', $stored);
            $this->assertSame('Dr. Secret Name', (new EncryptionService())->decrypt($stored));
            $this->assertSame('Dr. Secret Name', $service->openCase((int) $case['case_id'])['referrals'][0]['referred_to']);
        });
    }

    public function testTheMigrationEncryptsOldNamesOnceOnly(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('member@agile.test');
            $db = Database::connection();
            $case = (new WelfareService())->submit(['category_id' => '1', 'subject' => 'S', 'description' => 'D', 'urgent' => false], null, []);
            $user = (int) current_user_id();
            $db->prepare("INSERT INTO referrals (case_id, referred_to, remarks, status, referred_by, referred_at) VALUES (?, 'Guidance Office', 'x', 'pending', ?, NOW())")->execute([$case['case_id'], $user]);
            $id = (int) $db->lastInsertId();
            $read = static fn (): string => (string) $db->query("SELECT referred_to FROM referrals WHERE referral_id = {$id}")->fetchColumn();
            $migration = require Config::basePath('database/migrations/2026_11_01_encrypt_welfare_names.php');

            ob_start();
            $migration($db);
            $first = $read();
            $migration($db);
            ob_end_clean();

            $this->assertSame('Guidance Office', (new EncryptionService())->decrypt($first));
            $this->assertSame($first, $read(), 'A second run must not encrypt it again.');
        });
    }

    private function inRollback(callable $test): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $test();
        } finally {
            $db->rollBack();
        }
    }

    private function signIn(string $email): void
    {
        $_SESSION['user_id'] = (int) (new UserRepository())->findBy(['email' => $email])[0]['user_id'];
        RbacService::clearCache();
    }
}
