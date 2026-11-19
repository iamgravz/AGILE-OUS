<?php

declare(strict_types=1);

namespace App\Models;

final class ReferralRepository extends BaseRepository
{
    protected string $table = 'referrals';
    protected string $primaryKey = 'referral_id';

    /** @return list<array<string, mixed>> Newest first. Remarks are still ciphertext. */
    public function forCase(int $caseId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT r.referral_id, r.referred_to, r.remarks, r.status, r.referred_at, CONCAT(u.first_name, ' ', u.last_name) AS referred_by_name
             FROM referrals r
             JOIN users u ON u.user_id = r.referred_by
             WHERE r.case_id = ?
             ORDER BY r.referral_id DESC"
        );
        $statement->execute([$caseId]);

        return $statement->fetchAll();
    }
}
