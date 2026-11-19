<?php

declare(strict_types=1);

namespace App\Models;

final class InterviewRepository extends BaseRepository
{
    protected string $table = 'interviews';
    protected string $primaryKey = 'interview_id';

    /** @return list<array<string, mixed>> Newest first, with the interviewer's name. */
    public function forApplication(int $applicationId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) AS interviewer_name,
                    CONCAT(cb.first_name, ' ', cb.last_name) AS checklist_by_name
             FROM interviews i
             JOIN users u ON u.user_id = i.interviewer_id
             LEFT JOIN users cb ON cb.user_id = i.checklist_by
             WHERE i.application_id = ?
             ORDER BY i.interview_id DESC"
        );
        $statement->execute([$applicationId]);

        return $statement->fetchAll();
    }

    /**
     * @param array{result?: string} $filters
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function search(array $filters, int $page, int $perPage = 20): array
    {
        $where = isset($filters['result']) ? 'i.result = ?' : '1 = 1';
        $params = isset($filters['result']) ? [$filters['result']] : [];
        $from = 'FROM interviews i
             JOIN membership_applications a ON a.application_id = i.application_id
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             LEFT JOIN users applicant ON applicant.user_id = COALESCE(a.user_id, ap.user_id)
             JOIN users interviewer ON interviewer.user_id = i.interviewer_id';

        $count = $this->pdo()->prepare("SELECT COUNT(*) {$from} WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers.
        $rows = $this->pdo()->prepare(
            "SELECT i.*, a.tracking_code, CONCAT(COALESCE(applicant.first_name, ap.first_name), ' ', COALESCE(applicant.last_name, ap.last_name)) AS applicant_name,
                    CONCAT(interviewer.first_name, ' ', interviewer.last_name) AS interviewer_name
             {$from} WHERE {$where} ORDER BY i.scheduled_at DESC, i.interview_id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }

    /**
     * Pending interviews of applications still in progress whose time falls inside the reminder window.
     *
     * @return list<int>
     */
    public function dueForReminder(string $from, string $to): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT i.interview_id
             FROM interviews i
             JOIN membership_applications a ON a.application_id = i.application_id
             WHERE i.result = 'pending' AND i.reminder_sent_at IS NULL AND i.scheduled_at > ? AND i.scheduled_at <= ?
               AND a.status IN ('submitted', 'under_review', 'approved')
             ORDER BY i.interview_id"
        );
        $statement->execute([$from, $to]);

        return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return array<string, mixed>|null With the applicant's id and tracking code. Must run inside a transaction. */
    public function lockForReminder(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT i.*, a.user_id, a.applicant_id, a.tracking_code
             FROM interviews i
             JOIN membership_applications a ON a.application_id = i.application_id
             WHERE i.interview_id = ?
             FOR UPDATE'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** BR-25: this person declared a conflict of interest on one of this application's interviews. */
    public function hasConflict(int $applicationId, int $userId): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM interviews WHERE application_id = ? AND interviewer_id = ? AND conflict_of_interest = 1');
        $statement->execute([$applicationId, $userId]);

        return (int) $statement->fetchColumn() > 0;
    }
}
