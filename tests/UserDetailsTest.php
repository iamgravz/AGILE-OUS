<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\UserRepository;
use App\Services\RbacService;
use App\Services\UserAdminService;
use PHPUnit\Framework\TestCase;

/** Administration, Users, Details: the MSW Committee Head corrects a person's name, email and student details. */
final class UserDetailsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testTheMswHeadCorrectsDetailsAndEmailsStayUnique(): void
    {
        $sentinel = new RuntimeException('Roll back the test.');

        try {
            Database::transaction(function () use ($sentinel): void {
                $users = new UserRepository();
                $_SESSION['user_id'] = (int) $users->findByEmail('mswhead@agile.test')['user_id'];
                RbacService::clearCache();
                $target = $users->findByEmail('member@agile.test');
                $id = (int) $target['user_id'];
                $details = ['first_name' => 'Corrected', 'last_name' => 'Name', 'email' => 'Member@Agile.test', 'student_no' => (string) ($target['student_no'] ?? ''),
                    'year_level' => '3rd Year', 'section' => 'BSITOUMN 3-2', 'contact_no' => '09171112222'];
                $service = new UserAdminService();

                $this->assertRefused('why', fn () => $service->updateDetails($id, $details, ''));
                $service->updateDetails($id, $details, 'Typo in the name');
                $after = $users->find($id);
                $this->assertSame('Corrected', $after['first_name']);
                $this->assertSame('member@agile.test', $after['email'], 'Emails are kept in lower case.');
                $this->assertSame('3rd Year', $after['year_level']);
                $this->assertSame('BSITOUMN 3-2', $after['section']);
                $this->assertRefused('Nothing changed', fn () => $service->updateDetails($id, $details, 'Again'));
                $this->assertRefused('already uses this email', fn () => $service->updateDetails($id, ['email' => 'head@agile.test'] + $details, 'Wrong'));

                throw $sentinel;
            });
        } catch (RuntimeException $exception) {
            if ($exception !== $sentinel) {
                throw $exception;
            }
        }
    }

    public function testOnlyTheMswHeadEditsDetails(): void
    {
        $users = new UserRepository();
        $_SESSION['user_id'] = (int) $users->findByEmail('mswmember@agile.test')['user_id'];
        RbacService::clearCache();
        $this->expectException(ForbiddenException::class);
        (new UserAdminService())->updateDetails((int) $users->findByEmail('member@agile.test')['user_id'], ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'member@agile.test', 'student_no' => '', 'year_level' => '1st Year', 'section' => 'A', 'contact_no' => '09170000000'], 'No');
    }

    private function assertRefused(string $message, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a refusal: {$message}");
        } catch (DomainException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }
}
