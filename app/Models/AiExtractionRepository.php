<?php

declare(strict_types=1);

namespace App\Models;

final class AiExtractionRepository extends BaseRepository
{
    protected string $table = 'ai_extractions';
    protected string $primaryKey = 'extraction_id';

    /** @return array<string, mixed>|null */
    public function latestSuggested(string $sourceType, int $sourceId): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT * FROM ai_extractions WHERE source_type = ? AND source_id = ? AND status = 'suggested' ORDER BY extraction_id DESC LIMIT 1"
        );
        $statement->execute([$sourceType, $sourceId]);

        return $statement->fetch() ?: null;
    }

    /** Asking again replaces the earlier suggestion. */
    public function discardSuggested(string $sourceType, int $sourceId, int $reviewerId): void
    {
        $this->pdo()->prepare(
            "UPDATE ai_extractions SET status = 'discarded', verified_by = ?, verified_at = ? WHERE source_type = ? AND source_id = ? AND status = 'suggested'"
        )->execute([$reviewerId, date('Y-m-d H:i:s'), $sourceType, $sourceId]);
    }
}
