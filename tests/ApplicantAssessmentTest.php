<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\InterviewRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\UserRepository;
use App\Services\AssessmentService;
use App\Services\InterviewService;
use App\Services\MembershipService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** The MSW interviewer's assessment and AGILE's interview question bank (Nov 23, 2026). Database tests roll back. */
final class ApplicantAssessmentTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testTheSheetsCriteriaAndTotals(): void
    {
        $this->assertSame(25, AssessmentService::maxScore('application'));
        $this->assertSame(30, AssessmentService::maxScore('interview'));
        $this->assertSame(5, AssessmentService::maxScore('role'));
        $this->assertSame(60, AssessmentService::maxScore());
        $this->assertCount(5, AssessmentService::RECOMMENDATIONS);
    }

    public function testTheBankHasAgilesSixtyFiveQuestions(): void
    {
        $this->assertSame(65, (int) Database::connection()->query('SELECT COUNT(*) FROM interview_questions')->fetchColumn());
    }

    public function testTheBankPicksCoreRoleCommitteeAndPosition(): void
    {
        $service = new AssessmentService();
        $codes = static fn (array $rows): array => array_map(static fn (array $r): string => $r['code'] . ':' . ($r['committee'] ?? $r['position'] ?? '*'), $rows);

        $member = $codes($service->questionBank(['role_type' => 'committee_member', 'committee_name' => 'Logistics', 'position_title' => 'Committee Member']));
        $this->assertSame(['C1:*', 'C2:*', 'C3:*', 'C4:*', 'C5:*', 'CM1:*', 'CM2:*', 'CM3:Logistics'], $member);

        $writer = $codes($service->questionBank(['role_type' => 'source_code', 'committee_name' => 'The Source Code', 'position_title' => 'Writer']));
        $this->assertSame(['C1:*', 'C2:*', 'C3:*', 'C4:*', 'C5:*', 'SC1:Writer', 'SC2:Writer', 'SC3:Writer'], $writer);

        $general = $codes($service->questionBank(['role_type' => null]));
        $this->assertSame(['C1:*', 'C2:*', 'C3:*', 'C4:*', 'C5:*', 'G1:*', 'G2:*', 'G3:*'], $general);
    }

    public function testTheAssessmentWaitsForTheInterviewThenSavesAndUpdates(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application();
            $this->signIn('mswmember@agile.test');
            $service = new AssessmentService();

            try {
                $service->save($applicationId, $this->ratings(4), [], $this->summary());
                $this->fail('No assessment before the interview.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('interview result', $exception->getMessage());
            }

            $this->interviewDone($applicationId);
            $service->save($applicationId, $this->ratings(4), ['interview' => [0 => 'Clear answers.']], $this->summary());
            $saved = $service->forApplication($applicationId);
            $this->assertSame(20, (int) $saved['application_score']);
            $this->assertSame(24, (int) $saved['interview_score']);
            $this->assertSame(4, (int) $saved['role_score']);
            $this->assertSame(48, (int) $saved['total_score']);
            $this->assertSame('Clear answers.', $saved['ratings']['interview'][0]['remark']);
            $this->assertSame('Communication', $saved['ratings']['interview'][0]['criterion']);

            $service->save($applicationId, $this->ratings(5), [], ['recommendation' => 'highly_recommended'] + $this->summary());
            $this->assertSame(60, (int) $service->forApplication($applicationId)['total_score']);
            $this->assertSame(1, (int) Database::connection()->query("SELECT COUNT(*) FROM applicant_assessments WHERE application_id = {$applicationId}")->fetchColumn());
        });
    }

    public function testBadRatingsAndRecommendationsAreRefused(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application();
            $this->signIn('mswmember@agile.test');
            $this->interviewDone($applicationId);
            $service = new AssessmentService();

            foreach ([['application', 0, '6'], ['interview', 2, '0'], ['role', 0, ''], ['interview', 5, 'x']] as [$section, $index, $bad]) {
                $ratings = $this->ratings(3);
                $ratings[$section][$index] = $bad;

                try {
                    $service->save($applicationId, $ratings, [], $this->summary());
                    $this->fail("{$section} {$index} = '{$bad}' was accepted.");
                } catch (DomainException) {
                    $this->addToAssertionCount(1);
                }
            }

            $this->expectException(DomainException::class);
            $service->save($applicationId, $this->ratings(3), [], ['recommendation' => 'maybe'] + $this->summary());
        });
    }

    public function testOnlyInterviewersAssess(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application();
            $this->signIn('member@agile.test');
            $this->expectException(ForbiddenException::class);
            (new AssessmentService())->save($applicationId, $this->ratings(3), [], $this->summary());
        });
    }

    public function testADecidedApplicationsAssessmentIsFinal(): void
    {
        $this->inRollback(function (): void {
            $applicationId = $this->application();
            $this->signIn('mswmember@agile.test');
            $this->interviewDone($applicationId);
            (new MembershipApplicationRepository())->update($applicationId, ['status' => 'approved']);

            $this->expectException(DomainException::class);
            (new AssessmentService())->save($applicationId, $this->ratings(3), [], $this->summary());
        });
    }

    /** @return array<string, array<int, int|string>> */
    private function ratings(int $value): array
    {
        $ratings = [];

        foreach (AssessmentService::SECTIONS as $section => [, $criteria]) {
            $ratings[$section] = array_fill(0, count($criteria), (string) $value);
        }

        return $ratings;
    }

    /** @return array{recommendation: string, strengths: string, development: string, remarks: string} */
    private function summary(): array
    {
        return ['recommendation' => 'recommended', 'strengths' => 'Organized.', 'development' => 'Public speaking.', 'remarks' => ''];
    }

    private function interviewDone(int $applicationId): void
    {
        $service = new InterviewService();
        $service->schedule($applicationId, [
            'scheduled_at' => date('Y-m-d H:i:s', time() + 86400),
            'mode' => 'onsite',
            'location' => 'Room 1',
            'interviewer_id' => $this->userId('mswhead@agile.test'),
        ]);
        $id = (int) (new InterviewRepository())->forApplication($applicationId)[0]['interview_id'];
        $service->saveChecklist($applicationId, $id, ['identity' => true, 'role' => true, 'privacy' => true, 'conflict' => 'no']);
        $service->recordResult($applicationId, $id, 'passed', '');
    }

    private function application(): int
    {
        $db = Database::connection();
        $db->prepare('INSERT INTO membership_applications (user_id, tracking_code, status, submitted_at, wizard_step) VALUES (?, ?, ?, ?, 6)')
            ->execute([$this->userId('nomember@agile.test'), MembershipService::generateTrackingCode(), 'under_review', date('Y-m-d H:i:s')]);

        return (int) $db->lastInsertId();
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
