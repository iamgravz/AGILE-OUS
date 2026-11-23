<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ApplicationDocumentRepository;

/**
 * The files of an application: adding, replacing with version history, removing, and taking over the files uploaded
 * on the first apply page. MembershipService decides whether the application may still change and whether a document
 * is asked for; staff verification stays there too, because it moves the application along.
 */
final class ApplicationDocumentService
{
    public function __construct(
        private readonly ApplicationDocumentRepository $documents = new ApplicationDocumentRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
    ) {
    }

    /**
     * @param array<string, mixed> $application
     * @param array<string, mixed> $file an upload from $_FILES
     * @return array<string, mixed> The saved document.
     * @throws FileRejectedException
     */
    public function add(array $application, string $docType, array $file): array
    {
        $stored = $this->files->store($file, 'applications');
        $documentId = $this->documents->insert($stored + ['application_id' => $application['application_id'], 'doc_type' => $docType]);
        AuditService::log('application.document_uploaded', 'application_document', $documentId, ['doc_type' => $docType]);

        return $stored + ['document_id' => $documentId, 'doc_type' => $docType];
    }

    /**
     * One copy per type. A new upload becomes the next version: the earlier file and its review are kept in
     * document_versions, and a needs_correction document returns to pending. A verified one cannot be replaced.
     *
     * @param array<string, mixed> $application
     * @param array<string, mixed> $file
     * @return array<string, mixed> The saved document.
     * @throws FileRejectedException
     */
    public function replace(array $application, string $docType, array $file): array
    {
        $existing = null;

        foreach ($this->documents->forApplication((int) $application['application_id']) as $document) {
            if ($document['doc_type'] === $docType && $document['verification_status'] !== 'rejected') {
                $existing = $document;
            }
        }

        if ($existing === null) {
            return $this->add($application, $docType, $file);
        }

        $status = $existing['verification_status'];

        if ($status === 'verified') {
            throw new FileRejectedException('This document is already verified, so it cannot be replaced.');
        }

        if ($status === 'needs_correction') {
            DocumentStatus::assertTransition($status, 'pending');
        }

        $stored = $this->files->store($file, 'applications');
        $version = (int) ($existing['version_no'] ?? 1) + 1;
        $values = $stored + ['version_no' => $version, 'verification_status' => 'pending', 'verification_remarks' => null, 'verified_by' => null, 'verified_at' => null];
        $this->documents->keepVersion($existing);
        $this->documents->update((int) $existing['document_id'], $values);
        AuditService::log('application.document_replaced', 'application_document', (int) $existing['document_id'], ['doc_type' => $docType, 'from' => $status, 'to' => 'pending', 'version' => $version]);

        return $values + ['document_id' => $existing['document_id'], 'doc_type' => $docType];
    }

    /**
     * Removes a document with its earlier versions and their files.
     *
     * @param array<string, mixed> $document
     */
    public function remove(array $document): void
    {
        $documentId = (int) $document['document_id'];

        foreach ($this->documents->versions($documentId) as $version) {
            $this->files->delete($version['stored_path']);
        }

        Database::connection()->prepare('DELETE FROM document_versions WHERE document_id = ?')->execute([$documentId]);
        $this->documents->delete($documentId);
        $this->files->delete($document['stored_path']);
        AuditService::log('application.document_removed', 'application_document', $documentId, ['doc_type' => $document['doc_type']]);
    }

    /**
     * The COR and grades uploaded on the first apply page, before the email was confirmed, join the new draft. Each
     * was checked and saved then (FileStorageService::store); a type the draft already has is left alone.
     *
     * @param array<string, mixed> $application
     * @param array<string, mixed> $documents doc_type => stored file, read back from the session
     */
    public function attachFirstPage(array $application, array $documents): void
    {
        $existing = array_column($this->documents->forApplication((int) $application['application_id']), 'doc_type');

        foreach ($documents as $type => $stored) {
            if (!in_array($type, ['cor', 'grades'], true) || in_array($type, $existing, true) || !is_array($stored) || !isset($stored['stored_path'])) {
                continue;
            }

            $documentId = $this->documents->insert([
                'application_id' => $application['application_id'],
                'doc_type' => $type,
                'stored_path' => (string) $stored['stored_path'],
                'original_name' => (string) $stored['original_name'],
                'mime_type' => (string) $stored['mime_type'],
                'size_bytes' => (int) $stored['size_bytes'],
            ]);
            AuditService::log('application.document_uploaded', 'application_document', $documentId, ['doc_type' => $type, 'from' => 'apply page']);
        }
    }
}
