<?php

declare(strict_types=1);

namespace App\Models;

final class EvaluationRepository extends BaseRepository
{
    protected string $table = 'evaluations';
    protected string $primaryKey = 'evaluation_id';

    /** @return list<array<string, mixed>> With each evaluator's name, oldest first. */
    public function forApplication(int $applicationId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS evaluator_name
             FROM evaluations e
             JOIN users u ON u.user_id = e.evaluator_id
             WHERE e.application_id = ?
             ORDER BY e.evaluation_id"
        );
        $statement->execute([$applicationId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findOne(int $applicationId, int $evaluatorId): ?array
    {
        return $this->findBy(['application_id' => $applicationId, 'evaluator_id' => $evaluatorId])[0] ?? null;
    }

    /**
     * Applications still open for evaluation, with this evaluator's score and the totals.
     *
     * @param array{q?: string, vacancy_only?: bool} $filters
     * @param list<string> $statuses
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function queue(array $filters, array $statuses, int $evaluatorId, int $page, int $perPage = 20): array
    {
        $conditions = ['a.status IN (' . implode(', ', array_fill(0, count($statuses), '?')) . ')', '(a.user_id IS NULL OR a.user_id <> ?)'];
        $params = [...$statuses, $evaluatorId];

        if (($filters['vacancy_only'] ?? false) === true) {
            $conditions[] = 'a.vacancy_id IS NOT NULL';
        }

        if (isset($filters['q'])) {
            $like = '%' . strtr($filters['q'], ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
            $conditions[] = "(CONCAT(COALESCE(u.first_name, ap.first_name), ' ', COALESCE(u.last_name, ap.last_name)) LIKE ? ESCAPE '|' OR a.tracking_code LIKE ? ESCAPE '|')";
            array_push($params, $like, $like);
        }

        $where = implode(' AND ', $conditions);
        $from = 'FROM membership_applications a LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id LEFT JOIN users u ON u.user_id = COALESCE(a.user_id, ap.user_id) LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id';
        $count = $this->pdo()->prepare("SELECT COUNT(*) {$from} WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers.
        $rows = $this->pdo()->prepare(
            "SELECT a.application_id, a.tracking_code, a.status, a.submitted_at, COALESCE(u.first_name, ap.first_name) AS first_name, COALESCE(u.last_name, ap.last_name) AS last_name, v.title AS vacancy_title,
                    (SELECT e.score FROM evaluations e WHERE e.application_id = a.application_id AND e.evaluator_id = ?) AS my_score,
                    (SELECT COUNT(*) FROM evaluations e WHERE e.application_id = a.application_id) AS evaluation_count,
                    (SELECT AVG(e.score) FROM evaluations e WHERE e.application_id = a.application_id) AS average_score
             {$from} WHERE {$where} ORDER BY a.submitted_at, a.application_id LIMIT {$perPage} OFFSET {$offset}"
        );
        $rows->execute([$evaluatorId, ...$params]);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }
}
