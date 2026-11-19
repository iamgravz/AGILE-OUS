<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationRepository;
use App\Services\Mail\MailerInterface;
use RuntimeException;
use Throwable;

final class EmailQueueService
{
    public const MAX_ATTEMPTS = 3;

    // These payloads hold a live token or code. Section 11 stores tokens only as hashes, so the plain value
    // is erased from the row as soon as the email is finished with.
    private const SECRET_TEMPLATES = ['ACCOUNT_SETUP', 'APPLICATION_LINK', 'PASSWORD_RESET', 'GUEST_EMAIL_CODE', 'MEMBER_REGISTRATION_CODE'];

    public function __construct(
        private readonly NotificationRepository $notifications = new NotificationRepository(),
        private readonly TemplateRenderer $renderer = new TemplateRenderer(),
    ) {
    }

    /**
     * Sends due emails. $log receives one line per email, with ids only and never any content.
     *
     * @return array{sent: int, retrying: int, failed: int}
     */
    public function run(MailerInterface $mailer, callable $log, int $limit = 50): array
    {
        $counts = ['sent' => 0, 'retrying' => 0, 'failed' => 0];

        foreach ($this->notifications->dueEmails($limit) as $row) {
            $id = (int) $row['notification_id'];

            try {
                $this->send($mailer, $row);
                $this->markSent($row);
                $counts['sent']++;
                $log("#{$id} sent");
            } catch (Throwable $exception) {
                $attempts = $this->markFailed($row, $exception->getMessage());
                $outcome = $attempts < self::MAX_ATTEMPTS ? 'retrying' : 'failed';
                $counts[$outcome]++;
                $log(sprintf('#%d %s (attempt %d of %d): %s', $id, $outcome, $attempts, self::MAX_ATTEMPTS, $exception->getMessage()));
            }
        }

        return $counts;
    }

    /** @param array<string, mixed> $row */
    private function send(MailerInterface $mailer, array $row): void
    {
        $to = $row['user_id'] === null ? $row['recipient_email'] : $row['user_email'];

        if ($to === null || $to === '') {
            throw new RuntimeException('The notification has no recipient address.');
        }

        if ($row['template_code'] === null) {
            throw new RuntimeException('The notification has no email template.');
        }

        $rendered = $this->renderer->render($row['template_code'], json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR));
        $mailer->send($to, $rendered['subject'], $rendered['html']);
    }

    /** @param array<string, mixed> $row */
    private function markSent(array $row): void
    {
        NotificationStatus::assertTransition($row['status'], NotificationStatus::SENT);

        $this->notifications->update((int) $row['notification_id'], [
            'status' => NotificationStatus::SENT,
            'sent_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ] + $this->eraseSecrets($row));
    }

    /**
     * Records queued -> failed, then failed -> queued while attempts remain.
     *
     * @param array<string, mixed> $row
     * @return int Attempts used so far.
     */
    private function markFailed(array $row, string $error): int
    {
        $attempts = (int) $row['attempts'] + 1;
        $status = NotificationStatus::FAILED;
        NotificationStatus::assertTransition($row['status'], $status);

        $data = ['attempts' => $attempts, 'last_error' => mb_substr($error, 0, 1000)];

        if ($attempts < self::MAX_ATTEMPTS) {
            NotificationStatus::assertTransition($status, NotificationStatus::QUEUED);
            $status = NotificationStatus::QUEUED;
        } else {
            $data += $this->eraseSecrets($row);
        }

        $this->notifications->update((int) $row['notification_id'], ['status' => $status] + $data);

        return $attempts;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function eraseSecrets(array $row): array
    {
        // APP_FOR_REVISION carries an edit link only for someone without an account, so this looks at the payload too.
        return in_array($row['template_code'], self::SECRET_TEMPLATES, true) || str_contains((string) $row['payload_json'], 'token=')
            ? ['payload_json' => '{}', 'link' => null]
            : [];
    }
}
