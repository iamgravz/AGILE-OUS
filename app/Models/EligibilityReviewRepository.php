<?php

declare(strict_types=1);

namespace App\Models;

final class EligibilityReviewRepository extends BaseRepository
{
    protected string $table = 'eligibility_reviews';
    protected string $primaryKey = 'review_id';

    /** @return array<string, mixed>|null */
    public function forScreening(int $screeningId): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS decided_by_name
             FROM eligibility_reviews r
             JOIN users u ON u.user_id = r.decided_by
             WHERE r.screening_id = ?"
        );
        $statement->execute([$screeningId]);

        return $statement->fetch() ?: null;
    }
}
