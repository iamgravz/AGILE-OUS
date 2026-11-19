<?php

declare(strict_types=1);

namespace App\Models;

final class VacancyRepository extends BaseRepository
{
    protected string $table = 'vacancies';
    protected string $primaryKey = 'vacancy_id';

    private const OPEN_SELECT = "SELECT v.*, c.name AS committee_name, p.title AS position_title
         FROM vacancies v
         JOIN positions p ON p.position_id = v.position_id
         LEFT JOIN committees c ON c.committee_id = p.committee_id
         WHERE v.status = 'open' AND v.opens_at <= ? AND v.closes_at > ?";

    /** @return list<array<string, mixed>> Open vacancies, soonest deadline first. */
    public function openList(?int $limit = null): array
    {
        $limitSql = $limit === null ? '' : ' LIMIT ' . max(1, $limit);
        $statement = $this->pdo()->prepare(self::OPEN_SELECT . " ORDER BY v.closes_at ASC{$limitSql}");
        $now = date('Y-m-d H:i:s');
        $statement->execute([$now, $now]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findOpen(int $id): ?array
    {
        $statement = $this->pdo()->prepare(self::OPEN_SELECT . ' AND v.vacancy_id = ? LIMIT 1');
        $now = date('Y-m-d H:i:s');
        $statement->execute([$now, $now, $id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null Any status, with committee and position added, for staff. */
    public function findDetailed(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT v.*, c.name AS committee_name, p.title AS position_title
             FROM vacancies v
             JOIN positions p ON p.position_id = v.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE v.vacancy_id = ?"
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null Must run inside a transaction. */
    public function lockAndFind(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM vacancies WHERE vacancy_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null The request's vacancy. A cancelled one does not count, so a new one can replace it. */
    public function liveForRequest(int $requestId): ?array
    {
        $statement = $this->pdo()->prepare("SELECT * FROM vacancies WHERE request_id = ? AND status <> 'cancelled' ORDER BY vacancy_id DESC LIMIT 1");
        $statement->execute([$requestId]);

        return $statement->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> Published vacancies of the committees this user heads, newest first. */
    public function forCommitteesHeadedBy(int $userId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT v.*, c.name AS committee_name
             FROM vacancies v
             JOIN positions p ON p.position_id = v.position_id
             JOIN committees c ON c.committee_id = p.committee_id
             WHERE c.head_user_id = ? AND v.status <> 'draft'
             ORDER BY v.vacancy_id DESC"
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /** @return list<int> Open vacancies past their closing date or with every slot filled. */
    public function dueIds(string $now): array
    {
        $statement = $this->pdo()->prepare("SELECT vacancy_id FROM vacancies WHERE status = 'open' AND (closes_at <= ? OR slots_filled >= slots) ORDER BY vacancy_id");
        $statement->execute([$now]);

        return array_map(intval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @param array{status?: string, q?: string} $filters
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function search(array $filters, int $page, int $perPage = 20): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (isset($filters['status'])) {
            $conditions[] = 'v.status = ?';
            $params[] = $filters['status'];
        }

        if (isset($filters['q'])) {
            $like = '%' . strtr($filters['q'], ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
            $conditions[] = "(v.title LIKE ? ESCAPE '|' OR c.name LIKE ? ESCAPE '|')";
            array_push($params, $like, $like);
        }

        $where = implode(' AND ', $conditions);
        $from = 'FROM vacancies v JOIN positions p ON p.position_id = v.position_id LEFT JOIN committees c ON c.committee_id = p.committee_id';
        $count = $this->pdo()->prepare("SELECT COUNT(*) {$from} WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers.
        $rows = $this->pdo()->prepare(
            "SELECT v.*, c.name AS committee_name,
                    (SELECT COUNT(*) FROM membership_applications a WHERE a.vacancy_id = v.vacancy_id AND a.status <> 'draft') AS application_count
             {$from} WHERE {$where} ORDER BY v.vacancy_id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }
}
