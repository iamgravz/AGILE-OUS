<?php

declare(strict_types=1);

namespace App\Models;

final class ApplicationAnswerRepository extends BaseRepository
{
    protected string $table = 'application_answers';
    protected string $primaryKey = 'answer_id';

    /** @return array<int, string> question_id => answer */
    public function forApplication(int $applicationId): array
    {
        $statement = $this->pdo()->prepare('SELECT question_id, answer_text FROM application_answers WHERE application_id = ?');
        $statement->execute([$applicationId]);
        $answers = [];

        foreach ($statement as $row) {
            $answers[(int) $row['question_id']] = (string) $row['answer_text'];
        }

        return $answers;
    }

    /** The schema has no unique key on this pair, so this replaces the row. Call inside a transaction. */
    public function put(int $applicationId, int $questionId, string $answer): void
    {
        $this->pdo()->prepare('DELETE FROM application_answers WHERE application_id = ? AND question_id = ?')->execute([$applicationId, $questionId]);
        $this->insert(['application_id' => $applicationId, 'question_id' => $questionId, 'answer_text' => $answer]);
    }

    /** @param list<int> $keepQuestionIds Everything else is removed, such as answers for another position. */
    public function deleteExcept(int $applicationId, array $keepQuestionIds): void
    {
        if ($keepQuestionIds === []) {
            $this->pdo()->prepare('DELETE FROM application_answers WHERE application_id = ?')->execute([$applicationId]);

            return;
        }

        $marks = implode(',', array_fill(0, count($keepQuestionIds), '?'));
        $this->pdo()->prepare("DELETE FROM application_answers WHERE application_id = ? AND question_id NOT IN ({$marks})")
            ->execute([$applicationId, ...$keepQuestionIds]);
    }
}
