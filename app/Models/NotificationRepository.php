<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\NotificationStatus;

final class NotificationRepository extends BaseRepository
{
    protected string $table = 'notifications';
    protected string $primaryKey = 'notification_id';

    /**
     * Queued emails that are due, with the address and template code each one needs.
     *
     * @return list<array<string, mixed>>
     */
    public function dueEmails(int $limit): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT n.*, u.email AS user_email, t.code AS template_code
             FROM notifications n
             LEFT JOIN users u ON u.user_id = n.user_id
             LEFT JOIN email_templates t ON t.template_id = n.template_id
             WHERE n.channel = 'email'
               AND n.status = 'queued'
               AND (n.scheduled_at IS NULL OR n.scheduled_at <= ?)
             ORDER BY n.notification_id
             LIMIT {$limit}"
        );
        $statement->execute([date('Y-m-d H:i:s')]);

        return $statement->fetchAll();
    }

    /** Lets a daily job run twice in one day without sending twice. */
    public function sentToday(int $userId, string $templateCode): bool
    {
        return $this->sentSince($userId, $templateCode, date('Y-m-d 00:00:00'));
    }

    public function sentSince(int $userId, string $templateCode, string $since): bool
    {
        $statement = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM notifications n
             JOIN email_templates t ON t.template_id = n.template_id
             WHERE n.user_id = ? AND t.code = ? AND n.channel = 'email' AND n.created_at >= ?"
        );
        $statement->execute([$userId, $templateCode, $since]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function countEmails(string $status): int
    {
        $statement = $this->pdo()->prepare("SELECT COUNT(*) FROM notifications WHERE channel = 'email' AND status = ?");
        $statement->execute([$status]);

        return (int) $statement->fetchColumn();
    }

    /** An erased payload had a one-time link or code in it, so that email can no longer be sent. */
    public function countRetryableFailed(): int
    {
        return (int) $this->pdo()->query("SELECT COUNT(*) FROM notifications WHERE channel = 'email' AND status = 'failed' AND JSON_LENGTH(payload_json) > 0")->fetchColumn();
    }

    public function requeueFailedEmails(): int
    {
        NotificationStatus::assertTransition(NotificationStatus::FAILED, NotificationStatus::QUEUED);

        return (int) $this->pdo()->exec("UPDATE notifications SET status = 'queued', attempts = 0, last_error = NULL WHERE channel = 'email' AND status = 'failed' AND JSON_LENGTH(payload_json) > 0");
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND channel = 'in_app' AND read_at IS NULL"
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function latestInApp(int $userId, int $limit): array
    {
        $limit = max(1, $limit);
        $statement = $this->pdo()->prepare(
            "SELECT * FROM notifications
             WHERE user_id = ? AND channel = 'in_app'
             ORDER BY notification_id DESC
             LIMIT {$limit}"
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /** Only the owner's own unread row changes. */
    public function markRead(int $id, int $userId): bool
    {
        $statement = $this->pdo()->prepare(
            "UPDATE notifications SET read_at = ?
             WHERE notification_id = ? AND user_id = ? AND channel = 'in_app' AND read_at IS NULL"
        );
        $statement->execute([date('Y-m-d H:i:s'), $id, $userId]);

        return $statement->rowCount() > 0;
    }

    public function markAllRead(int $userId): int
    {
        $statement = $this->pdo()->prepare(
            "UPDATE notifications SET read_at = ?
             WHERE user_id = ? AND channel = 'in_app' AND read_at IS NULL"
        );
        $statement->execute([date('Y-m-d H:i:s'), $userId]);

        return $statement->rowCount();
    }
}
