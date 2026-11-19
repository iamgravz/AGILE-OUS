<?php

declare(strict_types=1);

namespace App\Models;

final class PositionRepository extends BaseRepository
{
    protected string $table = 'positions';
    protected string $primaryKey = 'position_id';

    /** @return array<string, mixed>|null The position with committee_name and head_user_id added. */
    public function findWithCommittee(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT p.*, c.name AS committee_name, c.head_user_id
             FROM positions p
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE p.position_id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    private const USAGE = '(SELECT COUNT(*) FROM members m WHERE m.position_id = p.position_id)
                         + (SELECT COUNT(*) FROM hr_requests h WHERE h.position_id = p.position_id)
                         + (SELECT COUNT(*) FROM vacancies v WHERE v.position_id = p.position_id)
                         + (SELECT COUNT(*) FROM appointments a WHERE a.position_id = p.position_id)';

    /** @return list<array<string, mixed>> */
    public function withUsage(): array
    {
        return $this->pdo()->query(
            'SELECT p.position_id, p.title, p.role_type, c.name AS committee_name, ' . self::USAGE . ' AS usage_count
             FROM positions p LEFT JOIN committees c ON c.committee_id = p.committee_id
             ORDER BY c.name, p.title'
        )->fetchAll();
    }

    public function usageCount(int $id): int
    {
        $statement = $this->pdo()->prepare('SELECT ' . self::USAGE . ' FROM positions p WHERE p.position_id = ?');
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function headOf(int $committeeId): ?array
    {
        return $this->findBy(['committee_id' => $committeeId, 'role_type' => 'committee_head'])[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function forCommitteesHeadedBy(int $userId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT p.position_id, p.title, c.name AS committee_name
             FROM positions p
             JOIN committees c ON c.committee_id = p.committee_id
             WHERE c.head_user_id = ?
             ORDER BY c.name, p.position_id'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }
}
