<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\ApplicantRepository;
use App\Services\EncryptionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The database is the last line of defense (docs/database-audit.md): these rows must be refused by MySQL itself,
 * whatever the PHP code does. Every test runs inside a transaction that is rolled back.
 */
final class DatabaseIntegrityTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function badRows(): array
    {
        return [
            'T1 the same term twice' => ["INSERT INTO academic_terms (academic_year, semester, screening_deadline) VALUES ('2030-2031', '1st', '2030-12-01'), ('2030-2031', '1st', '2030-12-01')", 'uq_academic_terms_year_semester'],
            'T1 two current terms' => ["INSERT INTO academic_terms (academic_year, semester, screening_deadline, is_current) VALUES ('2030-2031', '1st', '2030-12-01', 1), ('2030-2031', '2nd', '2031-05-01', 1)", 'uq_academic_terms_current_flag'],
            'T1 a year that is not YYYY-YYYY+1' => ["INSERT INTO academic_terms (academic_year, semester, screening_deadline) VALUES ('2030-2032', '1st', '2030-12-01')", 'chk_academic_terms_year'],
            'T2 more filled than slots' => ['INSERT INTO vacancies (request_id, position_id, title, description, slots, slots_filled, opens_at, closes_at) SELECT request_id, position_id, \'x\', \'y\', 2, 9, \'2030-11-01\', \'2030-12-01\' FROM hr_requests LIMIT 1', 'chk_vacancies_slots_filled'],
            'T2 closes before it opens' => ['INSERT INTO vacancies (request_id, position_id, title, description, slots, opens_at, closes_at) SELECT request_id, position_id, \'x\', \'y\', 2, \'2030-12-01\', \'2030-11-01\' FROM hr_requests LIMIT 1', 'chk_vacancies_dates'],
            'T3 a score over 100' => ["INSERT INTO evaluations (application_id, evaluator_id, score, rubric_json, recommendation, remarks) SELECT a.application_id, u.user_id, 999.99, '{}', 'recommend', 'x' FROM membership_applications a, users u LIMIT 1", 'chk_evaluations_score'],
            'T4 a case with no owner' => ["INSERT INTO welfare_cases (case_code, category_id, subject, description, submitted_at) VALUES ('WCTESTNOOWN1', 1, 'x', 'y', NOW())", 'chk_welfare_cases_owner'],
            'T4 a guest email with no hash' => ["INSERT INTO welfare_cases (case_code, guest_email, category_id, subject, description, submitted_at) VALUES ('WCTESTNOHSH1', 'cipher', 1, 'x', 'y', NOW())", 'chk_welfare_cases_guest_hash'],
            'T5 one student number, two applicants' => ["INSERT INTO applicants (email, first_name, last_name, student_no) VALUES ('t5a@example.test', 'A', 'B', '2030-00001-OU-0'), ('t5b@example.test', 'A', 'B', '2030-00001-OU-0')", 'uq_applicants_student_no'],
            'T6 an application with no owner' => ["INSERT INTO membership_applications (tracking_code) VALUES ('TESTNOOWNER1')", 'chk_membership_applications_owner'],
            'T7 the same title twice in a committee' => ["INSERT INTO positions (committee_id, title, role_type) VALUES (NULL, 'President', 'officer')", 'uq_positions_committee_title'],
        ];
    }

    #[DataProvider('badRows')]
    public function testTheDatabaseRefuses(string $sql, string $constraint): void
    {
        $this->inRollback(function () use ($sql, $constraint): void {
            try {
                Database::connection()->exec($sql);
                $this->fail("MySQL accepted a row that {$constraint} should refuse.");
            } catch (PDOException $exception) {
                $this->assertStringContainsString($constraint, $exception->getMessage());
            }
        });
    }

    public function testValidRowsStillPass(): void
    {
        $this->inRollback(function (): void {
            $db = Database::connection();
            $db->exec("INSERT INTO academic_terms (academic_year, semester, screening_deadline) VALUES ('2031-2032', '1st', '2031-12-01'), ('2031-2032', '2nd', '2032-05-01')");
            $db->exec("INSERT INTO applicants (email, first_name, last_name, student_no) VALUES ('nono1@example.test', 'A', 'B', NULL), ('nono2@example.test', 'A', 'B', NULL)");

            $this->assertSame(2, (int) $db->query("SELECT COUNT(*) FROM academic_terms WHERE academic_year = '2031-2032'")->fetchColumn());
        });
    }

    public function testTheApplicantRepositoryExplainsATakenStudentNumber(): void
    {
        $this->inRollback(function (): void {
            $applicants = new ApplicantRepository();
            $first = $applicants->saveVerified(['email' => 'taken-a@example.test', 'first_name' => 'A', 'last_name' => 'B', 'student_no' => '2030-00002-OU-0']);
            $second = $applicants->saveVerified(['email' => 'taken-b@example.test', 'first_name' => 'A', 'last_name' => 'B', 'student_no' => '']);

            $this->assertNull($applicants->find($second)['student_no'], 'An empty student number is saved as NULL.');
            $this->assertSame($first, $applicants->saveVerified(['email' => 'taken-a@example.test', 'first_name' => 'A', 'last_name' => 'C', 'student_no' => '2030-00002-OU-0']), 'Same person, same row.');

            $this->expectException(DomainException::class);
            $applicants->update($second, ['student_no' => '2030-00002-OU-0']);
        });
    }

    public function testEveryMigrationIsRecorded(): void
    {
        $files = array_map('basename', glob(dirname(__DIR__) . '/database/migrations/*.{php,sql}', GLOB_BRACE) ?: []);
        $ran = Database::connection()->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

        sort($files);
        sort($ran);
        $this->assertSame($files, $ran);
    }

    public function testRealCommitteesAreSeeded(): void
    {
        $db = Database::connection();
        $committees = $db->query('SELECT name FROM committees ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([
            'Academics and Training', 'Creatives, Social Media and Communication', 'Events and External Relations',
            'Finance and Fund Raising', 'Logistics', 'Membership and Student Welfare', 'Research and Extensions', 'The Source Code',
        ], $committees);
        $this->assertSame(11, (int) $db->query("SELECT COUNT(*) FROM positions p JOIN committees c ON c.committee_id = p.committee_id WHERE c.name = 'The Source Code' AND p.role_type = 'source_code'")->fetchColumn());
        $this->assertSame(
            ['Assistant Secretary', 'Auditor', 'President', 'Secretary', 'Treasurer', 'Vice President for External Affairs', 'Vice President for Internal Affairs'],
            $db->query('SELECT title FROM positions WHERE committee_id IS NULL ORDER BY title')->fetchAll(PDO::FETCH_COLUMN)
        );
        $this->assertNull($db->query("SELECT head_user_id FROM committees WHERE name = 'The Source Code'")->fetchColumn() ?: null);
    }

    public function testTheBlindIndexIgnoresCaseAndSpaces(): void
    {
        $encryption = new EncryptionService();

        $this->assertSame($encryption->blindIndex('Guest@Example.test'), $encryption->blindIndex(' guest@example.test '));
        $this->assertNotSame(hash('sha256', 'guest@example.test'), $encryption->blindIndex('guest@example.test'), 'Keyed, not a plain SHA-256.');
        $this->assertSame(64, strlen($encryption->blindIndex('a@b.test')));
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
