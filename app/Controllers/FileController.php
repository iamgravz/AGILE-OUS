<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\FileAccessPolicy;
use App\Services\FileStorageService;

/** The only way a stored file reaches a browser. */
final class FileController
{
    public function show(Request $request, string $type, string $id): Response
    {
        if (!in_array($type, FileAccessPolicy::TYPES, true) || !ctype_digit($id)) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->send($request, $type, (int) $id, (new FileAccessPolicy())->$type((int) $id));
    }

    /** An applicant without an account opening their own upload from the wizard (BR-18). */
    public function applicantDocument(Request $request, string $id): Response
    {
        return ctype_digit($id)
            ? $this->send($request, 'application_document', (int) $id, (new FileAccessPolicy())->applicantDocument((int) $id))
            : Response::error(Response::HTTP_NOT_FOUND);
    }

    /** @param array<string, mixed>|null $record */
    private function send(Request $request, string $type, int $id, ?array $record): Response
    {
        if ($record === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        if ($record['log']) {
            AuditService::log('file.accessed', $type, $id);
        }

        // Welfare files are encrypted on disk, so they arrive here already decrypted.
        if (isset($record['contents'])) {
            return (new FileStorageService())->streamContents($record['contents'], $record['mime_type'], $record['download_name']);
        }

        // Staff checking documents view them in a frame on the same page instead of downloading each one.
        $mayPreview = ($type === 'application_document' && can('document.verify')) || ($record['previewable'] ?? false);
        $preview = $request->queryText('inline') === '1' && $mayPreview;

        // Credentials live in their own folder, outside storage/uploads.
        if (isset($record['absolute_path'])) {
            return (new FileStorageService())->streamPath($record['absolute_path'], $record['download_name'], $preview, $preview);
        }

        return (new FileStorageService())->stream($record['stored_path'], $record['download_name'], $record['inline'] || $preview, $preview);
    }
}
