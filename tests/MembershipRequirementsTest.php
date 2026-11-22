<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\MembershipApplicationRepository;
use App\Models\UserRepository;
use App\Services\MembershipService;
use App\Services\OpenPositionsService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/**
 * AGILE's membership requirements and Open for application (Nov 22, 2026). General Membership needs the current COR;
 * a position also needs the previous semester's copy of grades, except for 1st years in their 1st semester. The MSW
 * Committee Head opens positions, Committee Head to The Source Code, and sets their targets. Every test runs in a
 * transaction that is rolled back.
 */
final class MembershipRequirementsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testGeneralMembershipNeedsTheCurrentCorOnly(): void
    {
        $this->inRollback(function (): void {
            $service = new MembershipService();
            $application = $this->detailed($this->application(null));

            $this->assertSame(['cor'], $service->requiredDocuments($application));
            $this->assertSame(['cor', 'certificate'], $service->documentsToAsk($application));
        });
    }

    public function testAPositionNeedsTheGradesToo(): void
    {
        $this->inRollback(function (): void {
            $this->makeCurrentTerm('2nd');
            $application = $this->detailed($this->application($this->position('Academics and Training', 'Committee Member')));

            $this->assertSame(['cor', 'grades'], (new MembershipService())->requiredDocuments($application));
            $this->assertSame('Committee Member, Academics and Training', $application['vacancy_title']);
            $this->assertSame('committee_member', $application['role_type']);
        });
    }

    public function testFirstYearsInTheirFirstSemesterGiveNoGrades(): void
    {
        $this->inRollback(function (): void {
            $service = new MembershipService();
            $id = $this->application($this->position('Logistics', 'Committee Member'));
            $this->answerYearLevel($id, '1st Year');

            $this->makeCurrentTerm('1st');
            $this->assertSame(['cor'], $service->requiredDocuments($this->detailed($id)));

            // 1st years joining in the 2nd semester give their 1st semester grades.
            $this->makeCurrentTerm('2nd');
            $this->assertSame(['cor', 'grades'], $service->requiredDocuments($this->detailed($id)));

            // A 2nd year in a 1st semester has grades from last semester.
            $this->makeCurrentTerm('1st');
            $this->answerYearLevel($id, '2nd Year');
            $this->assertSame(['cor', 'grades'], $service->requiredDocuments($this->detailed($id)));
        });
    }

    public function testTheApplyPageAsksForGradesByYearLevel(): void
    {
        $this->inRollback(function (): void {
            $service = new MembershipService();

            $this->makeCurrentTerm('1st');
            $this->assertFalse($service->gradesForYear('1st Year')['needed'], '1st year, 1st semester: COR only.');
            $this->assertTrue($service->gradesForYear('2nd Year')['needed']);

            $this->makeCurrentTerm('2nd');
            $this->assertSame(['needed' => true, 'label' => '1st semester copy of grades'], $service->gradesForYear('1st Year'));
            $this->assertSame(['needed' => true, 'label' => 'Previous semester copy of grades'], $service->gradesForYear('3rd Year'));
        });
    }

    public function testTheRequirementsDropdownIsAgilesTable(): void
    {
        $groups = MembershipService::REQUIREMENT_GROUPS;

        $this->assertCount(7, $groups);
        $this->assertFalse($groups['general'][2], 'General members: COR only.');
        $this->assertFalse($groups['first_year_first_sem'][2], '1st year, 1st semester: COR only.');
        $this->assertSame('1st semester copy of grades', $groups['first_year_second_sem'][3]);

        foreach (['executive', 'committee', 'year_rep', 'applicant'] as $key) {
            $this->assertTrue($groups[$key][2], "{$key} needs grades.");
        }
    }

    public function testTheCommitteesHeadDecidesAndApprovalGivesThePosition(): void
    {
        $this->inRollback(function (): void {
            $this->makeCurrentTerm('2nd');
            $positionId = $this->position('Academics and Training', 'Committee Member');
            $id = $this->application($positionId, ['cor' => 'verified', 'grades' => 'verified']);
            $service = new MembershipService();

            $this->assertSame($this->userId('head@agile.test'), $service->committeeDecider($this->detailed($id)));

            $this->signIn('head@agile.test');
            $service->approve($id, 'Welcome to the committee.');

            $member = Database::connection()->query(
                "SELECT m.membership_type, m.role_status, m.position_id FROM members m
                 JOIN membership_applications a ON a.applicant_id = m.applicant_id WHERE a.application_id = {$id}"
            )->fetch();

            $this->assertSame($positionId, (int) $member['position_id']);
            $this->assertSame('officer', $member['membership_type']);
            $this->assertSame('active', $member['role_status']);
        });
    }

    public function testACommitteeHeadApplicationGoesToTheMswHead(): void
    {
        $this->inRollback(function (): void {
            $id = $this->application($this->position('Logistics', 'Committee Head'));

            $this->assertNull((new MembershipService())->committeeDecider($this->detailed($id)), 'R6: the MSW Committee Head decides a Committee Head position.');
        });
    }

    public function testApprovalWaitsUntilTheGradesAreVerified(): void
    {
        $this->inRollback(function (): void {
            $this->makeCurrentTerm('2nd');
            $id = $this->application($this->position('Academics and Training', 'Committee Member'), ['cor' => 'verified', 'grades' => 'pending']);
            $this->signIn('head@agile.test');

            try {
                (new MembershipService())->approve($id, 'Welcome.');
                $this->fail('An application with unverified grades was approved.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('copy of grades', $exception->getMessage());
            }
        });
    }

    public function testOpenForApplicationControlsTheList(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $service = new MembershipService();
            $editor = $this->position('The Source Code', 'Editor-in-Chief');
            $writer = $this->position('The Source Code', 'Writer');

            $this->assertNotContains($editor, array_column($service->openPositions(), 'position_id'), 'Closed until the MSW Committee Head opens it.');

            // Open the Editor-in-Chief with a target of 5 and the Writer with no target; everything else closes.
            (new OpenPositionsService())->save([$editor, $writer], [$editor => '5', $writer => '']);
            $open = array_column($service->openPositions(), 'position_id');

            $this->assertSame([$editor, $writer], array_values(array_intersect($open, [$editor, $writer])));
            $this->assertNotContains($this->position('Logistics', 'Committee Member'), $open);

            $row = array_values(array_filter($service->applicationPositions(), static fn (array $p): bool => $p['position_id'] === $editor))[0];
            $this->assertSame(5, $row['target_slots']);

            // A target of 0 means full: it leaves the list even while ticked open.
            (new OpenPositionsService())->save([$editor], [$editor => '0']);
            $this->assertNotContains($editor, array_column($service->openPositions(), 'position_id'));
        });
    }

    public function testOnlyTheMswHeadOpensPositions(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('head@agile.test');
            $this->expectException(App\Core\ForbiddenException::class);
            (new OpenPositionsService())->save([], []);
        });
    }

    public function testATargetMustBeAWholeNumber(): void
    {
        $this->inRollback(function (): void {
            $this->signIn('mswhead@agile.test');
            $writer = $this->position('The Source Code', 'Writer');

            $this->expectException(DomainException::class);
            (new OpenPositionsService())->save([$writer], [$writer => 'many']);
        });
    }

    public function testApprovalIsRefusedWhenThePositionIsFull(): void
    {
        $this->inRollback(function (): void {
            $this->makeCurrentTerm('2nd');
            $positionId = $this->position('Academics and Training', 'Committee Member');
            $id = $this->application($positionId, ['cor' => 'verified', 'grades' => 'verified']);
            Database::connection()->prepare('UPDATE positions SET target_slots = 0 WHERE position_id = ?')->execute([$positionId]);
            $this->signIn('head@agile.test');

            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('Every slot');
            (new MembershipService())->approve($id, 'Welcome.');
        });
    }

    public function testChoosingAPosition(): void
    {
        $this->inRollback(function (): void {
            $service = new MembershipService();
            $this->signIn('nomember@agile.test');
            $id = $this->draftFor('nomember@agile.test');

            $service->chooseType($id, null, $this->position('Logistics', 'Committee Member'));
            $this->assertSame($this->position('Logistics', 'Committee Member'), (int) $this->detailed($id)['position_id']);

            // A position that is not open for application cannot be chosen.
            $this->expectException(DomainException::class);
            $service->chooseType($id, null, $this->position('Logistics', 'Committee Head'));
        });
    }

    public function testSomeoneHoldingAPositionCannotChooseAnotherThisWay(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $userId = $this->userId('nomember@agile.test');
            $db->prepare("INSERT INTO members (user_id, member_no, membership_type, position_id, role_status, joined_at) VALUES (?, 'AGILE-2040-9999', 'officer', ?, 'active', CURDATE())")
                ->execute([$userId, $this->position('Logistics', 'Committee Member')]);
            $this->signIn('nomember@agile.test');
            $id = $this->draftFor('nomember@agile.test');

            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('already hold a position');
            (new MembershipService())->chooseType($id, null, $this->position('Academics and Training', 'Committee Member'));
        });
    }

    public function testTheDatabaseRefusesBothAVacancyAndAPosition(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $vacancyId = (int) $db->query('SELECT vacancy_id FROM vacancies LIMIT 1')->fetchColumn();

            if ($vacancyId === 0) {
                $this->markTestSkipped('The test database has no vacancy.');
            }

            $this->expectException(PDOException::class);
            $this->expectExceptionMessageMatches('/chk_membership_applications_one_choice/');
            $db->prepare('UPDATE membership_applications SET vacancy_id = ?, position_id = ? WHERE application_id = ?')
                ->execute([$vacancyId, $this->position('Logistics', 'Committee Member'), $this->application(null)]);
        });
    }

    /** @param array<string, string> $documents doc_type => verification status */
    private function application(?int $positionId, array $documents = ['cor' => 'verified']): int
    {
        static $n = 0;
        $n++;
        $db = Database::connection();
        $db->prepare('INSERT INTO applicants (email, first_name, last_name, student_no, email_verified_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute(["requirements{$n}@example.test", 'Req', "Person{$n}", sprintf('2030-%05d-OU-0', 700 + $n)]);
        $applicantId = (int) $db->lastInsertId();

        $id = (new MembershipApplicationRepository())->insert([
            'applicant_id' => $applicantId,
            'position_id' => $positionId,
            'tracking_code' => MembershipService::generateTrackingCode(),
            'status' => 'under_review',
            'submitted_at' => date('Y-m-d H:i:s'),
            'wizard_step' => 6,
        ]);

        foreach ($documents as $type => $status) {
            $db->prepare("INSERT INTO application_documents (application_id, doc_type, original_name, stored_path, mime_type, size_bytes, verification_status) VALUES (?, ?, 'x.pdf', ?, 'application/pdf', 1, ?)")
                ->execute([$id, $type, 'applications/demo/' . md5('demo-cor') . '.pdf', $status]);
        }

        return $id;
    }

    private function draftFor(string $email): int
    {
        return (new MembershipApplicationRepository())->insert([
            'user_id' => $this->userId($email),
            'tracking_code' => MembershipService::generateTrackingCode(),
            'status' => 'draft',
            'wizard_step' => 3,
        ]);
    }

    private function answerYearLevel(int $applicationId, string $yearLevel): void
    {
        $db = Database::connection();
        $questionId = (int) $db->query("SELECT question_id FROM application_questions WHERE role_type = 'academic' ORDER BY sort_order, question_id LIMIT 1")->fetchColumn();
        $db->prepare('DELETE FROM application_answers WHERE application_id = ? AND question_id = ?')->execute([$applicationId, $questionId]);
        $db->prepare('INSERT INTO application_answers (application_id, question_id, answer_text) VALUES (?, ?, ?)')->execute([$applicationId, $questionId, $yearLevel]);
    }

    private function makeCurrentTerm(string $semester): void
    {
        $db = Database::connection();
        $db->exec('UPDATE academic_terms SET is_current = 0 WHERE is_current = 1');
        $db->prepare("INSERT INTO academic_terms (academic_year, semester, screening_deadline, is_current) VALUES ('2040-2041', ?, '2040-12-01', 1)
                      ON DUPLICATE KEY UPDATE is_current = 1")->execute([$semester]);
    }

    /** @return array<string, mixed> */
    private function detailed(int $id): array
    {
        return (new MembershipApplicationRepository())->findDetailed($id);
    }

    private function position(string $committee, string $title): int
    {
        $statement = Database::connection()->prepare('SELECT p.position_id FROM positions p JOIN committees c ON c.committee_id = p.committee_id WHERE c.name = ? AND p.title = ?');
        $statement->execute([$committee, $title]);

        return (int) $statement->fetchColumn();
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
