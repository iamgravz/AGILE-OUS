<?php

declare(strict_types=1);

namespace App\Models;

final class AppointmentRepository extends BaseRepository
{
    protected string $table = 'appointments';
    protected string $primaryKey = 'appointment_id';

    private const DETAILED_SELECT = "SELECT ap.*, a.tracking_code, a.user_id, a.vacancy_id, v.title AS vacancy_title, v.slots, v.slots_filled,
                v.status AS vacancy_status, p.title AS position_title, p.role_type, c.name AS committee_name,
                CONCAT(applicant.first_name, ' ', applicant.last_name) AS applicant_name,
                CONCAT(preparer.first_name, ' ', preparer.last_name) AS preparer_name
         FROM appointments ap
         JOIN membership_applications a ON a.application_id = ap.application_id
         LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id
         JOIN positions p ON p.position_id = ap.position_id
         LEFT JOIN committees c ON c.committee_id = p.committee_id
         JOIN users applicant ON applicant.user_id = a.user_id
         JOIN users preparer ON preparer.user_id = ap.prepared_by";

    /** @return array<string, mixed>|null */
    public function findDetailed(int $id): ?array
    {
        $statement = $this->pdo()->prepare(self::DETAILED_SELECT . ' WHERE ap.appointment_id = ?');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null Must run inside a transaction. */
    public function lockAndFind(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM appointments WHERE appointment_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null The application's prepared or approved appointment. A returned one does not count. */
    public function liveForApplication(int $applicationId): ?array
    {
        $statement = $this->pdo()->prepare("SELECT * FROM appointments WHERE application_id = ? AND status <> 'returned' ORDER BY appointment_id DESC LIMIT 1");
        $statement->execute([$applicationId]);

        return $statement->fetch() ?: null;
    }

    /**
     * @param array{status?: string} $filters
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function search(array $filters, int $page, int $perPage = 20): array
    {
        $where = isset($filters['status']) ? 'ap.status = ?' : '1 = 1';
        $params = isset($filters['status']) ? [$filters['status']] : [];

        $count = $this->pdo()->prepare("SELECT COUNT(*) FROM appointments ap WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers.
        $rows = $this->pdo()->prepare(self::DETAILED_SELECT . " WHERE {$where} ORDER BY ap.appointment_id DESC LIMIT {$perPage} OFFSET {$offset}");
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }

    /** @return list<array<string, mixed>> Approved position applications waiting for an appointment, on vacancies with a free slot. */
    public function readyToPrepare(): array
    {
        return $this->pdo()->query(
            "SELECT a.application_id, a.tracking_code, a.decided_at, v.title AS vacancy_title, v.slots, v.slots_filled,
                    CONCAT(u.first_name, ' ', u.last_name) AS applicant_name
             FROM membership_applications a
             JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             JOIN users u ON u.user_id = a.user_id
             WHERE a.status = 'approved' AND v.status IN ('open', 'closed') AND v.slots_filled < v.slots
               AND NOT EXISTS (SELECT 1 FROM appointments ap WHERE ap.application_id = a.application_id AND ap.status <> 'returned')
             ORDER BY a.decided_at, a.application_id"
        )->fetchAll();
    }
}
