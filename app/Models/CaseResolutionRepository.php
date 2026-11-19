<?php

declare(strict_types=1);

namespace App\Models;

final class CaseResolutionRepository extends BaseRepository
{
    protected string $table = 'case_resolutions';
    protected string $primaryKey = 'resolution_id';

    /** @return array<string, mixed>|null Its text is still ciphertext. */
    public function forCase(int $caseId): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS resolved_by_name
             FROM case_resolutions r
             JOIN users u ON u.user_id = r.resolved_by
             WHERE r.case_id = ?"
        );
        $statement->execute([$caseId]);

        return $statement->fetch() ?: null;
    }
}
