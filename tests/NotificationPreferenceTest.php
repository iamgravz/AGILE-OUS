<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\UserRepository;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationService;
use PHPUnit\Framework\TestCase;

/** Notification preferences (Nov 23, 2026). Database tests roll back. */
final class NotificationPreferenceTest extends TestCase
{
    public function testWhatCanAndCannotBeTurnedOff(): void
    {
        $this->assertSame('announcements', NotificationPreferenceService::categoryOf('ANNOUNCEMENT'));
        $this->assertSame('tasks', NotificationPreferenceService::categoryOf('APP_READY_FOR_DECISION'));

        foreach (['PASSWORD_RESET', 'GUEST_EMAIL_CODE', 'APP_APPROVED', 'APP_REJECTED', 'WELFARE_UPDATE', 'WELFARE_URGENT_ALERT', 'CREDENTIAL_UPLOADED', 'ELIGIBILITY_RESULT'] as $code) {
            $this->assertNull(NotificationPreferenceService::categoryOf($code), "{$code} always goes out.");
        }
    }

    public function testChoicesFilterTheChannelsAndTasksKeepTheBell(): void
    {
        $this->inRollback(function (): void {
            $userId = (int) (new UserRepository())->findByEmail('member@agile.test')['user_id'];
            $service = new NotificationPreferenceService();
            $this->assertSame(['email', 'in_app'], $service->allowed($userId, 'ANNOUNCEMENT', ['email', 'in_app']), 'All on by default.');

            // Announcements: bell only. Reminders: nothing. Tasks: email off, and a forged in_app = off is ignored.
            $service->save($userId, ['announcements' => ['in_app' => '1'], 'tasks' => []]);
            $this->assertSame(['in_app'], $service->allowed($userId, 'ANNOUNCEMENT', ['email', 'in_app']));
            $this->assertSame([], $service->allowed($userId, 'INTERVIEW_REMINDER', ['email', 'in_app']));
            $this->assertSame(['in_app'], $service->allowed($userId, 'CONTENT_FOR_APPROVAL', ['email', 'in_app']));
            $this->assertSame(['email', 'in_app'], $service->allowed($userId, 'PASSWORD_RESET', ['email', 'in_app']));

            $notices = new NotificationService();
            $this->assertSame([], $notices->notify($userId, 'INTERVIEW_REMINDER', ['date' => 'x', 'time' => 'y', 'mode' => 'z', 'location' => 'w']), 'Nothing is queued.');
            $this->assertCount(2, $notices->notify($userId, 'PASSWORD_RESET', ['link' => 'https://example.com', 'minutes' => 60]));
        });
    }

    public function testTheAccountPageSavesTheChoices(): void
    {
        $db = Tests\Support\TestServer::db();
        $db->exec('DELETE FROM rate_limits');
        $browser = Tests\Support\TestServer::loggedIn('member@agile.test');
        $this->assertStringContainsString('Save notification preferences', $browser->get('/account')['body']);

        $page = $browser->follow($browser->post('/account/notifications', ['notify' => ['announcements' => ['email' => '1'], 'reminders' => ['email' => '1', 'in_app' => '1']]]))['body'];
        $this->assertStringContainsString('notification preferences were saved', $page);
        $row = $db->query("SELECT p.email, p.in_app FROM notification_preferences p JOIN users u ON u.user_id = p.user_id WHERE u.email = 'member@agile.test' AND p.category = 'announcements'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['email' => 1, 'in_app' => 0], array_map('intval', $row));

        $db->exec("DELETE p FROM notification_preferences p JOIN users u ON u.user_id = p.user_id WHERE u.email = 'member@agile.test'");

        // Unread notifications roll across the top of the dashboard, with the settings link.
        $db->exec("INSERT INTO notifications (user_id, channel, title, payload_json, status) SELECT user_id, 'in_app', 'Rolling test notice', '{}', 'sent' FROM users WHERE email = 'member@agile.test'");
        $dashboard = $browser->get('/dashboard')['body'];
        $db->exec("DELETE FROM notifications WHERE title = 'Rolling test notice'");
        $this->assertMatchesRegularExpression('/rolling-notice[\s\S]*Rolling test notice[\s\S]*Notification settings/', $dashboard);
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
