<?php

declare(strict_types=1);

namespace App\Models;

final class EmailDraftRepository extends BaseRepository
{
    protected string $table = 'email_drafts';
    protected string $primaryKey = 'draft_id';

    /** @return array{data: list<array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int} */
    public function listWithAuthors(int $page, int $perPage = 20): array
    {
        $page = max(1, $page);
        $total = (int) $this->pdo()->query('SELECT COUNT(*) FROM email_drafts')->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $rows = $this->pdo()->query(
            "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
             FROM email_drafts d JOIN users u ON u.user_id = d.created_by
             ORDER BY d.draft_id DESC LIMIT {$perPage} OFFSET {$offset}"
        )->fetchAll();

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'lastPage' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM email_drafts WHERE draft_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /**
     * One row per email address. People with an account are sent to through it, and the others by address.
     *
     * @return list<array{user_id: int|null, email: string, first_name: string}>
     */
    public function recipients(string $target): array
    {
        if ($target === 'applicants') {
            $rows = $this->pdo()->query(
                "SELECT NULL AS user_id, ap.email, ap.first_name
                 FROM applicants ap
                 JOIN membership_applications a ON a.applicant_id = ap.applicant_id
                 WHERE a.status IN ('submitted', 'under_review', 'for_revision')"
            )->fetchAll();

            return $this->uniqueByEmail($rows);
        }

        $where = "(u.user_id IS NULL OR u.status <> 'disabled')";
        $params = [];

        if ($target === 'officers') {
            $where .= " AND m.membership_type = 'officer' AND m.role_status = 'active'";
        } elseif (preg_match('/^committee:(\d+)$/', $target, $match) === 1) {
            $where .= " AND m.role_status = 'active' AND p.committee_id = ?";
            $params[] = (int) $match[1];
        } elseif ($target !== 'all_members') {
            return [];
        }

        $statement = $this->pdo()->prepare(
            "SELECT m.user_id, COALESCE(u.email, ap.email) AS email, COALESCE(u.first_name, ap.first_name) AS first_name
             FROM members m
             LEFT JOIN users u ON u.user_id = m.user_id
             LEFT JOIN applicants ap ON ap.applicant_id = m.applicant_id
             LEFT JOIN positions p ON p.position_id = m.position_id
             WHERE {$where} AND COALESCE(u.email, ap.email) IS NOT NULL"
        );
        $statement->execute($params);

        return $this->uniqueByEmail($statement->fetchAll());
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{user_id: int|null, email: string, first_name: string}>
     */
    private function uniqueByEmail(array $rows): array
    {
        $unique = [];

        foreach ($rows as $row) {
            $unique[mb_strtolower($row['email'])] ??= $row;
        }

        return array_values($unique);
    }
}
