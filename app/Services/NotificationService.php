<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\NotificationRepository;
use App\Models\UserRepository;
use InvalidArgumentException;

/**
 * The one entry point for email and in-app notifications. Emails are queued here and sent by
 * jobs/send_email_queue.php. A template value that is missing throws, so the caller finds out at once.
 */
final class NotificationService
{
    private const CHANNELS = ['email', 'in_app'];

    public function __construct(
        private readonly NotificationRepository $notifications = new NotificationRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly TemplateRenderer $renderer = new TemplateRenderer(),
    ) {
    }

    /**
     * {name} defaults to the user's first name and {link} to $link. Do not pass a link holding a secret
     * token as $link: put it in $payload, and send those emails on the email channel only.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $channels
     * @return list<int> The new notification ids.
     */
    public function notify(int $userId, string $templateCode, array $payload, ?string $link = null, array $channels = self::CHANNELS): array
    {
        if ($channels === [] || array_diff($channels, self::CHANNELS) !== []) {
            throw new InvalidArgumentException('Channels must be email, in_app or both.');
        }

        $user = $this->users->find($userId) ?? throw new InvalidArgumentException("Unknown user: {$userId}");
        $payload += ['name' => $user['first_name']];

        if ($link !== null) {
            $payload += ['link' => $link];
        }

        $rendered = $this->renderer->render($templateCode, $payload);

        return Database::transaction(function () use ($userId, $payload, $link, $channels, $rendered): array {
            $ids = [];

            foreach ($channels as $channel) {
                $ids[] = $this->notifications->insert($this->row($rendered, $payload, $link, $channel) + ['user_id' => $userId]);
            }

            return $ids;
        });
    }

    /**
     * @param list<int> $userIds
     * @param array<string, mixed> $payload
     * @param list<string> $channels
     * @return list<int>
     */
    public function notifyMany(array $userIds, string $templateCode, array $payload, ?string $link = null, array $channels = self::CHANNELS): array
    {
        return Database::transaction(function () use ($userIds, $templateCode, $payload, $link, $channels): array {
            $ids = [];

            foreach (array_unique($userIds) as $userId) {
                array_push($ids, ...$this->notify($userId, $templateCode, $payload, $link, $channels));
            }

            return $ids;
        });
    }

    /**
     * Sends to the role's active users only.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $channels
     * @return list<int>
     */
    public function notifyRole(string $roleSlug, string $templateCode, array $payload, ?string $link = null, array $channels = self::CHANNELS): array
    {
        return $this->notifyMany($this->users->activeIdsByRole($roleSlug), $templateCode, $payload, $link, $channels);
    }

    /**
     * Email only, for a guest with no account.
     *
     * @param array<string, mixed> $payload
     */
    public function notifyEmail(string $email, string $templateCode, array $payload): int
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        $rendered = $this->renderer->render($templateCode, $payload);

        return $this->notifications->insert($this->row($rendered, $payload, null, 'email') + ['recipient_email' => $email]);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $userId, int $limit = 10): array
    {
        return $this->notifications->latestInApp($userId, $limit);
    }

    /** @return array{data: list<array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int} */
    public function page(int $userId, int $page): array
    {
        return $this->notifications->paginate($page, 20, "user_id = ? AND channel = 'in_app'", [$userId]);
    }

    public function markRead(int $id, int $userId): bool
    {
        return $this->notifications->markRead($id, $userId);
    }

    public function markAllRead(int $userId): int
    {
        return $this->notifications->markAllRead($userId);
    }

    /**
     * @param array{template_id: int, subject: string, html: string} $rendered
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function row(array $rendered, array $payload, ?string $link, string $channel): array
    {
        $isInApp = $channel === 'in_app';

        return [
            'template_id' => $rendered['template_id'],
            'channel' => $channel,
            'title' => mb_substr($rendered['subject'], 0, 200),
            'link' => $link,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'status' => $isInApp ? NotificationStatus::SENT : NotificationStatus::QUEUED,
            'sent_at' => $isInApp ? date('Y-m-d H:i:s') : null,
        ];
    }
}
