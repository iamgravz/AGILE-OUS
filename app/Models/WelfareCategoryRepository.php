<?php

declare(strict_types=1);

namespace App\Models;

final class WelfareCategoryRepository extends BaseRepository
{
    protected string $table = 'welfare_categories';
    protected string $primaryKey = 'category_id';

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo()->query('SELECT category_id, name FROM welfare_categories ORDER BY category_id')->fetchAll();
    }
}
