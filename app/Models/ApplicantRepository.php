<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;

/** People who apply without an account (BR-18). One row per email. */
final class ApplicantRepository extends BaseRepository
{
    protected string $table = 'applicants';
    protected string $primaryKey = 'applicant_id';

    /**
     * Their email was just proven with a code, so the details they typed replace any earlier ones.
     *
     * @param array{email: string, first_name: string, last_name: string, student_no: string, contact_no?: string, year_level?: string, section?: string} $details
     * @return int The applicant's id.
     */
    public function saveVerified(array $details): int
    {
        $now = date('Y-m-d H:i:s');
        $details['student_no'] = $this->studentNo($details['student_no']);
        $existing = $this->findBy(['email' => $details['email']])[0] ?? null;
        $this->assertStudentNoFree($details['student_no'], $existing === null ? 0 : (int) $existing['applicant_id']);
        // From the first apply page; an empty value keeps what an earlier visit saved.
        $optional = static fn (string $key): ?string => ($details[$key] ?? '') === '' ? null : (string) $details[$key];

        $this->pdo()->prepare(
            'INSERT INTO applicants (email, first_name, last_name, student_no, contact_no, year_level, section, email_verified_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name),
                 student_no = VALUES(student_no), email_verified_at = VALUES(email_verified_at),
                 contact_no = COALESCE(VALUES(contact_no), contact_no), year_level = COALESCE(VALUES(year_level), year_level),
                 section = COALESCE(VALUES(section), section)'
        )->execute([$details['email'], $details['first_name'], $details['last_name'], $details['student_no'],
            $optional('contact_no'), $optional('year_level'), $optional('section'), $now]);

        return (int) $this->findBy(['email' => $details['email']])[0]['applicant_id'];
    }

    /**
     * BR-02: one student number, one applicant (uq_applicants_student_no). An empty number is saved as NULL.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): int
    {
        if (array_key_exists('student_no', $data)) {
            $data['student_no'] = $this->studentNo($data['student_no']);
            $this->assertStudentNoFree($data['student_no'], $id);
        }

        return parent::update($id, $data);
    }

    /** @throws DomainException when another applicant already has this student number */
    public function assertStudentNoFree(?string $studentNo, int $exceptId): void
    {
        if ($studentNo === null) {
            return;
        }

        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM applicants WHERE student_no = ? AND applicant_id <> ?');
        $statement->execute([$studentNo, $exceptId]);

        if ((int) $statement->fetchColumn() > 0) {
            throw new DomainException('This PUP student number is already on another application. Please use the email you applied with, or contact the MSW Committee.');
        }
    }

    private function studentNo(mixed $value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function lockRow(int $applicantId): void
    {
        $this->pdo()->prepare('SELECT applicant_id FROM applicants WHERE applicant_id = ? FOR UPDATE')->execute([$applicantId]);
    }
}
