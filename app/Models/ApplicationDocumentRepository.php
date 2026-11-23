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

    /** @return array<int, list<array<string, mixed>>> document_id => its earlier versions, newest first. */
    public function versionsForApplication(int $applicationId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT v.* FROM document_versions v JOIN application_documents d ON d.document_id = v.document_id
             WHERE d.application_id = ? ORDER BY v.document_id, v.version_no DESC'
        );
        $statement->execute([$applicationId]);
        $grouped = [];

        foreach ($statement->fetchAll() as $row) {
            $grouped[(int) $row['document_id']][] = $row;
        }

        return $grouped;
    }

    /** Keeps the current file of a document as an earlier version, before a new upload replaces it. */
    public function keepVersion(array $document): void
    {
        $this->pdo()->prepare(
            'INSERT INTO document_versions (document_id, version_no, original_name, stored_path, mime_type, size_bytes, verification_status, verification_remarks, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([(int) $document['document_id'], (int) ($document['version_no'] ?? 1), $document['original_name'], $document['stored_path'], $document['mime_type'],
            (int) $document['size_bytes'], $document['verification_status'], $document['verification_remarks'], $document['updated_at'] ?? $document['created_at']]);
    }

    /** @return list<array<string, mixed>> The earlier versions of one document. */
    public function versions(int $documentId): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM document_versions WHERE document_id = ? ORDER BY version_no DESC');
        $statement->execute([$documentId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null An earlier version with its document's application and owner. */
    public function findVersion(int $versionId): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT v.*, d.application_id, COALESCE(a.user_id, ap.user_id) AS owner_id
             FROM document_versions v
             JOIN application_documents d ON d.document_id = v.document_id
             JOIN membership_applications a ON a.application_id = d.application_id
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             WHERE v.version_id = ?'
        );
        $statement->execute([$versionId]);

        return $statement->fetch() ?: null;
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
