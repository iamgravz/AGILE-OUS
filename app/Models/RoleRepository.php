<?php

declare(strict_types=1);

namespace App\Models;

final class RoleRepository extends BaseRepository
{
    protected string $table = 'roles';
    protected string $primaryKey = 'role_id';

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy(['slug' => $slug])[0] ?? null;
    }
}
