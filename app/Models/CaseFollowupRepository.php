<?php

declare(strict_types=1);

namespace App\Models;

final class CaseFollowupRepository extends BaseRepository
{
    protected string $table = 'case_followups';
    protected string $primaryKey = 'followup_id';

    /** @return list<array<string, mixed>> Soonest first. Remarks are still ciphertext. */
    public function forCase(int $caseId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT f.followup_id, f.due_at, f.done_at, f.remarks, f.reminder_sent_at, CONCAT(u.first_name, ' ', u.last_name) AS handler_name
             FROM case_followups f
             JOIN users u ON u.user_id = f.handler_id
             WHERE f.case_id = ?
             ORDER BY f.due_at, f.followup_id"
        );
        $statement->execute([$caseId]);

        return $statement->fetchAll();
    }

    /** @return list<int> Open follow-ups due by $endOfToday on cases that are not closed, not reminded yet. */
    public function dueForReminder(string $endOfToday): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT f.followup_id
             FROM case_followups f
             JOIN welfare_cases c ON c.case_id = f.case_id
             WHERE f.done_at IS NULL AND f.reminder_sent_at IS NULL AND f.due_at <= ? AND c.status <> 'closed'
             ORDER BY f.followup_id"
        );
        $statement->execute([$endOfToday]);

        return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return array<string, mixed>|null With the case code. Must run inside a transaction. */
    public function lockForReminder(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT f.*, c.case_code
             FROM case_followups f
             JOIN welfare_cases c ON c.case_id = f.case_id
             WHERE f.followup_id = ?
             FOR UPDATE'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }
}
