<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * The semester archive (Article III Section 6): files are archived, never deleted. The COR and copy of grades of a decided
 * application, and of a closed candidacy, move to storage/uploads/archive/<academic year>-<semester>/ once
 * config/retention.php's days have passed since the decision. Records stay, the files still open from the same pages,
 * and nothing is ever deleted: a file is renamed into the archive, its row updated, and moved back if the update fails.
 */
final class DocumentArchiveService
{
    private const ARCHIVE_PREFIX = 'archive/';

    /**
     * Every file due for the archive.
     *
     * @return list<array{table: string, key: string, id: int, column: string, stored_path: string, decided_at: string, size: int, folder: string}>
     */
    public function due(): array
    {
        authorize('backup.manage');

        $days = (int) config('retention.archive_after_days');
        $types = array_values(config('retention.document_types'));
        $marks = implode(',', array_fill(0, count($types), '?'));
        $db = Database::connection();
        $files = [];

        $documents = $db->prepare(
            "SELECT d.document_id AS id, d.stored_path, d.size_bytes, COALESCE(a.decided_at, a.updated_at) AS decided_at
             FROM application_documents d
             JOIN membership_applications a ON a.application_id = d.application_id
             WHERE a.status IN ('approved', 'rejected', 'withdrawn') AND d.doc_type IN ({$marks})
               AND COALESCE(a.decided_at, a.updated_at) < NOW() - INTERVAL ? DAY AND d.stored_path NOT LIKE 'archive/%'
             ORDER BY decided_at"
        );
        $documents->execute([...$types, $days]);

        foreach ($documents->fetchAll() as $row) {
            $files[] = $this->entry('application_documents', 'document_id', (int) $row['id'], 'stored_path', $row, (int) $row['size_bytes']);
        }

        // Earlier versions of those documents (document_versions) go with them.
        $versions = $db->prepare(
            "SELECT v.version_id AS id, v.stored_path, v.size_bytes, COALESCE(a.decided_at, a.updated_at) AS decided_at
             FROM document_versions v
             JOIN application_documents d ON d.document_id = v.document_id
             JOIN membership_applications a ON a.application_id = d.application_id
             WHERE a.status IN ('approved', 'rejected', 'withdrawn') AND d.doc_type IN ({$marks})
               AND COALESCE(a.decided_at, a.updated_at) < NOW() - INTERVAL ? DAY AND v.stored_path NOT LIKE 'archive/%'
             ORDER BY decided_at"
        );
        $versions->execute([...$types, $days]);

        foreach ($versions->fetchAll() as $row) {
            $files[] = $this->entry('document_versions', 'version_id', (int) $row['id'], 'stored_path', $row, (int) $row['size_bytes']);
        }

        $candidacies = $db->prepare(
            "SELECT candidacy_id AS id, cor_path, grades_path, COALESCE(decided_at, updated_at) AS decided_at
             FROM election_candidacies
             WHERE status IN ('disqualified', 'withdrawn', 'elected') AND COALESCE(decided_at, updated_at) < NOW() - INTERVAL ? DAY
             ORDER BY decided_at"
        );
        $candidacies->execute([$days]);

        foreach ($candidacies->fetchAll() as $row) {
            foreach (['cor_path', 'grades_path'] as $column) {
                if (!str_starts_with((string) $row[$column], self::ARCHIVE_PREFIX)) {
                    $files[] = $this->entry('election_candidacies', 'candidacy_id', (int) $row['id'], $column, ['stored_path' => $row[$column]] + $row, null);
                }
            }
        }

        return $files;
    }

    /**
     * What the archive page shows: how many files and how much space, per semester folder.
     *
     * @return array{total: int, bytes: int, folders: array<string, array{count: int, bytes: int}>, archived: int}
     */
    public function summary(): array
    {
        $due = $this->due();
        $folders = [];

        foreach ($due as $file) {
            $folders[$file['folder']] ??= ['count' => 0, 'bytes' => 0];
            $folders[$file['folder']]['count']++;
            $folders[$file['folder']]['bytes'] += $file['size'];
        }

        ksort($folders);
        $archived = (int) Database::connection()->query("SELECT COUNT(*) FROM application_documents WHERE stored_path LIKE 'archive/%'")->fetchColumn()
            + (int) Database::connection()->query("SELECT COUNT(*) FROM election_candidacies WHERE cor_path LIKE 'archive/%'")->fetchColumn()
            + (int) Database::connection()->query("SELECT COUNT(*) FROM election_candidacies WHERE grades_path LIKE 'archive/%'")->fetchColumn();

        return ['total' => count($due), 'bytes' => array_sum(array_column($due, 'size')), 'folders' => $folders, 'archived' => $archived];
    }

    /**
     * Moves every due file into its semester folder. A file that is missing or already has a twin in the archive is
     * left alone and counted as skipped.
     *
     * @return array{moved: int, skipped: int}
     */
    public function archive(): array
    {
        authorize('backup.manage');

        $storage = new FileStorageService();
        $moved = 0;
        $skipped = 0;

        foreach ($this->due() as $file) {
            $target = self::ARCHIVE_PREFIX . $file['folder'] . '/' . $file['stored_path'];

            try {
                $from = $storage->path($file['stored_path']);
                $to = $storage->path($target);
            } catch (Throwable) {
                $skipped++;
                continue;
            }

            if (!is_file($from) || file_exists($to)) {
                $skipped++;
                continue;
            }

            if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0775, true) && !is_dir(dirname($to))) {
                $skipped++;
                continue;
            }

            if (!rename($from, $to)) {
                $skipped++;
                continue;
            }

            try {
                Database::connection()->prepare("UPDATE {$file['table']} SET {$file['column']} = ? WHERE {$file['key']} = ? AND {$file['column']} = ?")
                    ->execute([$target, $file['id'], $file['stored_path']]);
                $moved++;
            } catch (Throwable) {
                // The row still points at the old place, so the file goes back there.
                rename($to, $from);
                $skipped++;
            }
        }

        AuditService::log('documents.archived', null, null, ['moved' => $moved, 'skipped' => $skipped]);

        return ['moved' => $moved, 'skipped' => $skipped];
    }

    /** The semester a date falls in: August to December is the 1st, January to May the 2nd, June and July summer. */
    public static function folderFor(string $date): string
    {
        $time = strtotime($date) ?: time();
        $year = (int) date('Y', $time);
        $month = (int) date('n', $time);

        return match (true) {
            $month >= 8 => sprintf('%d-%d-1st', $year, $year + 1),
            $month <= 5 => sprintf('%d-%d-2nd', $year - 1, $year),
            default => sprintf('%d-%d-summer', $year - 1, $year),
        };
    }

    /** @param array<string, mixed> $row */
    private function entry(string $table, string $key, int $id, string $column, array $row, ?int $size): array
    {
        $path = (string) $row['stored_path'];

        if ($size === null) {
            try {
                $absolute = (new FileStorageService())->path($path);
                $size = is_file($absolute) ? (int) filesize($absolute) : 0;
            } catch (Throwable) {
                $size = 0;
            }
        }

        return ['table' => $table, 'key' => $key, 'id' => $id, 'column' => $column, 'stored_path' => $path, 'decided_at' => (string) $row['decided_at'], 'size' => $size, 'folder' => self::folderFor((string) $row['decided_at'])];
    }
}
