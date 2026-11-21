<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\MembershipApplicationRepository;
use App\Models\UserRepository;
use App\Services\MembershipService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/**
 * AGILE's membership requirements (Nov 22, 2026): General Membership needs the current COR; joining a committee as a
 * Committee Member, or a position, also needs the previous semester's copy of grades, except for 1st years in their
 * 1st semester. School ID is no longer asked for. Every test runs in a transaction that is rolled back.
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

    public function testJoiningACommitteeNeedsTheGradesToo(): void
    {
        $this->inRollback(function (): void {
            $this->makeCurrentTerm('2nd');
            $application = $this->detailed($this->application($this->committeeId('Academics and Training')));

            $this->assertSame(['cor', 'grades'], (new MembershipService())->requiredDocuments($application));
            $this->assertSame('Committee Member, Academics and Training', $application['vacancy_title']);
            $this->assertSame('committee_member', $application['role_type']);
        });
    }

    public function testFirstYearsInTheirFirstSemesterGiveNoGrades(): void
    {
        $this->inRollback(function (): void {
            $service = new MembershipService();
            $id = $this->application($this->committeeId('Logistics'));
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

    public function testTheCommitteesHeadDecidesAndApprovalMakesACommitteeMember(): void
    {
        $this->inRollback(function (): void {
            $this->makeCurrentTerm('2nd');
            $committeeId = $this->committeeId('Academics and Training');
            $id = $this->application($committeeId, ['cor' => 'verified', 'grades' => 'verified']);
            $service = new MembershipService();

            $this->assertSame($this->userId('head@agile.test'), $service->committeeDecider($this->detailed($id)));

            $this->signIn('head@agile.test');
            $service->approve($id, 'Welcome to the committee.');

            $member = Database::connection()->query(
                "SELECT m.membership_type, m.role_status, p.title, p.committee_id FROM members m
                 JOIN positions p ON p.position_id = m.position_id
                 JOIN membership_applications a ON a.applicant_id = m.applicant_id WHERE a.application_id = {$id}"
            )->fetch();

            $this->assertSame('Committee Member', $member['title']);
            $this->assertSame($committeeId, (int) $member['committee_id']);
            $this->assertSame('officer', $member['membership_type']);
            $this->assertSame('active', $member['role_status']);
        });
    }

    public function testApprovalWaitsUntilTheGradesAreVerified(): void
    {
        $this->inRollback(function (): void {
            $this->makeCurrentTerm('2nd');
            $id = $this->application($this->committeeId('Academics and Training'), ['cor' => 'verified', 'grades' => 'pending']);
            $this->signIn('head@agile.test');

            try {
                (new MembershipService())->approve($id, 'Welcome.');
                $this->fail('An application with unverified grades was approved.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('copy of grades', $exception->getMessage());
            }
        });
    }

    public function testChoosingACommittee(): void
    {
        $this->inRollback(function (): void {
            $service = new MembershipService();
            $this->signIn('nomember@agile.test');
            $id = (new MembershipApplicationRepository())->insert([
                'user_id' => $this->userId('nomember@agile.test'),
                'tracking_code' => MembershipService::generateTrackingCode(),
                'status' => 'draft',
                'wizard_step' => 3,
            ]);

            $service->chooseType($id, null, $this->committeeId('Logistics'));
            $this->assertSame($this->committeeId('Logistics'), (int) $this->detailed($id)['committee_id']);

            // The Source Code has no Committee Member position; its positions open as vacancies.
            $this->expectException(DomainException::class);
            $service->chooseType($id, null, $this->committeeId('The Source Code'));
        });
    }

    public function testSomeoneHoldingAPositionCannotJoinACommitteeThisWay(): void
    {
        $this->inRollback(function (): void {
            // A member who may apply and already holds a position (a Logistics Committee Member).
            $db = Database::connection();
            $userId = $this->userId('nomember@agile.test');
            $positionId = (int) $db->query("SELECT p.position_id FROM positions p JOIN committees c ON c.committee_id = p.committee_id WHERE c.name = 'Logistics' AND p.role_type = 'committee_member'")->fetchColumn();
            $db->prepare("INSERT INTO members (user_id, member_no, membership_type, position_id, role_status, joined_at) VALUES (?, 'AGILE-2040-9999', 'officer', ?, 'active', CURDATE())")
                ->execute([$userId, $positionId]);
            $this->signIn('nomember@agile.test');
            $id = (new MembershipApplicationRepository())->insert([
                'user_id' => $userId,
                'tracking_code' => MembershipService::generateTrackingCode(),
                'status' => 'draft',
                'wizard_step' => 3,
            ]);

            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('already hold a position');
            (new MembershipService())->chooseType($id, null, $this->committeeId('Academics and Training'));
        });
    }

    public function testTheDatabaseRefusesBothAVacancyAndACommittee(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $vacancyId = (int) $db->query('SELECT vacancy_id FROM vacancies LIMIT 1')->fetchColumn();

            if ($vacancyId === 0) {
                $this->markTestSkipped('The test database has no vacancy.');
            }

            $this->expectException(PDOException::class);
            $this->expectExceptionMessageMatches('/chk_membership_applications_one_choice/');
            $db->prepare('UPDATE membership_applications SET vacancy_id = ?, committee_id = ? WHERE application_id = ?')
                ->execute([$vacancyId, $this->committeeId('Logistics'), $this->application(null)]);
        });
    }

    /** @param array<string, string> $documents doc_type => verification status */
    private function application(?int $committeeId, array $documents = ['cor' => 'verified']): int
    {
        static $n = 0;
        $n++;
        $db = Database::connection();
        $db->prepare('INSERT INTO applicants (email, first_name, last_name, student_no, email_verified_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute(["requirements{$n}@example.test", 'Req', "Person{$n}", sprintf('2030-%05d-OU-0', 700 + $n)]);
        $applicantId = (int) $db->lastInsertId();

        $id = (new MembershipApplicationRepository())->insert([
            'applicant_id' => $applicantId,
            'committee_id' => $committeeId,
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

    private function committeeId(string $name): int
    {
        $statement = Database::connection()->prepare('SELECT committee_id FROM committees WHERE name = ?');
        $statement->execute([$name]);

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
