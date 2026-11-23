<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MembershipApplicationRepository;
use DomainException;
use PDO;

/**
 * The MSW interviewer's assessment of an applicant, after the interview, from AGILE's own Applicant Evaluation sheet
 * (Nov 23, 2026): each criterion rated 1 to 5 with a remark, totals per section, a recommendation, strengths and areas
 * for development. The Committee Head reads it on the Applicant profile tab and still makes the decision (BR-20).
 */
final class AssessmentService
{
    /** Section => [label, criteria]. Each criterion is rated 1 (lowest) to 5 (highest). */
    public const SECTIONS = [
        'application' => ['Application review', [
            'Motivation for Joining AGILE',
            'Relevance of Skills/Experience',
            'Understanding of Applied Role',
            'Potential Contribution to AGILE',
            'Availability and Commitment',
        ]],
        'interview' => ['Interview assessment', [
            'Communication',
            'Clarity and Confidence',
            'Understanding of Responsibilities',
            'Teamwork and Collaboration',
            'Initiative and Problem-Solving',
            'Commitment and Reliability',
        ]],
        'role' => ['Role-specific assessment', [
            'Role-Specific Assessment',
        ]],
    ];

    public const RECOMMENDATIONS = [
        'highly_recommended' => 'Highly Recommended',
        'recommended' => 'Recommended',
        'recommended_with_considerations' => 'Recommended with Considerations',
        'further_evaluation' => 'Further Evaluation',
        'not_recommended' => 'Not Recommended',
    ];

    public const RATINGS = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'];

    /** An application in one of these states is decided or closed, so its assessment is final. */
    private const CLOSED = ['draft', 'approved', 'rejected', 'withdrawn'];

    private const TEXT_LIMIT = 2000;
    private const REMARK_LIMIT = 500;

    public function __construct(private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository())
    {
    }

    /** The most a section, or the whole assessment, can score. */
    public static function maxScore(?string $section = null): int
    {
        if ($section !== null) {
            return count(self::SECTIONS[$section][1]) * 5;
        }

        return array_sum(array_map(static fn (array $s): int => count($s[1]) * 5, self::SECTIONS));
    }

    /**
     * The interview questions for this applicant, from AGILE's question bank: the core ones, then those for what they
     * applied for, their committee and their position.
     *
     * @param array<string, mixed> $application from MembershipApplicationRepository::findDetailed()
     * @return list<array<string, mixed>>
     */
    public function questionBank(array $application): array
    {
        $type = (string) ($application['role_type'] ?? '');
        $type = $type === '' || $type === 'officer' ? 'general' : $type;
        $statement = Database::connection()->prepare(
            "SELECT * FROM interview_questions
             WHERE applicant_type = 'core'
                OR (applicant_type = ? AND (committee IS NULL OR committee = ?) AND (position IS NULL OR position = ?))
             ORDER BY applicant_type = 'core' DESC, sort_order, question_id"
        );
        $statement->execute([$type, (string) ($application['committee_name'] ?? ''), (string) ($application['position_title'] ?? '')]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null The assessment with its ratings decoded, and who wrote it. */
    public function forApplication(int $applicationId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS evaluator_name
             FROM applicant_assessments s JOIN users u ON u.user_id = s.evaluator_id
             WHERE s.application_id = ?"
        );
        $statement->execute([$applicationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['ratings'] = json_decode((string) $row['ratings_json'], true) ?: [];

        return $row;
    }

    /** Whether the interview happened: a passed or failed result, not a no-show. */
    public function hasCompletedInterview(int $applicationId): bool
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM interviews WHERE application_id = ? AND result IN ('passed', 'failed')");
        $statement->execute([$applicationId]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * What the Interviews and Applicant profile tabs show.
     *
     * @param array<string, mixed> $application
     * @return array<string, mixed>
     */
    public function panel(array $application): array
    {
        $id = (int) $application['application_id'];

        return [
            'assessment' => $this->forApplication($id),
            'questionBank' => $this->questionBank($application),
            'canAssess' => can('interview.manage') && $this->hasCompletedInterview($id) && !in_array($application['status'], self::CLOSED, true),
        ];
    }

    /**
     * Saves the assessment (one per application; saving again replaces it, with the saver as evaluator).
     *
     * @param array<string, array<int, mixed>> $ratings section => criterion index => 1..5
     * @param array<string, array<int, mixed>> $remarks section => criterion index => text
     * @param array{recommendation: string, strengths: string, development: string, remarks: string} $summary
     * @throws DomainException
     */
    public function save(int $applicationId, array $ratings, array $remarks, array $summary): void
    {
        authorize('interview.manage');

        $application = $this->applications->find($applicationId);

        if ($application === null) {
            throw new DomainException('Application not found.');
        }

        if (in_array($application['status'], self::CLOSED, true)) {
            throw new DomainException('This application is already decided or closed, so its assessment can no longer change.');
        }

        if (!$this->hasCompletedInterview($applicationId)) {
            throw new DomainException('Record the interview result (passed or failed) before the assessment.');
        }

        if (!isset(self::RECOMMENDATIONS[$summary['recommendation']])) {
            throw new DomainException('Please choose a recommendation.');
        }

        $stored = [];
        $scores = [];

        foreach (self::SECTIONS as $section => [, $criteria]) {
            $scores[$section] = 0;

            foreach ($criteria as $index => $criterion) {
                $rating = filter_var($ratings[$section][$index] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

                if ($rating === false) {
                    throw new DomainException("Rate \"{$criterion}\" from 1 to 5.");
                }

                $remark = trim((string) ($remarks[$section][$index] ?? ''));

                if (mb_strlen($remark) > self::REMARK_LIMIT) {
                    throw new DomainException("The remark for \"{$criterion}\" is too long (500 characters at most).");
                }

                $stored[$section][] = ['criterion' => $criterion, 'rating' => $rating, 'remark' => $remark];
                $scores[$section] += $rating;
            }
        }

        foreach (['strengths', 'development', 'remarks'] as $field) {
            if (mb_strlen(trim($summary[$field])) > self::TEXT_LIMIT) {
                throw new DomainException('Strengths, areas for development and remarks are 2,000 characters at most each.');
            }
        }

        $row = [
            'evaluator_id' => current_user_id(),
            'ratings_json' => json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'application_score' => $scores['application'],
            'interview_score' => $scores['interview'],
            'role_score' => $scores['role'],
            'total_score' => array_sum($scores),
            'recommendation' => $summary['recommendation'],
            'strengths' => self::nullable($summary['strengths']),
            'development' => self::nullable($summary['development']),
            'remarks' => self::nullable($summary['remarks']),
        ];

        Database::transaction(function () use ($applicationId, $row): void {
            $db = Database::connection();
            $existing = $db->prepare('SELECT assessment_id FROM applicant_assessments WHERE application_id = ? FOR UPDATE');
            $existing->execute([$applicationId]);
            $assessmentId = $existing->fetchColumn();

            if ($assessmentId === false) {
                $columns = array_keys($row);
                $db->prepare('INSERT INTO applicant_assessments (application_id, ' . implode(', ', $columns) . ') VALUES (?' . str_repeat(', ?', count($columns)) . ')')
                    ->execute([$applicationId, ...array_values($row)]);
            } else {
                $set = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", array_keys($row)));
                $db->prepare("UPDATE applicant_assessments SET {$set} WHERE assessment_id = ?")->execute([...array_values($row), (int) $assessmentId]);
            }

            AuditService::log($assessmentId === false ? 'assessment.created' : 'assessment.updated', 'membership_application', $applicationId, [
                'total' => $row['total_score'],
                'recommendation' => $row['recommendation'],
            ]);
        });
    }

    private static function nullable(string $text): ?string
    {
        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
