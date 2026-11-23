<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\UserRepository;
use App\Services\RbacService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouteList;

/**
 * Compares can() with the section 6 table, read directly from PROJECT_CONTEXT.md. Using the document instead
 * of config/permissions.php means a wrong seed or a wrong query fails here. Needs the seeded database.
 * Nothing is left behind: the one test that writes rolls its row back.
 */
final class AccessMatrixTest extends TestCase
{
    private const SAMPLE_USERS = [
        'nomember@agile.test' => 'general_member',
        'member@agile.test' => 'general_member',
        'head@agile.test' => 'committee_head',
        'execomm@agile.test' => 'execomm',
        'mswmember@agile.test' => 'msw_member',
        'mswdeputy@agile.test' => 'msw_member',
        'mswhead@agile.test' => 'msw_head',
        'president@agile.test' => 'president',
    ];

    protected function setUp(): void
    {
        RbacService::clearCache();
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    /** @return array<string, array{string, string}> */
    public static function sampleUsers(): array
    {
        $cases = [];

        foreach (self::SAMPLE_USERS as $email => $role) {
            $cases[$email] = [$email, $role];
        }

        return $cases;
    }

    #[DataProvider('sampleUsers')]
    public function testCanMatchesSectionSixForEveryCode(string $email, string $role): void
    {
        $this->logInAs($email);

        foreach (self::sectionSixTable() as $code => $roles) {
            $this->assertSame(
                in_array($role, $roles, true),
                can($code),
                "{$email} ({$role}) has the wrong result for {$code}"
            );
        }
    }

    public function testTheTableCoversEveryPermissionCode(): void
    {
        $table = array_keys(self::sectionSixTable());
        $stored = Database::connection()->query('SELECT code FROM permissions')->fetchAll(PDO::FETCH_COLUMN);

        sort($table);
        sort($stored);

        $this->assertCount(35, $table);
        $this->assertSame($table, $stored);
    }

    public function testGuestRoleHasNoPermissions(): void
    {
        $this->logInAs('guest@agile.test');

        $this->assertSame([], (new RbacService())->permissionsFor((int) current_user_id()));
    }

    public function testNobodyLoggedInCanDoNothing(): void
    {
        foreach (array_keys(self::sectionSixTable()) as $code) {
            $this->assertFalse(can($code), $code);
        }
    }

    public function testUnknownCodeIsDenied(): void
    {
        $this->logInAs('mswhead@agile.test');

        $this->assertFalse(can('no.such.permission'));
    }

    public function testAuthorizeThrowsWithTheCode(): void
    {
        $this->logInAs('nomember@agile.test');

        try {
            authorize('admin.users');
            $this->fail('authorize() should have thrown.');
        } catch (ForbiddenException $exception) {
            $this->assertSame('admin.users', $exception->permission);
        }

        authorize('application.submit');
        $this->addToAssertionCount(1);
    }

    public function testUserPermissionsAddToTheRole(): void
    {
        $this->logInAs('nomember@agile.test');
        $this->assertFalse(can('member.view'));

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $db->prepare('INSERT INTO user_permissions (user_id, permission_id) SELECT ?, permission_id FROM permissions WHERE code = ?')
                ->execute([current_user_id(), 'member.view']);
            RbacService::clearCache();

            $this->assertTrue(can('member.view'));
            $this->assertTrue(can('application.submit'));
        } finally {
            $db->rollBack();
        }
    }

    public function testPermissionsAreCachedPerRequest(): void
    {
        $this->logInAs('nomember@agile.test');
        $this->assertTrue(can('application.submit'));

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $db->prepare('DELETE FROM role_permissions WHERE role_id = (SELECT role_id FROM users WHERE email = ?)')
                ->execute(['nomember@agile.test']);

            $this->assertTrue(can('application.submit'), 'The cached value should still be used.');
            RbacService::clearCache();
            $this->assertFalse(can('application.submit'));
        } finally {
            $db->rollBack();
        }
    }

    private function logInAs(string $email): void
    {
        $user = (new UserRepository())->findByEmail($email);
        $this->assertNotNull($user, "{$email} is missing. Run: php database/setup.php");
        $_SESSION['user_id'] = (int) $user['user_id'];
    }

    /** @return array<string, list<string>> code => roles that have it */
    private static function sectionSixTable(): array
    {
        return RouteList::sectionSix();
    }
}
