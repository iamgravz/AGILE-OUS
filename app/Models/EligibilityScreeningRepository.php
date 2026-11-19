<?php

declare(strict_types=1);

namespace App\Models;

final class EligibilityScreeningRepository extends BaseRepository
{
    protected string $table = 'eligibility_screenings';
    protected string $primaryKey = 'screening_id';

    private const DETAILED_SELECT = "SELECT s.*, t.academic_year, t.semester, t.screening_deadline, t.grace_days,
                m.user_id, m.member_no, CONCAT(u.first_name, ' ', u.last_name) AS officer_name, u.student_no,
                r.slug AS role_slug, r.role_name, p.title AS position_title, c.name AS committee_name
         FROM eligibility_screenings s
         JOIN academic_terms t ON t.term_id = s.term_id
         JOIN members m ON m.member_id = s.member_id
         JOIN users u ON u.user_id = m.user_id
         JOIN roles r ON r.role_id = u.role_id
         LEFT JOIN positions p ON p.position_id = m.position_id
         LEFT JOIN committees c ON c.committee_id = p.committee_id";

    /**
     * BR-08: active officers by their position's role_type, whatever their system role. General Members have none of these.
     *
     * @return list<array<string, mixed>>
     */
    public function coveredWithoutScreening(int $termId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT m.member_id, m.user_id, m.member_no, CONCAT(u.first_name, ' ', u.last_name) AS officer_name,
                    p.title AS position_title, c.name AS committee_name
             FROM members m
             JOIN users u ON u.user_id = m.user_id
             JOIN positions p ON p.position_id = m.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.role_type IN ('officer', 'committee_head', 'deputy', 'committee_member', 'source_code')
               AND m.role_status = 'active' AND m.status = 'active' AND u.status = 'active'
               AND NOT EXISTS (SELECT 1 FROM eligibility_screenings s WHERE s.term_id = ? AND s.member_id = m.member_id)
             ORDER BY u.last_name, u.first_name"
        );
        $statement->execute([$termId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findFor(int $termId, int $memberId): ?array
    {
        return $this->findBy(['term_id' => $termId, 'member_id' => $memberId])[0] ?? null;
    }

    /** @return array<string, mixed>|null With the term, the officer and their position. */
    public function findDetailed(int $id): ?array
    {
        $statement = $this->pdo()->prepare(self::DETAILED_SELECT . ' WHERE s.screening_id = ?');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null Must run inside a transaction. */
    public function lockAndFind(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM eligibility_screenings WHERE screening_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return list<int> */
    public function pendingIds(int $termId): array
    {
        $statement = $this->pdo()->prepare("SELECT screening_id FROM eligibility_screenings WHERE term_id = ? AND result = 'pending' ORDER BY screening_id");
        $statement->execute([$termId]);

        return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @param array{term_id: int, result?: string, owner_role?: string} $filters
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function search(array $filters, int $page, int $perPage = 20): array
    {
        $conditions = ['s.term_id = ?'];
        $params = [$filters['term_id']];

        if (isset($filters['result'])) {
            $conditions[] = 's.result = ?';
            $params[] = $filters['result'];
        }

        if (isset($filters['owner_role'])) {
            $conditions[] = 'r.slug = ?';
            $params[] = $filters['owner_role'];
        }

        $where = implode(' AND ', $conditions);
        $count = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM eligibility_screenings s
             JOIN members m ON m.member_id = s.member_id
             JOIN users u ON u.user_id = m.user_id
             JOIN roles r ON r.role_id = u.role_id
             WHERE {$where}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers.
        $rows = $this->pdo()->prepare(self::DETAILED_SELECT . " WHERE {$where} ORDER BY u.last_name, u.first_name LIMIT {$perPage} OFFSET {$offset}");
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }
}
