<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use DomainException;
use PDO;

/**
 * Service requests (Nov 23, 2026): what a member asks the MSW Committee that is not a welfare concern. One thread of
 * replies per request. The member sees only their own; MSW staff (service_request.manage) handle all of them.
 * Welfare concerns stay in Ask for Help, which is confidential.
 */
final class ServiceRequestService
{
    public const TYPES = [
        'record_update' => 'Correct my record (name, student number, year, section)',
        'document' => 'Request a document (endorsement, certification)',
        'inquiry' => 'Question about AGILE',
        'technical' => 'Problem with my account or the system',
        'other' => 'Something else',
    ];

    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];

    /** Statuses where the thread is still active, so the member can reply or cancel. */
    public const ACTIVE = ['open', 'in_progress'];

    private const SUBJECT_LIMIT = 150;
    private const BODY_LIMIT = 3000;

    public static function code(int $requestId): string
    {
        return sprintf('REQ-%06d', $requestId);
    }

    /**
     * @return array<string, string> field => error
     */
    public static function errors(string $type, string $subject, string $body): array
    {
        $errors = [];

        if (!isset(self::TYPES[$type])) {
            $errors['type'] = 'Choose what the request is about.';
        }

        if ($subject === '') {
            $errors['subject'] = 'Enter a subject.';
        } elseif (mb_strlen($subject) > self::SUBJECT_LIMIT) {
            $errors['subject'] = 'The subject is 150 characters at most.';
        }

        if ($body === '') {
            $errors['body'] = 'Describe your request.';
        } elseif (mb_strlen($body) > self::BODY_LIMIT) {
            $errors['body'] = 'The message is 3,000 characters at most.';
        }

        return $errors;
    }

    /** @throws DomainException */
    public function create(string $type, string $subject, string $body): int
    {
        authorize('service_request.submit');

        if (self::errors($type, $subject, $body) !== []) {
            throw new DomainException('Please complete the request.');
        }

        return Database::transaction(function () use ($type, $subject, $body): int {
            $db = Database::connection();
            $db->prepare('INSERT INTO service_requests (user_id, type, subject) VALUES (?, ?, ?)')->execute([current_user_id(), $type, $subject]);
            $id = (int) $db->lastInsertId();
            $this->addMessage($id, $body, false);
            AuditService::log('service_request.created', 'service_request', $id, ['type' => $type]);
            $payload = ['code' => self::code($id), 'type' => explode(' (', self::TYPES[$type])[0], 'subject' => $subject];
            $notices = new NotificationService();

            foreach (['msw_member', 'msw_head'] as $role) {
                $notices->notifyRole($role, 'SERVICE_REQUEST_NEW', $payload, '/requests/' . $id);
            }

            return $id;
        });
    }

    /** @return list<array<string, mixed>> The signed-in person's requests, newest first. */
    public function mine(): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM service_requests WHERE user_id = ? ORDER BY created_at DESC, request_id DESC');
        $statement->execute([current_user_id()]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A request with its thread, for its owner or MSW staff. Anyone else gets a 403 whether or not it exists.
     *
     * @return array{request: array<string, mixed>, messages: list<array<string, mixed>>}|null
     */
    public function open(int $requestId): ?array
    {
        $request = $this->find($requestId);
        $isOwner = $request !== null && (int) $request['user_id'] === current_user_id();

        if (!$isOwner && !can('service_request.manage')) {
            throw new ForbiddenException('service_request.view');
        }

        if ($request === null) {
            return null;
        }

        $messages = Database::connection()->prepare(
            "SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
             FROM service_request_messages m JOIN users u ON u.user_id = m.author_id
             WHERE m.request_id = ? ORDER BY m.created_at, m.message_id"
        );
        $messages->execute([$requestId]);

        return ['request' => $request, 'messages' => $messages->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** The owner or MSW staff add to the thread while the request is active. @throws DomainException */
    public function reply(int $requestId, string $body): void
    {
        $request = $this->open($requestId)['request'] ?? throw new DomainException('Request not found.');

        if (!in_array($request['status'], self::ACTIVE, true)) {
            throw new DomainException('This request is ' . strtolower(self::STATUSES[$request['status']]) . ', so it takes no more replies.');
        }

        if ($body === '' || mb_strlen($body) > self::BODY_LIMIT) {
            throw new DomainException('Write a reply of up to 3,000 characters.');
        }

        $fromStaff = (int) $request['user_id'] !== current_user_id();

        Database::transaction(function () use ($requestId, $body, $fromStaff, $request): void {
            $this->addMessage($requestId, $body, $fromStaff);
            // A staff reply on an open request means someone is on it.
            $status = $fromStaff && $request['status'] === 'open' ? 'in_progress' : $request['status'];
            Database::connection()->prepare('UPDATE service_requests SET status = ?, assigned_to = COALESCE(assigned_to, ?), updated_at = NOW() WHERE request_id = ?')
                ->execute([$status, $fromStaff ? current_user_id() : null, $requestId]);
            AuditService::log('service_request.replied', 'service_request', $requestId, ['from_staff' => $fromStaff]);

            if ($fromStaff) {
                $this->tellRequester($request, $status);
            }
        });
    }

    /** The owner withdraws a request still active. @throws DomainException */
    public function cancel(int $requestId): void
    {
        $request = $this->find($requestId);

        if ($request === null || (int) $request['user_id'] !== current_user_id()) {
            throw new ForbiddenException('service_request.cancel');
        }

        if (!in_array($request['status'], self::ACTIVE, true)) {
            throw new DomainException('Only an open or in-progress request can be cancelled.');
        }

        Database::connection()->prepare("UPDATE service_requests SET status = 'cancelled' WHERE request_id = ?")->execute([$requestId]);
        AuditService::log('service_request.cancelled', 'service_request', $requestId);
    }

    /**
     * The MSW queue: requests by status (active ones first, oldest first, so nothing waits too long).
     *
     * @return list<array<string, mixed>>
     */
    public function queue(string $status): array
    {
        authorize('service_request.manage');
        $where = isset(self::STATUSES[$status]) ? 'WHERE r.status = ?' : "WHERE r.status IN ('open', 'in_progress')";
        $statement = Database::connection()->prepare(
            "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS requester_name, CONCAT(a.first_name, ' ', a.last_name) AS assigned_name,
                    (SELECT COUNT(*) FROM service_request_messages m WHERE m.request_id = r.request_id) AS messages
             FROM service_requests r JOIN users u ON u.user_id = r.user_id LEFT JOIN users a ON a.user_id = r.assigned_to
             {$where} ORDER BY r.created_at, r.request_id"
        );
        $statement->execute(isset(self::STATUSES[$status]) ? [$status] : []);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, int> status => how many, for the queue's filter. */
    public function counts(): array
    {
        authorize('service_request.manage');
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);

        foreach (Database::connection()->query('SELECT status, COUNT(*) AS n FROM service_requests GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** @return list<array<string, mixed>> MSW staff who can be assigned a request. */
    public function handlers(): array
    {
        return Database::connection()->query(
            "SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE u.status = 'active' AND r.slug IN ('msw_member', 'msw_head') ORDER BY u.last_name, u.first_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * MSW staff set the status and who handles it. Cancelled is the member's alone.
     *
     * @throws DomainException
     */
    public function manage(int $requestId, string $status, ?int $assignee): void
    {
        authorize('service_request.manage');
        $request = $this->find($requestId) ?? throw new DomainException('Request not found.');

        if ($request['status'] === 'cancelled') {
            throw new DomainException('The member cancelled this request.');
        }

        if (!isset(self::STATUSES[$status]) || $status === 'cancelled') {
            throw new DomainException('Choose open, in progress, resolved or closed.');
        }

        if ($assignee !== null && !in_array($assignee, array_map('intval', array_column($this->handlers(), 'user_id')), true)) {
            throw new DomainException('Assign the request to an MSW Committee member.');
        }

        $changed = $status !== $request['status'];

        Database::connection()->prepare('UPDATE service_requests SET status = ?, assigned_to = ?, resolved_at = ? WHERE request_id = ?')
            ->execute([$status, $assignee, $status === 'resolved' ? date('Y-m-d H:i:s') : ($status === 'closed' ? $request['resolved_at'] : null), $requestId]);
        AuditService::log('service_request.updated', 'service_request', $requestId, ['status' => $status, 'assigned_to' => $assignee]);

        if ($changed) {
            $this->tellRequester($request, $status);
        }
    }

    /** The member always hears about a reply or a new status on their request. */
    private function tellRequester(array $request, string $status): void
    {
        (new NotificationService())->notify((int) $request['user_id'], 'SERVICE_REQUEST_UPDATE', [
            'code' => self::code((int) $request['request_id']),
            'subject' => $request['subject'],
            'status' => self::STATUSES[$status],
        ], '/requests/' . (int) $request['request_id']);
    }

    /** @return array<string, mixed>|null */
    private function find(int $requestId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS requester_name, u.email AS requester_email,
                    CONCAT(a.first_name, ' ', a.last_name) AS assigned_name
             FROM service_requests r JOIN users u ON u.user_id = r.user_id LEFT JOIN users a ON a.user_id = r.assigned_to
             WHERE r.request_id = ?"
        );
        $statement->execute([$requestId]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function addMessage(int $requestId, string $body, bool $fromStaff): void
    {
        Database::connection()->prepare('INSERT INTO service_request_messages (request_id, author_id, from_staff, body) VALUES (?, ?, ?, ?)')
            ->execute([$requestId, current_user_id(), $fromStaff ? 1 : 0, $body]);
    }
}
