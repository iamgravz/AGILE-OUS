<?php

declare(strict_types=1);

namespace App\Models;

final class EligibilityDocumentRepository extends BaseRepository
{
    protected string $table = 'eligibility_documents';
    protected string $primaryKey = 'document_id';

    /** @return list<array<string, mixed>> */
    public function forScreening(int $screeningId): array
    {
        return $this->findBy(['screening_id' => $screeningId]);
    }

    public function deleteForScreening(int $screeningId): void
    {
        $this->pdo()->prepare('DELETE FROM eligibility_documents WHERE screening_id = ?')->execute([$screeningId]);
    }

    /** @return array<string, mixed>|null With the owner's user id and role. */
    public function findWithOwner(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT d.*, m.user_id AS owner_id, r.slug AS owner_role
             FROM eligibility_documents d
             JOIN eligibility_screenings s ON s.screening_id = d.screening_id
             JOIN members m ON m.member_id = s.member_id
             JOIN users u ON u.user_id = m.user_id
             JOIN roles r ON r.role_id = u.role_id
             WHERE d.document_id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }
}
