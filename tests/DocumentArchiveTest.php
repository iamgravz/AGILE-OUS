<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\UserRepository;
use App\Services\DocumentArchiveService;
use App\Services\FileStorageService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** The semester archive: decided COR and grades move into archive/<year>-<semester>/, and nothing is deleted. */
final class DocumentArchiveTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testSemesterFolders(): void
    {
        $this->assertSame('2026-2027-1st', DocumentArchiveService::folderFor('2026-09-15 10:00:00'));
        $this->assertSame('2025-2026-2nd', DocumentArchiveService::folderFor('2026-03-01'));
        $this->assertSame('2025-2026-summer', DocumentArchiveService::folderFor('2026-07-01'));
    }

    public function testDecidedDocumentsMoveAndRecentOnesStay(): void
    {
        $sentinel = new RuntimeException('Roll back the test.');

        try {
            Database::transaction(function () use ($sentinel): void {
                $_SESSION['user_id'] = (int) (new UserRepository())->findByEmail('mswhead@agile.test')['user_id'];
                RbacService::clearCache();
                $db = Database::connection();
                $storage = new FileStorageService();

                [$old, $oldPath] = $this->document($db, 'approved', '-200 days');
                [$recent, $recentPath] = $this->document($db, 'rejected', '-10 days');
                [$pending, $pendingPath] = $this->document($db, 'submitted', '-200 days');

                foreach ([$oldPath, $recentPath, $pendingPath] as $path) {
                    @mkdir(dirname($storage->path($path)), 0775, true);
                    file_put_contents($storage->path($path), "%PDF-1.4\n% {$path}\n");
                }

                $service = new DocumentArchiveService();
                $due = array_column(array_filter($service->due(), static fn (array $file): bool => $file['table'] === 'application_documents'), 'id');
                $this->assertContains($old, $due);
                $this->assertNotContains($recent, $due, 'Decided 10 days ago: not yet.');
                $this->assertNotContains($pending, $due, 'Not decided: never.');

                $result = $service->archive();
                $this->assertGreaterThanOrEqual(1, $result['moved']);

                $newPath = (string) $db->query("SELECT stored_path FROM application_documents WHERE document_id = {$old}")->fetchColumn();
                $this->assertStringStartsWith('archive/', $newPath);
                $this->assertFileExists($storage->path($newPath));
                $this->assertStringContainsString($oldPath, (string) file_get_contents($storage->path($newPath)), 'The same file, moved.');
                $this->assertFileDoesNotExist($storage->path($oldPath));
                $this->assertFileExists($storage->path($recentPath));
                $this->assertFileExists($storage->path($pendingPath));
                $this->assertNotContains($old, array_column($service->due(), 'id'), 'Archived once.');

                throw $sentinel;
            });
        } catch (RuntimeException $exception) {
            if ($exception !== $sentinel) {
                throw $exception;
            }
        }
    }

    public function testOnlyTheMswHeadArchives(): void
    {
        $_SESSION['user_id'] = (int) (new UserRepository())->findByEmail('mswmember@agile.test')['user_id'];
        RbacService::clearCache();
        $this->expectException(ForbiddenException::class);
        (new DocumentArchiveService())->archive();
    }

    /** @return array{0: int, 1: string} A COR on a test application with this status, decided at that time. */
    private function document(PDO $db, string $status, string $decided): array
    {
        $owner = (int) (new UserRepository())->findByEmail('nomember@agile.test')['user_id'];
        $db->prepare('INSERT INTO membership_applications (tracking_code, user_id, status, decided_at) VALUES (?, ?, ?, ?)')
            ->execute([strtoupper(bin2hex(random_bytes(6))), $owner, $status, $status === 'submitted' ? null : date('Y-m-d H:i:s', strtotime($decided))]);
        $id = (int) $db->lastInsertId();
        $this->assertGreaterThan(0, $id);
        // Not decided yet: the last change is old too, so only the status keeps it out.
        $db->prepare('UPDATE membership_applications SET updated_at = ? WHERE application_id = ?')->execute([date('Y-m-d H:i:s', strtotime($decided)), $id]);
        $path = 'applications/' . bin2hex(random_bytes(16)) . '.pdf';
        $db->prepare("INSERT INTO application_documents (application_id, doc_type, original_name, stored_path, mime_type, size_bytes) VALUES (?, 'cor', 'cor.pdf', ?, 'application/pdf', 20)")
            ->execute([$id, $path]);

        return [(int) $db->lastInsertId(), $path];
    }
}
