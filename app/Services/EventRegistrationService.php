<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MemberRepository;
use DomainException;
use PDO;

/**
 * Event registration and attendance. An active member registers for a published event before it
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

    /** @return list<array<string, mixed>> Published events, newest first, with registration and attendance counts. */
    public function events(): array
    {
        authorize('event.attendance');

        return Database::connection()->query(
            "SELECT p.post_id, p.title, p.slug, p.event_start, p.event_end, p.venue_or_link, p.capacity, p.registration_open,
                    COALESCE(SUM(r.status = 'registered'), 0) AS registered,
                    COALESCE(SUM(r.status = 'registered' AND r.attended_at IS NOT NULL), 0) AS attended
             FROM content_posts p LEFT JOIN event_registrations r ON r.post_id = p.post_id
             WHERE p.type = 'event' AND p.status = 'published'
             GROUP BY p.post_id ORDER BY p.event_start DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null A published event. */
    public function event(int $postId): ?array
    {
        authorize('event.attendance');
        $statement = Database::connection()->prepare("SELECT * FROM content_posts WHERE post_id = ? AND type = 'event' AND status = 'published'");
        $statement->execute([$postId]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array<string, mixed>> Who registered (and walk-ins), with member number, year and section. */
    public function registrants(int $postId): array
    {
        authorize('event.attendance');
        $statement = Database::connection()->prepare(
            "SELECT r.*, m.member_no, u.first_name, u.last_name, u.year_level, u.section,
                    CONCAT(c.first_name, ' ', c.last_name) AS checked_in_by_name
             FROM event_registrations r
             JOIN members m ON m.member_id = r.member_id
             JOIN users u ON u.user_id = m.user_id
             LEFT JOIN users c ON c.user_id = r.checked_in_by
             WHERE r.post_id = ? AND r.status = 'registered'
             ORDER BY u.last_name, u.first_name"
        );
        $statement->execute([$postId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Checks a member in by member number, or by scanning the QR on their AGILE ID (the verify link or its token).
     * Someone who did not register is added as a walk-in. Returns the member's name.
     *
     * @throws DomainException
     */
    public function checkIn(int $postId, string $input): string
    {
        authorize('event.attendance');
        $memberId = $this->memberFromInput($input);

        if ($memberId === null) {
            throw new DomainException('No active AGILE member matches that member number or ID.');
        }

        return Database::transaction(function () use ($postId, $memberId): string {
            $post = $this->lockPost($postId);

            if ($post['type'] !== 'event' || $post['status'] !== 'published') {
                throw new DomainException('Event not found.');
            }

            $db = Database::connection();
            $existing = $this->registration($postId, $memberId);
            $name = $db->prepare("SELECT CONCAT(u.first_name, ' ', u.last_name) FROM members m JOIN users u ON u.user_id = m.user_id WHERE m.member_id = ?");
            $name->execute([$memberId]);
            $memberName = (string) $name->fetchColumn();

            if ($existing !== null && $existing['status'] === 'registered' && $existing['attended_at'] !== null) {
                throw new DomainException("{$memberName} is already checked in.");
            }

            if ($existing === null) {
                $db->prepare('INSERT INTO event_registrations (post_id, member_id, walk_in, attended_at, checked_in_by) VALUES (?, ?, 1, NOW(), ?)')
                    ->execute([$postId, $memberId, current_user_id()]);
            } else {
                $db->prepare("UPDATE event_registrations SET status = 'registered', cancelled_at = NULL, attended_at = NOW(), checked_in_by = ? WHERE registration_id = ?")
                    ->execute([current_user_id(), (int) $existing['registration_id']]);
            }

            AuditService::log('event.checked_in', 'content_post', $postId, ['member_id' => $memberId, 'walk_in' => $existing === null]);

            return $memberName;
        });
    }

    /** Undoes a check-in made by mistake. @throws DomainException */
    public function undoCheckIn(int $postId, int $registrationId): void
    {
        authorize('event.attendance');
        $statement = Database::connection()->prepare('UPDATE event_registrations SET attended_at = NULL, checked_in_by = NULL WHERE registration_id = ? AND post_id = ? AND attended_at IS NOT NULL');
        $statement->execute([$registrationId, $postId]);

        if ($statement->rowCount() === 0) {
            throw new DomainException('That check-in was not found.');
        }

        AuditService::log('event.check_in_undone', 'content_post', $postId, ['registration_id' => $registrationId]);
    }

    /** The attendance list as CSV, safe to open in a spreadsheet. */
    public function csv(int $postId): string
    {
        $rows = [['Member no.', 'Last name', 'First name', 'Year level', 'Section', 'Registered', 'Checked in', 'Walk-in']];

        foreach ($this->registrants($postId) as $r) {
            $rows[] = [$r['member_no'], $r['last_name'], $r['first_name'], (string) $r['year_level'], (string) $r['section'],
                (int) $r['walk_in'] === 1 ? '' : $r['registered_at'], (string) $r['attended_at'], (int) $r['walk_in'] === 1 ? 'Yes' : 'No'];
        }

        $out = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($cell): string => ReportService::safeCell((string) $cell), $row), ',', '"', '');
        }

        rewind($out);

        return (string) stream_get_contents($out);
    }

    /** @return list<array<string, mixed>> The events the signed-in member registered for, latest first. */
    public function mine(): array
    {
        $member = current_user_id() === null ? null : $this->members->findByUser((int) current_user_id());

        if ($member === null) {
            return [];
        }

        $statement = Database::connection()->prepare(
            "SELECT p.title, p.slug, p.event_start, p.venue_or_link, r.attended_at
             FROM event_registrations r JOIN content_posts p ON p.post_id = r.post_id
             WHERE r.member_id = ? AND r.status = 'registered' AND p.status = 'published'
             ORDER BY p.event_start DESC"
        );
        $statement->execute([(int) $member['member_id']]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function memberFromInput(string $input): ?int
    {
        $input = trim($input);
        $db = Database::connection();

        // The QR on the ID holds the verify link; a scanner types it like a keyboard.
        if (preg_match('#([a-f0-9]{64})/?$#', $input, $match) === 1) {
            $statement = $db->prepare("SELECT member_id FROM members WHERE verify_token = ? AND status = 'active'");
            $statement->execute([$match[1]]);
        } else {
            $statement = $db->prepare("SELECT member_id FROM members WHERE member_no = ? AND status = 'active'");
            $statement->execute([strtoupper($input)]);
        }

        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
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
