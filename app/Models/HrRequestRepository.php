<?php

declare(strict_types=1);

namespace App\Models;

final class HrRequestRepository extends BaseRepository
{
    protected string $table = 'hr_requests';
    protected string $primaryKey = 'request_id';

    private const DETAILED_SELECT = "SELECT r.*, c.name AS committee_name, c.head_user_id, p.title AS position_title,
                CONCAT(u.first_name, ' ', u.last_name) AS requester_name
         FROM hr_requests r
         JOIN committees c ON c.committee_id = r.committee_id
         JOIN positions p ON p.position_id = r.position_id
         JOIN users u ON u.user_id = r.requested_by";

    /** @return array<string, mixed>|null The request with committee, position and requester names added. */
    public function findDetailed(int $id): ?array
    {
        $statement = $this->pdo()->prepare(self::DETAILED_SELECT . ' WHERE r.request_id = ?');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null Must run inside a transaction. */
    public function lockAndFind(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM hr_requests WHERE request_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /**
     * @param array{status?: string, q?: string, visible_to?: int} $filters
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function search(array $filters, int $page, int $perPage = 20): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (isset($filters['status'])) {
            $conditions[] = 'r.status = ?';
            $params[] = $filters['status'];
        }

        if (isset($filters['q'])) {
            $like = '%' . strtr($filters['q'], ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
            $conditions[] = "(c.name LIKE ? ESCAPE '|' OR p.title LIKE ? ESCAPE '|')";
            array_push($params, $like, $like);
        }

        if (isset($filters['visible_to'])) {
            $conditions[] = '(r.requested_by = ? OR c.head_user_id = ?)';
            array_push($params, $filters['visible_to'], $filters['visible_to']);
        }

        $where = implode(' AND ', $conditions);
        $count = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM hr_requests r
             JOIN committees c ON c.committee_id = r.committee_id
             JOIN positions p ON p.position_id = r.position_id
             WHERE {$where}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers.
        $rows = $this->pdo()->prepare(self::DETAILED_SELECT . " WHERE {$where} ORDER BY r.request_id DESC LIMIT {$perPage} OFFSET {$offset}");
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }

    /** @return list<array<string, mixed>> Approved requests with no vacancy, or only cancelled ones. */
    public function readyForVacancy(): array
    {
        return $this->pdo()->query(
            self::DETAILED_SELECT . " WHERE r.status = 'approved'
               AND NOT EXISTS (SELECT 1 FROM vacancies v WHERE v.request_id = r.request_id AND v.status <> 'cancelled')
             ORDER BY r.approved_at, r.request_id"
        )->fetchAll();
    }
}
