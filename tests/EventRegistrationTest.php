<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\UserRepository;
use App\Services\EventRegistrationService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** Event registration (Nov 23, 2026). Database tests roll back. */
final class EventRegistrationTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testWhyRegistrationIsClosed(): void
    {
        $event = ['type' => 'event', 'status' => 'published', 'registration_open' => 1, 'event_start' => '2026-12-01 09:00:00', 'capacity' => 2];
        $now = '2026-11-23 10:00:00';

        $this->assertSame('', EventRegistrationService::closedReason($event, 1, $now));
        $this->assertStringContainsString('full', EventRegistrationService::closedReason($event, 2, $now));
        $this->assertStringContainsString('closed', EventRegistrationService::closedReason(['registration_open' => 0] + $event, 0, $now));
        $this->assertStringContainsString('started', EventRegistrationService::closedReason($event, 0, '2026-12-01 09:00:00'));
        $this->assertStringContainsString('not an open event', EventRegistrationService::closedReason(['status' => 'draft'] + $event, 0, $now));
        $this->assertSame('', EventRegistrationService::closedReason(['capacity' => null] + $event, 500, $now), 'No cap, no limit.');
    }

    public function testAMemberRegistersCancelsAndRegistersAgain(): void
    {
        $this->inRollback(function (): void {
            $postId = $this->event(null);
            $this->signIn('member@agile.test');
            $service = new EventRegistrationService();

            $service->register($postId);
            $this->assertSame(1, $service->registeredCount($postId));

            try {
                $service->register($postId);
                $this->fail('Registering twice is refused.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('already registered', $exception->getMessage());
            }

            $service->cancel($postId);
            $this->assertSame(0, $service->registeredCount($postId));
            $service->register($postId);
            $this->assertSame(1, $service->registeredCount($postId));
            $this->assertSame(1, (int) Database::connection()->query("SELECT COUNT(*) FROM event_registrations WHERE post_id = {$postId}")->fetchColumn(), 'One row per member.');
        });
    }

    public function testAFullEventTakesNoMore(): void
    {
        $this->inRollback(function (): void {
            $postId = $this->event(1);
            $this->signIn('member@agile.test');
            (new EventRegistrationService())->register($postId);

            $this->signIn('mswmember@agile.test');
            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('full');
            (new EventRegistrationService())->register($postId);
        });
    }

    public function testSomeoneWithoutAMembershipCannotRegister(): void
    {
        $this->inRollback(function (): void {
            $postId = $this->event(null);
            $this->signIn('nomember@agile.test');
            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('Only AGILE members');
            (new EventRegistrationService())->register($postId);
        });
    }

    private function event(?int $capacity): int
    {
        $db = Database::connection();
        $db->prepare("INSERT INTO content_posts (type, title, slug, excerpt, body, event_start, event_end, venue_or_link, capacity, status, author_id, published_at)
                      VALUES ('event', 'Test event', ?, 'x', '<p>x</p>', ?, ?, 'Room 1', ?, 'published', ?, NOW())")
            ->execute(['test-event-' . bin2hex(random_bytes(4)), date('Y-m-d H:i:s', time() + 86400), date('Y-m-d H:i:s', time() + 90000), $capacity, $this->userId('mswhead@agile.test')]);

        return (int) $db->lastInsertId();
    }

    private function signIn(string $email): void
    {
        $_SESSION['user_id'] = $this->userId($email);
        RbacService::clearCache();
    }

    private function userId(string $email): int
    {
        return (int) (new UserRepository())->findByEmail($email)['user_id'];
    }

    private function inRollback(callable $test): void
    {
        $sentinel = new RuntimeException('Roll back the test.');

        try {
            Database::transaction(static function () use ($test, $sentinel): void {
                $test();

                throw $sentinel;
            });
        } catch (RuntimeException $exception) {
            if ($exception !== $sentinel) {
                throw $exception;
            }
        }
    }
}
