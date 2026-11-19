<?php

declare(strict_types=1);

namespace App\Models;

final class ApplicationDocumentRepository extends BaseRepository
{
    protected string $table = 'application_documents';
    protected string $primaryKey = 'document_id';

    /** @return list<array<string, mixed>> */
    public function forApplication(int $applicationId): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM application_documents WHERE application_id = ? ORDER BY document_id');
        $statement->execute([$applicationId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null The document with its application's owner added. */
    public function findWithOwner(int $documentId): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT d.*, COALESCE(a.user_id, ap.user_id) AS owner_id
             FROM application_documents d
             JOIN membership_applications a ON a.application_id = d.application_id
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             WHERE d.document_id = ?'
        );
        $statement->execute([$documentId]);

        return $statement->fetch() ?: null;
    }
}
