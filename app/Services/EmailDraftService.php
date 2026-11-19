<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CommitteeRepository;
use App\Models\EmailDraftRepository;
use DomainException;

/** Announcement emails, for email.compose. Sending queues one notification per recipient. */
final class EmailDraftService
{
    public const GROUPS = ['all_members' => 'All members', 'officers' => 'Officers', 'applicants' => 'Applicants being processed'];

    public function __construct(
        private readonly EmailDraftRepository $drafts = new EmailDraftRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int} */
    public function list(int $page): array
    {
        authorize('email.compose');

        return $this->drafts->listWithAuthors($page);
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        authorize('email.compose');

        return $this->drafts->find($id) ?? throw new DomainException('This draft no longer exists.');
    }

    /** @return array<string, string> target value => name */
    public function targets(): array
    {
        $targets = self::GROUPS;

        foreach ((new CommitteeRepository())->options() as $committee) {
            $targets['committee:' . (int) $committee['committee_id']] = $committee['name'] . ' Committee';
        }

        return $targets;
    }

    public function targetLabel(string $target): string
    {
        return $this->targets()[$target] ?? $target;
    }

    public function create(string $subject, string $body, string $target): int
    {
        authorize('email.compose');
        [$subject, $body] = $this->validated($subject, $body, $target);

        $id = $this->drafts->insert(['subject' => $subject, 'body_html' => $body, 'target_group' => $target, 'status' => 'draft', 'created_by' => current_user_id()]);
        AuditService::log('email_draft.created', 'email_draft', $id, ['target' => $target]);

        return $id;
    }

    public function update(int $id, string $subject, string $body, string $target): void
    {
        authorize('email.compose');
        [$subject, $body] = $this->validated($subject, $body, $target);

        $this->assertDraft($this->find($id));
        $this->drafts->update($id, ['subject' => $subject, 'body_html' => $body, 'target_group' => $target]);
        AuditService::log('email_draft.updated', 'email_draft', $id, ['target' => $target]);
    }

    public function recipientCount(string $target): int
    {
        authorize('email.compose');

        return count($this->drafts->recipients($target));
    }

    /** @return int How many emails were queued. */
    public function send(int $id): int
    {
        authorize('email.compose');

        return Database::transaction(function () use ($id): int {
            $draft = $this->drafts->findForUpdate($id) ?? throw new DomainException('This draft no longer exists.');
            $this->assertDraft($draft);
            $recipients = $this->drafts->recipients($draft['target_group']);

            if ($recipients === []) {
                throw new DomainException('This group has no one to email yet.');
            }

            $payload = ['subject' => $draft['subject'], 'body_html' => $draft['body_html'], 'announcement' => true];

            foreach ($recipients as $person) {
                if ($person['user_id'] !== null) {
                    $this->notifications->notify((int) $person['user_id'], 'ANNOUNCEMENT', $payload, null, ['email']);
                } else {
                    $this->notifications->notifyEmail($person['email'], 'ANNOUNCEMENT', $payload + ['name' => $person['first_name']]);
                }
            }

            $this->drafts->update($id, ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')]);
            AuditService::log('email_draft.sent', 'email_draft', $id, ['target' => $draft['target_group'], 'recipients' => count($recipients)]);

            return count($recipients);
        });
    }

    /** @return array{0: string, 1: string} */
    private function validated(string $subject, string $body, string $target): array
    {
        $subject = trim($subject);
        $body = (new HtmlSanitizer())->clean($body);

        if ($subject === '' || mb_strlen($subject) > 200) {
            throw new DomainException('Please write a subject of up to 200 characters.');
        }

        if (trim(strip_tags($body)) === '') {
            throw new DomainException('Please write the message.');
        }

        if (!array_key_exists($target, $this->targets())) {
            throw new DomainException('Please choose who to send this to.');
        }

        return [$subject, $body];
    }

    /** @param array<string, mixed> $draft */
    private function assertDraft(array $draft): void
    {
        if ($draft['status'] !== 'draft') {
            throw new DomainException('This email was already sent, so it cannot be changed or sent again.');
        }
    }
}
