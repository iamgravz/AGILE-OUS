<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Notification preferences (Nov 23, 2026). A person can turn off some kinds of notice, by channel. Everything not
 * listed here always goes out: sign-in codes, passwords, decisions on their application, their welfare concern, their
 * ID or certificate, eligibility, and urgent welfare alerts. With no saved choice, every channel is on.
 */
final class NotificationPreferenceService
{
    /** Category => [label, help, template codes, channels the person may turn off]. */
    public const CATEGORIES = [
        'announcements' => [
            'Announcements',
            'News and notices sent to all members.',
            ['ANNOUNCEMENT'],
            ['email', 'in_app'],
        ],
        'reminders' => [
            'Reminders',
            'Interview, registration, pending application, follow-up and term-ending reminders.',
            ['INTERVIEW_REMINDER', 'MEMBER_REGISTRATION_REMINDER', 'APP_PENDING_REMINDER', 'WELFARE_FOLLOWUP_REMINDER', 'ROLE_EXPIRATION_REMINDER'],
            ['email', 'in_app'],
        ],
        'tasks' => [
            'Officer tasks',
            'Something waiting for you to review. It always shows in the bell; you can turn off the email.',
            ['ACCOUNTS_FOR_APPROVAL', 'CREDENTIALS_FOR_APPROVAL', 'CREDENTIAL_REQUESTED', 'APP_READY_FOR_DECISION', 'CONTENT_FOR_APPROVAL', 'HR_REQUEST_SUBMITTED', 'VACANCY_CLOSED', 'SERVICE_REQUEST_NEW'],
            ['email'],
        ],
    ];

    public const CHANNEL_LABELS = ['email' => 'Email', 'in_app' => 'In the system (bell)'];

    /** The category a template belongs to, or null when it always goes out. */
    public static function categoryOf(string $templateCode): ?string
    {
        foreach (self::CATEGORIES as $key => [, , $codes]) {
            if (in_array($templateCode, $codes, true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The channels of $channels this person still wants for this template.
     *
     * @param list<string> $channels
     * @return list<string>
     */
    public function allowed(int $userId, string $templateCode, array $channels): array
    {
        $category = self::categoryOf($templateCode);

        if ($category === null) {
            return $channels;
        }

        $saved = $this->forUser($userId)[$category];

        return array_values(array_filter($channels, static fn (string $channel): bool => $saved[$channel] ?? true));
    }

    /** @return array<string, array<string, bool>> category => channel => on, with defaults for what was never saved. */
    public function forUser(int $userId): array
    {
        $choices = [];

        foreach (self::CATEGORIES as $key => $category) {
            $choices[$key] = ['email' => true, 'in_app' => true];
        }

        $statement = Database::connection()->prepare('SELECT category, email, in_app FROM notification_preferences WHERE user_id = ?');
        $statement->execute([$userId]);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($choices[$row['category']])) {
                $choices[$row['category']] = ['email' => (int) $row['email'] === 1, 'in_app' => (int) $row['in_app'] === 1];
            }
        }

        return $choices;
    }

    /**
     * Saves the signed-in person's choices. A channel that cannot be turned off stays on whatever was sent.
     *
     * @param array<string, mixed> $submitted category => channel => '1' for the boxes left ticked
     */
    public function save(int $userId, array $submitted): void
    {
        $upsert = Database::connection()->prepare(
            'INSERT INTO notification_preferences (user_id, category, email, in_app) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE email = VALUES(email), in_app = VALUES(in_app)'
        );

        foreach (self::CATEGORIES as $key => [, , , $optional]) {
            $values = [];

            foreach (['email', 'in_app'] as $channel) {
                $ticked = is_array($submitted[$key] ?? null) && ($submitted[$key][$channel] ?? '') === '1';
                $values[$channel] = !in_array($channel, $optional, true) || $ticked ? 1 : 0;
            }

            $upsert->execute([$userId, $key, $values['email'], $values['in_app']]);
        }

        AuditService::log('notification_preferences.updated', 'user', $userId);
    }
}
