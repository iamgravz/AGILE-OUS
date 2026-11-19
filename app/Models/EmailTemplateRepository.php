<?php

declare(strict_types=1);

namespace App\Models;

final class EmailTemplateRepository extends BaseRepository
{
    protected string $table = 'email_templates';
    protected string $primaryKey = 'template_id';

    /** @return list<array<string, mixed>> */
    public function allByCode(): array
    {
        return $this->pdo()->query('SELECT template_id, code, subject, updated_at FROM email_templates ORDER BY code')->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->findBy(['code' => $code])[0] ?? null;
    }
}
