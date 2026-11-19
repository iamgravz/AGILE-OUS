<?php

declare(strict_types=1);

namespace App\Models;

final class WelfareAttachmentRepository extends BaseRepository
{
    protected string $table = 'welfare_attachments';
    protected string $primaryKey = 'attachment_id';

    /** @return list<array<string, mixed>> */
    public function forCase(int $caseId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT a.attachment_id, a.original_name, a.size_bytes, a.disposition, a.disposition_at, CONCAT(u.first_name, ' ', u.last_name) AS disposition_by_name
             FROM welfare_attachments a
             LEFT JOIN users u ON u.user_id = a.disposition_by
             WHERE a.case_id = ?
             ORDER BY a.attachment_id"
        );
        $statement->execute([$caseId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null With owner_id, which is null for a guest case. */
    public function findWithOwner(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT a.*, c.user_id AS owner_id
             FROM welfare_attachments a
             JOIN welfare_cases c ON c.case_id = a.case_id
             WHERE a.attachment_id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }
}
