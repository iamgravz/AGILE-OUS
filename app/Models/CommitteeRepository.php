<?php

declare(strict_types=1);

namespace App\Models;

final class CommitteeRepository extends BaseRepository
{
    protected string $table = 'committees';
    protected string $primaryKey = 'committee_id';

    /** @return list<array<string, mixed>> */
    public function allByName(): array
    {
        return $this->pdo()->query('SELECT name, description FROM committees ORDER BY name')->fetchAll();
    }

    /** @return list<array<string, mixed>> For filter lists. */
    public function options(): array
    {
        return $this->pdo()->query('SELECT committee_id, name FROM committees ORDER BY name')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function withHeads(): array
    {
        return $this->pdo()->query(
            "SELECT c.committee_id, c.name, CONCAT(u.first_name, ' ', u.last_name) AS head_name,
                    (SELECT COUNT(*) FROM positions p WHERE p.committee_id = c.committee_id) AS position_count
             FROM committees c LEFT JOIN users u ON u.user_id = c.head_user_id
             ORDER BY c.name"
        )->fetchAll();
    }

    /** Positions and HR requests that point at this committee. */
    public function usageCount(int $id): int
    {
        $statement = $this->pdo()->prepare('SELECT (SELECT COUNT(*) FROM positions WHERE committee_id = ?) + (SELECT COUNT(*) FROM hr_requests WHERE committee_id = ?)');
        $statement->execute([$id, $id]);

        return (int) $statement->fetchColumn();
    }

    public function clearHead(int $userId): void
    {
        $this->pdo()->prepare('UPDATE committees SET head_user_id = NULL WHERE head_user_id = ?')->execute([$userId]);
    }
}
