<?php

declare(strict_types=1);

namespace App\Models;

final class CaseNoteRepository extends BaseRepository
{
    protected string $table = 'case_notes';
    protected string $primaryKey = 'note_id';

    /** @return list<array<string, mixed>> Newest first. Notes are still ciphertext. */
    public function forCase(int $caseId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT n.note_id, n.note, n.visible_to_student, n.created_at, CONCAT(u.first_name, ' ', u.last_name) AS author_name
             FROM case_notes n
             JOIN users u ON u.user_id = n.author_id
             WHERE n.case_id = ?
             ORDER BY n.note_id DESC"
        );
        $statement->execute([$caseId]);

        return $statement->fetchAll();
    }
}
