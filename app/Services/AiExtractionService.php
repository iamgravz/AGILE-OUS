<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\AiExtractionRepository;
use App\Models\EligibilityDocumentRepository;
use App\Models\EligibilityScreeningRepository;
use App\Services\Ai\ExtractionResult;
use App\Services\Ai\ExtractorFactory;
use App\Services\Ai\ExtractorInterface;
use DomainException;

/**
 * Optional suggestions on the eligibility review (BR-11). They are saved only in ai_extractions. Nothing here writes
 * to a screening: its answers and result come only from what the reviewer submits on the normal form.
 */
final class AiExtractionService
{
    private const SOURCE = 'eligibility_screening';
    private const TURNED_OFF = 'AI suggestions are turned off. Enter values manually.';
    private const FAILED = 'We could not get suggestions right now. Please enter the values manually.';

    private readonly ExtractorInterface $extractor;

    public function __construct(
        ?ExtractorInterface $extractor = null,
        private readonly AiExtractionRepository $extractions = new AiExtractionRepository(),
        private readonly EligibilityScreeningRepository $screenings = new EligibilityScreeningRepository(),
        private readonly EligibilityDocumentRepository $documents = new EligibilityDocumentRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
    ) {
        $this->extractor = $extractor ?? ExtractorFactory::make();
    }

    public function isEnabled(): bool
    {
        return $this->extractor->isEnabled();
    }

    /**
     * One result from several documents. A failing grade in any of them counts, and the lowest confidence stands.
     *
     * @param list<ExtractionResult> $results
     * @return array{fields: array<string, mixed>, confidence: ?float}
     */
    public static function merge(array $results): array
    {
        $fields = ['student_name' => null, 'semester' => null, 'academic_year' => null, 'subjects' => [], 'has_failed_subject' => null, 'enrollment_status' => 'unclear'];
        $confidence = null;

        foreach ($results as $result) {
            foreach (['student_name', 'semester', 'academic_year'] as $key) {
                $fields[$key] ??= $result->fields[$key] ?? null;
            }

            foreach ($result->fields['subjects'] ?? [] as $subject) {
                $fields['subjects'][] = $subject;
            }

            $failed = $result->fields['has_failed_subject'] ?? null;

            if ($failed === true || ($failed === false && $fields['has_failed_subject'] === null)) {
                $fields['has_failed_subject'] = $failed;
            }

            $status = $result->fields['enrollment_status'] ?? 'unclear';

            if ($status === 'officially_enrolled' || ($status === 'not_enrolled' && $fields['enrollment_status'] === 'unclear')) {
                $fields['enrollment_status'] = $status;
            }

            if ($result->confidence !== null) {
                $confidence = $confidence === null ? $result->confidence : min($confidence, $result->confidence);
            }
        }

        return ['fields' => $fields, 'confidence' => $confidence];
    }

    /**
     * What "Use this value" fills in, as the form's yes or no. Null when the document did not settle it.
     *
     * @param array<string, mixed> $fields
     * @return array{has_failed_subject: ?string, is_officially_enrolled: ?string}
     */
    public static function formValues(array $fields): array
    {
        $failed = $fields['has_failed_subject'] ?? null;
        $status = $fields['enrollment_status'] ?? null;

        return [
            'has_failed_subject' => $failed === null ? null : ($failed === true ? 'yes' : 'no'),
            'is_officially_enrolled' => match ($status) {
                'officially_enrolled' => 'yes',
                'not_enrolled' => 'no',
                default => null,
            },
        ];
    }

    /**
     * MSW Committee Head only, and never on their own screening. Saves a suggested row and changes nothing else.
     * The provider call happens outside any transaction, so it holds no database lock while it waits.
     *
     * @throws DomainException with a friendly message for manual entry
     */
    public function suggest(int $screeningId): void
    {
        authorize('eligibility.manage');

        $screening = $this->screenings->findDetailed($screeningId) ?? throw new DomainException('This screening no longer exists.');

        if (!(new EligibilityService())->isReviewerFor($screening)) {
            throw new ForbiddenException('eligibility.own_screening');
        }

        if (!$this->extractor->isEnabled()) {
            throw new DomainException(self::TURNED_OFF);
        }

        if ($screening['result'] !== 'submitted') {
            throw new DomainException('Suggestions are available only before the screening is verified.');
        }

        $documents = $this->documents->forScreening($screeningId);

        if ($documents === []) {
            throw new DomainException('This screening has no documents.');
        }

        // Each document may take up to the provider's timeout, which can pass PHP's limit on a web server.
        set_time_limit(0);
        $results = [];

        foreach ($documents as $document) {
            $result = $this->extractor->extract($this->files->path($document['stored_path']), $document['mime_type']);

            if (!$result->succeeded()) {
                AuditService::log('ai.extraction_failed', self::SOURCE, $screeningId, ['reason' => (string) $result->failure]);

                throw new DomainException(self::FAILED);
            }

            $results[] = $result;
        }

        $merged = self::merge($results);

        Database::transaction(function () use ($screeningId, $merged, $documents): void {
            $this->extractions->discardSuggested(self::SOURCE, $screeningId, (int) current_user_id());
            $id = $this->extractions->insert([
                'source_type' => self::SOURCE,
                'source_id' => $screeningId,
                'extracted_json' => json_encode($merged['fields'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'confidence' => $merged['confidence'],
                'status' => 'suggested',
            ]);
            AuditService::log('ai.suggested', 'ai_extraction', $id, ['screening_id' => $screeningId, 'documents' => count($documents)]);
        });
    }

    /** @return array{extraction_id: int, fields: array<string, mixed>, confidence: ?float, created_at: string, values: array<string, ?string>}|null */
    public function currentSuggestion(int $screeningId): ?array
    {
        $row = $this->extractions->latestSuggested(self::SOURCE, $screeningId);

        if ($row === null) {
            return null;
        }

        $fields = json_decode((string) $row['extracted_json'], true) ?: [];

        return [
            'extraction_id' => (int) $row['extraction_id'],
            'fields' => $fields,
            'confidence' => $row['confidence'] === null ? null : (float) $row['confidence'],
            'created_at' => $row['created_at'],
            'values' => self::formValues($fields),
        ];
    }

    /**
     * Called while the reviewer saves. Confirmed when they used a suggestion, discarded when they ignored it. It records
     * what happened to the suggestion, and the screening's answers still come only from the form. The caller holds the
     * transaction and has checked permission.
     */
    public function settle(int $screeningId, bool $used, int $reviewerId): void
    {
        $row = $this->extractions->latestSuggested(self::SOURCE, $screeningId);

        if ($row === null) {
            return;
        }

        $status = $used ? 'confirmed' : 'discarded';
        $this->extractions->update((int) $row['extraction_id'], ['status' => $status, 'verified_by' => $reviewerId, 'verified_at' => date('Y-m-d H:i:s')]);
        AuditService::log("ai.{$status}", 'ai_extraction', (int) $row['extraction_id'], ['screening_id' => $screeningId]);
    }
}
