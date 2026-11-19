<?php

declare(strict_types=1);

namespace App\Models;

final class CredentialRepository extends BaseRepository
{
    protected string $table = 'digital_credentials';
    protected string $primaryKey = 'credential_id';

    /** @return array<string, mixed>|null The member's valid credential of this type. */
    public function findActive(int $memberId, string $type): ?array
    {
        return $this->findBy(['member_id' => $memberId, 'type' => $type, 'revoked_at' => null])[0] ?? null;
    }

    /** @return array<string, mixed>|null Holds the row until the transaction ends. */
    public function findForUpdate(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM digital_credentials WHERE credential_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public function pendingCount(): int
    {
        return (int) $this->pdo()->query("SELECT COUNT(*) FROM digital_credentials WHERE status = 'pending_approval' AND revoked_at IS NULL")->fetchColumn();
    }

    /** @return list<int> */
    public function pendingIds(): array
    {
        return array_map('intval', $this->pdo()->query("SELECT credential_id FROM digital_credentials WHERE status = 'pending_approval' AND revoked_at IS NULL ORDER BY credential_id")->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @param array{type?: string, reason?: string} $filters Both come from fixed lists.
     * @return list<array<string, mixed>> Oldest first, with the member's name.
     */
    public function forReview(string $status, array $filters = []): array
    {
        $conditions = ['c.status = ?', 'c.revoked_at IS NULL'];
        $params = [$status];

        foreach (['type', 'reason'] as $column) {
            if (isset($filters[$column])) {
                $conditions[] = "c.{$column} = ?";
                $params[] = $filters[$column];
            }
        }

        $statement = $this->pdo()->prepare(
            "SELECT c.credential_id, c.type, c.reason, c.issued_at, c.review_remarks, m.member_no,
                    CONCAT(COALESCE(u.first_name, ap.first_name), ' ', COALESCE(u.last_name, ap.last_name)) AS member_name
             FROM digital_credentials c
             JOIN members m ON m.member_id = c.member_id
             LEFT JOIN users u ON u.user_id = m.user_id
             LEFT JOIN applicants ap ON ap.applicant_id = m.applicant_id
             WHERE " . implode(' AND ', $conditions) . '
             ORDER BY c.issued_at, c.credential_id'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByToken(string $token): ?array
    {
        return $this->findBy(['qr_token' => $token])[0] ?? null;
    }

    /** @return array<string, mixed>|null The credential with its owner's user id added. */
    public function findWithOwner(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT c.*, m.user_id AS owner_id, m.member_no
             FROM digital_credentials c
             JOIN members m ON m.member_id = c.member_id
             WHERE c.credential_id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> Newest first, including revoked ones. */
    public function forMember(int $memberId): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM digital_credentials WHERE member_id = ? ORDER BY issued_at DESC, credential_id DESC');
        $statement->execute([$memberId]);

        return $statement->fetchAll();
    }

    public function countForMember(int $memberId, string $type): int
    {
        return count($this->findBy(['member_id' => $memberId, 'type' => $type]));
    }

    /** @return list<string> Serial numbers of the credentials just revoked. */
    public function revokeActive(int $memberId): array
    {
        $active = $this->findBy(['member_id' => $memberId, 'revoked_at' => null]);

        $this->pdo()->prepare('UPDATE digital_credentials SET revoked_at = ? WHERE member_id = ? AND revoked_at IS NULL')
            ->execute([date('Y-m-d H:i:s'), $memberId]);

        return array_map(static fn (array $credential): string => $credential['serial_no'], $active);
    }
}
