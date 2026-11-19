<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\EncryptionService;
use RuntimeException;

final class WelfareCaseRepository extends BaseRepository
{
    protected string $table = 'welfare_cases';
    protected string $primaryKey = 'case_id';

    /**
     * Only what public tracking needs. Category, subject, description and priority are deliberately not selected.
     * The owner is the account's email, or the guest's for a guest case (BR-17).
     *
     * @return array<string, mixed>|null
     */
    public function findTrackable(string $caseCode): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT c.case_id, c.status, c.submitted_at, u.email AS account_email, c.guest_email
             FROM welfare_cases c
             LEFT JOIN users u ON u.user_id = c.user_id
             WHERE c.case_code = ?
             LIMIT 1'
        );
        $statement->execute([$caseCode]);
        $case = $statement->fetch();

        if ($case === false) {
            return null;
        }

        // The guest's email is encrypted (M3), so it is decrypted here and never leaves this method otherwise.
        $ownerEmail = $case['account_email'];

        if ($ownerEmail === null && $case['guest_email'] !== null) {
            try {
                $ownerEmail = (new EncryptionService())->decrypt($case['guest_email']);
            } catch (RuntimeException) {
                $ownerEmail = null;
            }
        }

        unset($case['account_email'], $case['guest_email']);

        return $case + ['owner_email' => $ownerEmail];
    }

    /** @return list<array<string, mixed>> Status changes only, not other events such as category changes. */
    public function statusChanges(int $caseId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT new_value, created_at FROM welfare_case_history
             WHERE case_id = ? AND event_type = 'status_change'
             ORDER BY created_at, history_id"
        );
        $statement->execute([$caseId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function studentVisibleNotes(int $caseId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT note, created_at FROM case_notes WHERE case_id = ? AND visible_to_student = 1 ORDER BY created_at, note_id'
        );
        $statement->execute([$caseId]);

        return $statement->fetchAll();
    }

    /** A NULL actor means a guest (section 9). */
    public function recordView(int $caseId, ?int $actorId, string $where = 'public_tracking'): void
    {
        $this->addHistory($caseId, 'viewed', null, $where, $actorId);
    }

    /** History rows hold events and statuses only, never a case's text. */
    public function addHistory(int $caseId, string $eventType, ?string $oldValue, ?string $newValue, ?int $actorId): void
    {
        $this->pdo()->prepare(
            'INSERT INTO welfare_case_history (case_id, event_type, old_value, new_value, actor_id) VALUES (?, ?, ?, ?, ?)'
        )->execute([$caseId, $eventType, $oldValue, $newValue, $actorId]);
    }

    /** @return list<array<string, mixed>> Code and status only, for the top bar search. */
    public function searchByCode(string $query, int $limit): array
    {
        $statement = $this->pdo()->prepare("SELECT case_id, case_code, status FROM welfare_cases WHERE case_code LIKE ? ESCAPE '|' ORDER BY case_id DESC LIMIT " . max(1, $limit));
        $statement->execute([strtr(strtoupper($query), ['|' => '||', '%' => '|%', '_' => '|_']) . '%']);

        return $statement->fetchAll();
    }

    public function codeExists(string $caseCode): bool
    {
        return $this->findBy(['case_code' => $caseCode]) !== [];
    }

    /**
     * The queue in its fixed order: most urgent first, then oldest. Rows still hold ciphertext.
     *
     * @param array{status?: string, category_id?: int, priority?: string, assigned_to?: int, unassigned?: bool, from?: string, to?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function forQueue(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        foreach (['status' => 'c.status', 'category_id' => 'c.category_id', 'priority' => 'c.priority', 'assigned_to' => 'c.assigned_to'] as $key => $column) {
            if (isset($filters[$key])) {
                $conditions[] = "{$column} = ?";
                $params[] = $filters[$key];
            }
        }

        if (($filters['unassigned'] ?? false) === true) {
            $conditions[] = 'c.assigned_to IS NULL';
        }

        if (isset($filters['from'])) {
            $conditions[] = 'c.submitted_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }

        if (isset($filters['to'])) {
            $conditions[] = 'c.submitted_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $where = implode(' AND ', $conditions);
        $statement = $this->pdo()->prepare(
            "SELECT c.case_id, c.case_code, c.subject, c.priority, c.status, c.category_id, c.student_marked_urgent,
                    c.acknowledged_at, c.submitted_at, w.name AS category_name, CONCAT(h.first_name, ' ', h.last_name) AS handler_name
             FROM welfare_cases c
             JOIN welfare_categories w ON w.category_id = c.category_id
             LEFT JOIN users h ON h.user_id = c.assigned_to
             WHERE {$where}
             ORDER BY FIELD(c.priority, 'urgent', 'high', 'medium', 'low'), c.submitted_at, c.case_id"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null The case with its category, handler, submitter and related code added. */
    public function findDetailed(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT c.*, w.name AS category_name, CONCAT(h.first_name, ' ', h.last_name) AS handler_name,
                    CONCAT(s.first_name, ' ', s.last_name) AS submitter_name, s.email AS submitter_email, s.student_no AS submitter_student_no,
                    r.slug AS submitter_role, rel.case_code AS related_code
             FROM welfare_cases c
             JOIN welfare_categories w ON w.category_id = c.category_id
             LEFT JOIN users h ON h.user_id = c.assigned_to
             LEFT JOIN users s ON s.user_id = c.user_id
             LEFT JOIN roles r ON r.role_id = s.role_id
             LEFT JOIN welfare_cases rel ON rel.case_id = c.related_case_id
             WHERE c.case_id = ?"
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null Must run inside a transaction. */
    public function lockAndFind(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM welfare_cases WHERE case_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> Every event, oldest first, with who did it. */
    public function history(int $caseId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT h.event_type, h.old_value, h.new_value, h.created_at, CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM welfare_case_history h
             LEFT JOIN users u ON u.user_id = h.actor_id
             WHERE h.case_id = ?
             ORDER BY h.history_id"
        );
        $statement->execute([$caseId]);

        return $statement->fetchAll();
    }

    public function hasEvent(int $caseId, string $eventType): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM welfare_case_history WHERE case_id = ? AND event_type = ?');
        $statement->execute([$caseId, $eventType]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * BR-07: urgent or Harassment/Bullying cases still not acknowledged and submitted before $cutoff.
     *
     * @return list<int>
     */
    public function overdueAcknowledgementIds(string $cutoff): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT case_id FROM welfare_cases
             WHERE (priority = 'urgent' OR student_marked_urgent = 1 OR category_id = 6)
               AND acknowledged_at IS NULL AND status <> 'closed' AND submitted_at <= ?
             ORDER BY case_id"
        );
        $statement->execute([$cutoff]);

        return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return list<array<string, mixed>> Newest first. */
    public function closedForUser(int $userId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT case_id, case_code, subject FROM welfare_cases WHERE user_id = ? AND status = 'closed' ORDER BY case_id DESC"
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null The case, when this email owns it. */
    public function findForEmail(string $caseCode, string $email): ?array
    {
        $case = $this->findTrackable($caseCode);

        return $case !== null && $case['owner_email'] !== null && strtolower($case['owner_email']) === strtolower($email) ? $case : null;
    }

    /** @return list<int> Guest cases submitted with this email that no account holds yet. */
    public function guestCaseIds(string $email): array
    {
        $statement = $this->pdo()->prepare('SELECT case_id FROM welfare_cases WHERE user_id IS NULL AND guest_email_hash = ? ORDER BY case_id');
        $statement->execute([(new EncryptionService())->blindIndex($email)]);

        return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
    }
}
