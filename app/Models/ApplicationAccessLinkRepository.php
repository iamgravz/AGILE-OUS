<?php

declare(strict_types=1);

namespace App\Models;

final class ApplicationAccessLinkRepository extends BaseRepository
{
    protected string $table = 'application_access_links';
    protected string $primaryKey = 'link_id';

    /** @return array<string, mixed>|null An unused, unexpired link, with its application's owner and status. */
    public function findUsable(string $tokenHash): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT l.*, a.applicant_id, a.status AS application_status
             FROM application_access_links l
             JOIN membership_applications a ON a.application_id = l.application_id
             WHERE l.token_hash = ? AND l.used_at IS NULL AND l.expires_at > ?
             LIMIT 1'
        );
        $statement->execute([$tokenHash, date('Y-m-d H:i:s')]);

        return $statement->fetch() ?: null;
    }

    public function hasUsable(int $applicationId, string $purpose): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM application_access_links WHERE application_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > ?'
        );
        $statement->execute([$applicationId, $purpose, date('Y-m-d H:i:s')]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function issuedSince(int $applicationId, string $purpose, string $since): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM application_access_links WHERE application_id = ? AND purpose = ? AND created_at >= ?');
        $statement->execute([$applicationId, $purpose, $since]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** True for the one caller that used the link, so it cannot work twice even at the same moment. */
    public function consume(int $linkId): bool
    {
        $statement = $this->pdo()->prepare('UPDATE application_access_links SET used_at = ? WHERE link_id = ? AND used_at IS NULL');
        $statement->execute([date('Y-m-d H:i:s'), $linkId]);

        return $statement->rowCount() === 1;
    }

    public function revokeAll(int $applicationId, string $purpose): void
    {
        $statement = $this->pdo()->prepare('UPDATE application_access_links SET used_at = ? WHERE application_id = ? AND purpose = ? AND used_at IS NULL');
        $statement->execute([date('Y-m-d H:i:s'), $applicationId, $purpose]);
    }
}
