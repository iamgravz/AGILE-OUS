<?php

declare(strict_types=1);

namespace App\Models;

final class TokenRepository extends BaseRepository
{
    protected string $table = 'user_tokens';
    protected string $primaryKey = 'token_id';

    /** @return array<string, mixed>|null An unused, unexpired token. */
    public function findUsable(string $tokenHash, string $type): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM user_tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > ? LIMIT 1'
        );
        $statement->execute([$tokenHash, $type, date('Y-m-d H:i:s')]);

        return $statement->fetch() ?: null;
    }

    /** True for the one caller that used the token, so a link cannot work twice even at the same moment. */
    public function consume(int $tokenId): bool
    {
        $statement = $this->pdo()->prepare('UPDATE user_tokens SET used_at = ? WHERE token_id = ? AND used_at IS NULL');
        $statement->execute([date('Y-m-d H:i:s'), $tokenId]);

        return $statement->rowCount() === 1;
    }

    public function revokeAll(int $userId, string $type): void
    {
        $statement = $this->pdo()->prepare('UPDATE user_tokens SET used_at = ? WHERE user_id = ? AND type = ? AND used_at IS NULL');
        $statement->execute([date('Y-m-d H:i:s'), $userId, $type]);
    }
}
