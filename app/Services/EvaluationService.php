<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Models\EvaluationRepository;
use App\Models\InterviewRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\SettingRepository;
use App\Models\VacancyRepository;
use DomainException;
use RuntimeException;

/** Rubric evaluations (FR-REC-05, BR-05): one per evaluator per application, editable until a decision is made. */
final class EvaluationService
{
    /** Before a decision. Approved, rejected and withdrawn applications are locked. */
    public const OPEN_STATUSES = ['submitted', 'under_review', 'for_revision'];
    public const RECOMMENDATIONS = ['recommend' => 'Recommend', 'hold' => 'Hold', 'not_recommend' => 'Not recommend'];

    public function __construct(
        private readonly EvaluationRepository $evaluations = new EvaluationRepository(),
        private readonly MembershipApplicationRepository $applications = new MembershipApplicationRepository(),
    ) {
    }

    /**
     * The weighted average on the rubric's own scale, always calculated here and never taken from the form.
     *
     * @param array{scale: array{min: int, max: int}, criteria: list<array{key: string, label: string, weight: int|float}>} $rubric
     * @param array<string, mixed> $scores criterion key => score
     * @return array{score: float, rubric: array<string, mixed>} rubric keeps the labels and weights used, for when the setting changes later.
     */
    public static function calculate(array $rubric, array $scores): array
    {
        $total = 0.0;
        $weights = 0.0;
        $criteria = [];

        foreach ($rubric['criteria'] as $criterion) {
            $raw = $scores[$criterion['key']] ?? null;

            if (!is_string($raw) && !is_int($raw) || preg_match('/^\d+$/', (string) $raw) !== 1 || (int) $raw < $rubric['scale']['min'] || (int) $raw > $rubric['scale']['max']) {
                throw new DomainException("Please score \"{$criterion['label']}\" from {$rubric['scale']['min']} to {$rubric['scale']['max']}.");
            }

            $total += (int) $raw * $criterion['weight'];
            $weights += $criterion['weight'];
            $criteria[] = $criterion + ['score' => (int) $raw];
        }

        return ['score' => round($total / $weights, 2), 'rubric' => ['scale' => $rubric['scale'], 'criteria' => $criteria]];
    }

    /** @return array{scale: array{min: int, max: int}, criteria: list<array{key: string, label: string, weight: int|float}>} */
    public function rubric(): array
    {
        $rubric = json_decode((string) (new SettingRepository())->value('evaluation.rubric'), true);
        $weights = array_sum(array_column($rubric['criteria'] ?? [], 'weight'));

        if (!isset($rubric['scale']['min'], $rubric['scale']['max']) || $weights <= 0) {
            throw new RuntimeException('The evaluation.rubric setting is missing or not valid.');
        }

        return $rubric;
    }

    /**
     * What the Evaluations tab shows.
     *
     * @return array<string, mixed>
     */
    public function panel(array $application): array
    {
        authorize('evaluation.view');

        $rows = $this->evaluations->forApplication((int) $application['application_id']);
        $userId = (int) current_user_id();
        $mine = null;

        foreach ($rows as $row) {
            if ((int) $row['evaluator_id'] === $userId) {
                $mine = $row;
            }
        }

        $counts = array_fill_keys(array_keys(self::RECOMMENDATIONS), 0);

        foreach ($rows as $row) {
            $counts[$row['recommendation']]++;
        }

        return [
            'evaluations' => $rows,
            'averageScore' => $rows === [] ? null : round(array_sum(array_column($rows, 'score')) / count($rows), 2),
            'recommendationCounts' => $counts,
            'myEvaluation' => $mine,
            'canEvaluate' => $this->canEvaluate($application),
            'rubric' => $this->rubric(),
        ];
    }

    public function canEvaluate(array $application): bool
    {
        return can('evaluation.submit')
            && in_array($application['status'], self::OPEN_STATUSES, true)
            && (int) $application['user_id'] !== current_user_id()
            && ($application['vacancy_id'] !== null || can('application.update_status'));
    }

    /**
     * A second submission by the same evaluator updates the first. The application's row lock keeps two tabs from
     * racing past the check.
     *
     * @param array<string, mixed> $scores
     * @throws DomainException
     */
    public function submit(int $applicationId, array $scores, string $recommendation, string $remarks): void
    {
        authorize('evaluation.submit');

        if (!isset(self::RECOMMENDATIONS[$recommendation])) {
            throw new DomainException('Please choose a recommendation.');
        }

        if (trim($remarks) === '') {
            throw new DomainException('Remarks are required.');
        }

        $result = self::calculate($this->rubric(), $scores);

        Database::transaction(function () use ($applicationId, $result, $recommendation, $remarks): void {
            $application = $this->applications->findForUpdate($applicationId);

            if ($application === null || $application['status'] === 'draft') {
                throw new DomainException('This application no longer exists.');
            }

            if ((new InterviewRepository())->hasConflict($applicationId, (int) current_user_id())) {
                throw new DomainException('You declared a conflict of interest for this applicant, so you cannot score them (BR-25).');
            }

            if ((int) $application['user_id'] === current_user_id()) {
                throw new ForbiddenException('application.own_review');
            }

            // Committee heads evaluate position applications. General membership has no committee behind it.
            if ($application['vacancy_id'] === null && !can('application.update_status')) {
                throw new ForbiddenException('application.review_scope');
            }

            if (!in_array($application['status'], self::OPEN_STATUSES, true)) {
                throw new DomainException('A decision was already made, so evaluations for this application are locked.');
            }

            $evaluatorId = (int) current_user_id();
            $existing = $this->evaluations->findOne($applicationId, $evaluatorId);
            $values = [
                'score' => $result['score'],
                'rubric_json' => json_encode($result['rubric'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'recommendation' => $recommendation,
                'remarks' => trim($remarks),
            ];

            if ($existing === null) {
                $this->evaluations->insert($values + ['application_id' => $applicationId, 'evaluator_id' => $evaluatorId]);
            } else {
                $this->evaluations->update((int) $existing['evaluation_id'], $values);
            }

            AuditService::log($existing === null ? 'evaluation.submitted' : 'evaluation.updated', 'membership_application', $applicationId, [
                'score' => $result['score'],
                'recommendation' => $recommendation,
            ]);
        });
    }

    /** @param array{q?: string} $filters */
    public function queue(array $filters, int $page): array
    {
        authorize('evaluation.view');

        if (!can('evaluation.submit')) {
            return ['data' => [], 'page' => 1, 'perPage' => 20, 'lastPage' => 1, 'total' => 0];
        }

        $filters['vacancy_only'] = !can('application.update_status');

        return $this->evaluations->queue($filters, self::OPEN_STATUSES, (int) current_user_id(), $page);
    }

    /**
     * The read-only committee view: this head's vacancies with the applications they may read.
     *
     * @return list<array<string, mixed>>
     */
    public function committeeVacancies(): array
    {
        authorize('evaluation.view');

        $vacancies = (new VacancyRepository())->forCommitteesHeadedBy((int) current_user_id());

        foreach ($vacancies as &$vacancy) {
            $vacancy['applications'] = $this->applications->reviewableForVacancy((int) $vacancy['vacancy_id']);
        }

        return $vacancies;
    }
}
