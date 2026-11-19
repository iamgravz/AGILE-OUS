<?php

declare(strict_types=1);

namespace App\Models;

final class ApplicationQuestionRepository extends BaseRepository
{
    protected string $table = 'application_questions';
    protected string $primaryKey = 'question_id';

    /**
     * @param list<string> $roleTypes Fixed values from the service, placed in the query as parameters.
     * @return list<array<string, mixed>>
     */
    public function forRoleTypes(array $roleTypes): array
    {
        $marks = implode(',', array_fill(0, count($roleTypes), '?'));
        $statement = $this->pdo()->prepare(
            "SELECT * FROM application_questions WHERE role_type IN ({$marks}) ORDER BY FIELD(role_type, {$marks}), sort_order, question_id"
        );
        $statement->execute([...$roleTypes, ...$roleTypes]);

        return $statement->fetchAll();
    }
}
