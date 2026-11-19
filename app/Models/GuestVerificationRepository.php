<?php

declare(strict_types=1);

namespace App\Models;

final class GuestVerificationRepository extends BaseRepository
{
    protected string $table = 'guest_verifications';
    protected string $primaryKey = 'verification_id';

    /** @return array<string, mixed>|null The email's newest code. Must run inside a transaction. */
    public function lockLatest(string $email, string $purpose): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM guest_verifications WHERE email = ? AND purpose = ? ORDER BY verification_id DESC LIMIT 1 FOR UPDATE'
        );
        $statement->execute([$email, $purpose]);

        return $statement->fetch() ?: null;
    }
}
