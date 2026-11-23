<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicTermRepository;
use App\Models\ApplicationAnswerRepository;
use App\Models\ApplicationQuestionRepository;

/**
 * AGILE's membership requirements, kept apart from MembershipService (ISO 25010 finding F-2). Which
 * documents an application needs: the current COR always; the previous semester copy of grades for a position, except
 * for a 1st year in a 1st semester, who has no grades yet. MembershipService keeps one-line wrappers for its callers.
 */
final class ApplicationRequirements
{
    /**
     * Every document type an application can hold. doc_type => label. School ID is not asked for;
     * it stays here so documents uploaded before then still show their name.
     */
    public const DOCUMENT_TYPES = [
        'cor' => 'Current COR (Certificate of Registration)',
        'grades' => 'Previous semester copy of grades',
        'certificate' => 'Certificates (optional)',
        'school_id' => 'School ID',
    ];

    /**
     * AGILE's membership requirements table, row by row: key => [who, required documents, grades needed, grades label].
     * The table (membership/_requirements) and the first page's checks use it.
     */
    public const REQUIREMENT_GROUPS = [
        'general' => ['General members', 'Current COR', false, null],
        'committee' => ['Committee Heads, Deputy Heads and committee members', 'Current COR and previous semester copy of grades', true, 'Previous semester copy of grades'],
        'applicant' => ['Applicants for any committee position or The Source Code', 'Current COR and previous semester copy of grades', true, 'Previous semester copy of grades'],
        'first_year_first_sem' => ['1st year, 1st semester joiners', 'Current COR only (no grades yet)', false, null],
        'first_year_second_sem' => ['1st years joining in the 2nd semester', 'Current COR and 1st semester copy of grades', true, '1st semester copy of grades'],
    ];

    /** The year level answer that, in a 1st semester, means there are no grades yet. */
    private const FIRST_YEAR = '1st Year';

    public function __construct(
        private readonly ApplicationQuestionRepository $questions = new ApplicationQuestionRepository(),
        private readonly ApplicationAnswerRepository $answers = new ApplicationAnswerRepository(),
    ) {
    }

    public static function documentLabel(string $docType): string
    {
        return self::DOCUMENT_TYPES[$docType] ?? 'Attachment';
    }

    /**
     * General Membership: the current COR. A committee or publication position (a vacancy, or a position chosen when
     * applying): the current COR and the previous semester's copy of grades, unless needsGrades() says otherwise.
     *
     * @param array<string, mixed> $application A row from MembershipApplicationRepository::findDetailed().
     * @return list<string> doc_types
     */
    public function requiredDocuments(array $application): array
    {
        return $this->needsGrades($application) ? ['cor', 'grades'] : ['cor'];
    }

    /**
     * The upload slots on the documents step: the required ones, then optional certificates.
     *
     * @param array<string, mixed> $application
     * @return list<string>
     */
    public function documentsToAsk(array $application): array
    {
        return [...$this->requiredDocuments($application), 'certificate'];
    }

    /** @param array<string, mixed> $application */
    public function needsGrades(array $application): bool
    {
        $forPosition = $application['vacancy_id'] !== null || ($application['position_id'] ?? null) !== null;

        return $forPosition && !$this->isFirstYearFirstSemester($application);
    }

    /**
     * A 1st year applying in a 1st semester: the year level from step 2, and the current academic term set by the MSW
     * Committee Head. With no current term set, grades are asked for, so nothing is skipped by mistake.
     *
     * @param array<string, mixed> $application
     */
    public function isFirstYearFirstSemester(array $application): bool
    {
        $academic = $this->questions->forRoleTypes(['academic']);
        $yearLevel = $academic === [] ? '' : (string) ($this->answers->forApplication((int) $application['application_id'])[(int) $academic[0]['question_id']] ?? '');

        return $yearLevel === self::FIRST_YEAR && $this->currentSemester() === '1st';
    }

    /** @return list<string> The year levels step 2 offers, so the first apply page asks with the same words. */
    public function yearLevelOptions(): array
    {
        $academic = $this->questions->forRoleTypes(['academic']);
        $options = $academic === [] ? [] : json_decode((string) $academic[0]['options_json'], true);

        return is_array($options) ? array_values(array_map('strval', $options)) : [];
    }

    /** The current academic term's semester ('1st', '2nd', 'summer'), or null when the MSW Committee Head set none. */
    public function currentSemester(): ?string
    {
        return (new AcademicTermRepository())->current()['semester'] ?? null;
    }

    /**
     * What the first apply page asks for a position, by year level: no grades for a 1st year in a 1st semester, the 1st
     * semester grades for a 1st year in a later one, and the previous semester's otherwise.
     *
     * @return array{needed: bool, label: string}
     */
    public function gradesForYear(string $yearLevel): array
    {
        if ($yearLevel !== self::FIRST_YEAR) {
            return ['needed' => true, 'label' => 'Previous semester copy of grades'];
        }

        return $this->currentSemester() === '1st'
            ? ['needed' => false, 'label' => 'Previous semester copy of grades']
            : ['needed' => true, 'label' => '1st semester copy of grades'];
    }
}
