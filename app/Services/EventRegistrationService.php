<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MemberRepository;
use DomainException;
use PDO;

/**
 * Event registration and attendance (Nov 23, 2026). An active member registers for a published event before it
 * starts, while its registration is open and slots remain; they can cancel until it starts. Officers with
 * event.attendance check registrants in at the event (EventAttendanceController).
 */
final class EventRegistrationService
{
    public function __construct(private readonly MemberRepository $members = new MemberRepository())
    {
    }

    /**
     * What the event page shows about registration, for the signed-in person (or a guest).
     *
     * @param array<string, mixed> $post a published content post
     * @return array{enabled: bool, open: bool, reason: string, registered: int, capacity: ?int, mine: ?array<string, mixed>, isMember: bool}
     */
    public function summary(array $post): array
    {
        $postId = (int) $post['post_id'];
        $member = current_user_id() === null ? null : $this->members->findByUser((int) current_user_id());
        $mine = null;

        if ($member !== null) {
            $statement = Database::connection()->prepare('SELECT * FROM event_registrations WHERE post_id = ? AND member_id = ?');
            $statement->execute([$postId, (int) $member['member_id']]);
            $mine = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $registered = $this->registeredCount($postId);
        $capacity = $post['capacity'] === null ? null : (int) $post['capacity'];

        return [
            'enabled' => $post['type'] === 'event' && (int) $post['registration_open'] === 1,
            'open' => self::closedReason($post, $registered) === '',
            'reason' => self::closedReason($post, $registered),
            'registered' => $registered,
            'capacity' => $capacity,
            'mine' => $mine !== null && $mine['status'] === 'registered' ? $mine : null,
            'isMember' => $member !== null,
        ];
    }

    /** Why registration is closed, or '' when it is open. */
    public static function closedReason(array $post, int $registered, ?string $now = null): string
    {
        $now ??= date('Y-m-d H:i:s');

        return match (true) {
            $post['type'] !== 'event' || $post['status'] !== 'published' => 'This is not an open event.',
            (int) $post['registration_open'] !== 1 => 'Registration is closed for this event.',
            $post['event_start'] === null || $post['event_start'] <= $now => 'Registration closed when the event started.',
            $post['capacity'] !== null && $registered >= (int) $post['capacity'] => 'The event is full.',
            default => '',
        };
    }

    /** @throws DomainException */
    public function register(int $postId): void
    {
        $member = $this->currentMember();

        Database::transaction(function () use ($postId, $member): void {
            $post = $this->lockPost($postId);
            $reason = self::closedReason($post, $this->registeredCount($postId));
            $existing = $this->registration($postId, (int) $member['member_id']);

            if ($existing !== null && $existing['status'] === 'registered') {
                throw new DomainException('You are already registered for this event.');
            }

            if ($reason !== '') {
                throw new DomainException($reason);
            }

            $db = Database::connection();

            if ($existing === null) {
                $db->prepare('INSERT INTO event_registrations (post_id, member_id) VALUES (?, ?)')->execute([$postId, (int) $member['member_id']]);
            } else {
                $db->prepare("UPDATE event_registrations SET status = 'registered', registered_at = NOW(), cancelled_at = NULL WHERE registration_id = ?")
                    ->execute([(int) $existing['registration_id']]);
            }

            AuditService::log('event.registered', 'content_post', $postId, ['member_id' => (int) $member['member_id']]);
        });
    }

    /** @throws DomainException */
    public function cancel(int $postId): void
    {
        $member = $this->currentMember();

        Database::transaction(function () use ($postId, $member): void {
            $post = $this->lockPost($postId);
            $existing = $this->registration($postId, (int) $member['member_id']);

            if ($existing === null || $existing['status'] !== 'registered') {
                throw new DomainException('You are not registered for this event.');
            }

            if ($post['event_start'] !== null && $post['event_start'] <= date('Y-m-d H:i:s')) {
                throw new DomainException('The event has started, so the registration can no longer be cancelled.');
            }

            Database::connection()->prepare("UPDATE event_registrations SET status = 'cancelled', cancelled_at = NOW() WHERE registration_id = ?")
                ->execute([(int) $existing['registration_id']]);
            AuditService::log('event.registration_cancelled', 'content_post', $postId, ['member_id' => (int) $member['member_id']]);
        });
    }

    public function registeredCount(int $postId): int
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM event_registrations WHERE post_id = ? AND status = 'registered'");
        $statement->execute([$postId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     * @throws DomainException
     */
    private function currentMember(): array
    {
        $member = current_user_id() === null ? null : $this->members->findByUser((int) current_user_id());

        if ($member === null || $member['status'] !== 'active') {
            throw new DomainException('Only AGILE members can register for events.');
        }

        return $member;
    }

    /** @return array<string, mixed> */
    private function lockPost(int $postId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM content_posts WHERE post_id = ? FOR UPDATE');
        $statement->execute([$postId]);
        $post = $statement->fetch(PDO::FETCH_ASSOC);

        if ($post === false) {
            throw new DomainException('Event not found.');
        }

        return $post;
    }

    /** @return array<string, mixed>|null */
    private function registration(int $postId, int $memberId): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM event_registrations WHERE post_id = ? AND member_id = ? FOR UPDATE');
        $statement->execute([$postId, $memberId]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
