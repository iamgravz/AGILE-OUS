<?php

declare(strict_types=1);

namespace App\Models;

final class AcademicTermRepository extends BaseRepository
{
    protected string $table = 'academic_terms';
    protected string $primaryKey = 'term_id';

    /** @return list<array<string, mixed>> */
    public function allNewestFirst(): array
    {
        return $this->pdo()->query(
            'SELECT t.*, (SELECT COUNT(*) FROM eligibility_screenings s WHERE s.term_id = t.term_id) AS screening_count
             FROM academic_terms t
             ORDER BY t.academic_year DESC, t.term_id DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        return $this->findBy(['is_current' => 1])[0] ?? null;
    }

    /** Only one term is current. The caller holds the transaction. */
    public function makeCurrent(int $id): void
    {
        $this->pdo()->exec('UPDATE academic_terms SET is_current = 0 WHERE is_current = 1');
        $this->update($id, ['is_current' => 1]);
    }
}
