<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\AiExtractionRepository;
use App\Models\EligibilityDocumentRepository;
use App\Models\EligibilityScreeningRepository;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use App\Services\Ai\ExtractionResult;
use App\Services\Ai\ExtractorInterface;
use App\Services\Ai\NullExtractor;
use App\Services\AiExtractionService;
use App\Services\EligibilityService;
use App\Services\FileStorageService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** AI suggestions never decide anything (BR-11, TC-28). Needs the seeded database. Database tests roll back. */
final class AiExtractionTest extends TestCase
{
    private ?string $storedFile = null;

    protected function tearDown(): void
    {
        if ($this->storedFile !== null) {
            (new FileStorageService())->delete($this->storedFile);
        }

        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testMergeLetAnyFailingGradeCount(): void
    {
        $merged = AiExtractionService::merge([
            new ExtractionResult(['student_name' => 'A', 'subjects' => [['code' => 'X', 'grade' => '1.0']], 'has_failed_subject' => false, 'enrollment_status' => 'unclear'], 0.9),
            new ExtractionResult(['student_name' => 'B', 'subjects' => [['code' => 'Y', 'grade' => '5.0']], 'has_failed_subject' => true, 'enrollment_status' => 'officially_enrolled'], 0.6),
        ]);

        $this->assertSame('A', $merged['fields']['student_name']);
        $this->assertCount(2, $merged['fields']['subjects']);
        $this->assertTrue($merged['fields']['has_failed_subject']);
        $this->assertSame('officially_enrolled', $merged['fields']['enrollment_status']);
        $this->assertSame(0.6, $merged['confidence']);
    }

    public function testFormValuesLeaveUnclearAnswersEmpty(): void
    {
        $this->assertSame(['has_failed_subject' => 'yes', 'is_officially_enrolled' => 'no'], AiExtractionService::formValues(['has_failed_subject' => true, 'enrollment_status' => 'not_enrolled']));
        $this->assertSame(['has_failed_subject' => 'no', 'is_officially_enrolled' => 'yes'], AiExtractionService::formValues(['has_failed_subject' => false, 'enrollment_status' => 'officially_enrolled']));
        $this->assertSame(['has_failed_subject' => null, 'is_officially_enrolled' => null], AiExtractionService::formValues(['has_failed_subject' => null, 'enrollment_status' => 'unclear']));
    }

    public function testTurnedOffSavesNothing(): void
    {
        $this->inRollback(function (): void {
            $id = $this->submittedScreening();
            $service = new AiExtractionService(new NullExtractor());
            $this->assertFalse($service->isEnabled());

            try {
                $service->suggest($id);
                $this->fail('Suggestions ran with AI turned off.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('turned off', $exception->getMessage());
            }

            $this->assertNull($service->currentSuggestion($id));
        });
    }

    public function testASuggestionLeavesTheScreeningUnchanged(): void
    {
        $this->inRollback(function (): void {
            $id = $this->submittedScreening();
            $repository = new EligibilityScreeningRepository();
            $before = $repository->find($id);
            $service = new AiExtractionService($this->stub(new ExtractionResult(['has_failed_subject' => true, 'enrollment_status' => 'not_enrolled', 'subjects' => []], 0.5)));

            $service->suggest($id);

            $this->assertEquals($before, $repository->find($id));
            $suggestion = $service->currentSuggestion($id);
            $this->assertSame('yes', $suggestion['values']['has_failed_subject']);
            $this->assertSame(0.5, $suggestion['confidence']);
            $this->assertSame('suggested', (new AiExtractionRepository())->find($suggestion['extraction_id'])['status']);
        });
    }

    public function testSavingUsesTheFormNotTheSuggestion(): void
    {
        $this->inRollback(function (): void {
            $id = $this->submittedScreening();
            $service = new AiExtractionService($this->stub(new ExtractionResult(['has_failed_subject' => true, 'enrollment_status' => 'not_enrolled', 'subjects' => []], 0.9)));
            $service->suggest($id);
            $extractionId = $service->currentSuggestion($id)['extraction_id'];

            $this->assertSame('eligible', (new EligibilityService())->verify($id, false, true, false));

            $row = (new AiExtractionRepository())->find($extractionId);
            $this->assertSame('discarded', $row['status']);
            $this->assertSame($this->userId('mswhead@agile.test'), (int) $row['verified_by']);
            $this->assertNotNull($row['verified_at']);
            $this->assertSame(0, (int) (new EligibilityScreeningRepository())->find($id)['has_failed_subject']);
        });
    }

    public function testUsingASuggestionConfirmsIt(): void
    {
        $this->inRollback(function (): void {
            $id = $this->submittedScreening();
            $service = new AiExtractionService($this->stub(new ExtractionResult(['has_failed_subject' => true, 'enrollment_status' => 'officially_enrolled', 'subjects' => []], 0.9)));
            $service->suggest($id);
            $extractionId = $service->currentSuggestion($id)['extraction_id'];

            $this->assertSame('flagged', (new EligibilityService())->verify($id, true, true, true));
            $this->assertSame('confirmed', (new AiExtractionRepository())->find($extractionId)['status']);
        });
    }

    public function testAFailureFallsBackToManualEntry(): void
    {
        $this->inRollback(function (): void {
            $id = $this->submittedScreening();
            $service = new AiExtractionService($this->stub(new ExtractionResult(failure: 'Boom')));

            try {
                $service->suggest($id);
                $this->fail('A failure was not reported.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('enter the values manually', $exception->getMessage());
            }

            $this->assertNull($service->currentSuggestion($id));
            $this->assertSame('submitted', (new EligibilityScreeningRepository())->find($id)['result']);
        });
    }

    public function testOnlyTheMswHeadAndNeverOnTheirOwn(): void
    {
        $this->inRollback(function (): void {
            $id = $this->submittedScreening();
            $service = new AiExtractionService($this->stub(new ExtractionResult(['subjects' => []], 0.5)));

            foreach (['president@agile.test', 'mswmember@agile.test', 'head@agile.test'] as $email) {
                $this->signIn($email);

                try {
                    $service->suggest($id);
                    $this->fail("{$email} got suggestions.");
                } catch (ForbiddenException) {
                    $this->addToAssertionCount(1);
                }
            }

            $this->signIn('mswhead@agile.test');
            $own = (new MemberRepository())->findByUser($this->userId('mswhead@agile.test'));
            $repository = new EligibilityScreeningRepository();
            $row = $repository->findFor((int) $repository->find($id)['term_id'], (int) $own['member_id']);
            $repository->update((int) $row['screening_id'], ['result' => 'submitted']);

            $this->expectException(ForbiddenException::class);
            $service->suggest((int) $row['screening_id']);
        });
    }

    private function stub(ExtractionResult $result): ExtractorInterface
    {
        return new class ($result) implements ExtractorInterface {
            public function __construct(private readonly ExtractionResult $result)
            {
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function extract(string $filePath, string $mime): ExtractionResult
            {
                return $this->result;
            }
        };
    }

    /** Head's screening, submitted with one document. Leaves the MSW Committee Head signed in. */
    private function submittedScreening(): int
    {
        $this->signIn('mswhead@agile.test');
        $service = new EligibilityService();
        $term = $service->createTerm(['academic_year' => '2090-2091', 'semester' => 'summer', 'screening_deadline' => date('Y-m-d', time() + 60 * 86400), 'grace_days' => '7'], true);
        $service->startScreening();

        $member = (new MemberRepository())->findByUser($this->userId('head@agile.test'));
        $repository = new EligibilityScreeningRepository();
        $id = (int) $repository->findFor($term, (int) $member['member_id'])['screening_id'];
        $repository->update($id, ['result' => 'submitted']);

        $this->storedFile = (new FileStorageService())->storeContents('%PDF-1.4', 'eligibility', 'pdf');
        (new EligibilityDocumentRepository())->insert(['screening_id' => $id, 'original_name' => 'g.pdf', 'stored_path' => $this->storedFile, 'mime_type' => 'application/pdf', 'size_bytes' => 8]);

        return $id;
    }

    private function signIn(string $email): void
    {
        $_SESSION['user_id'] = $this->userId($email);
        RbacService::clearCache();
    }

    private function userId(string $email): int
    {
        return (int) (new UserRepository())->findByEmail($email)['user_id'];
    }

    private function inRollback(callable $test): void
    {
        $sentinel = new RuntimeException('Roll back the test.');

        try {
            Database::transaction(static function () use ($test, $sentinel): void {
                $test();

                throw $sentinel;
            });
        } catch (RuntimeException $exception) {
            if ($exception !== $sentinel) {
                throw $exception;
            }
        }
    }
}
