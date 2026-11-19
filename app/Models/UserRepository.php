<?php

declare(strict_types=1);

namespace App\Models;

final class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected string $primaryKey = 'user_id';

    /** @return array<string, mixed>|null The user with role_slug added. */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT u.*, r.slug AS role_slug
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE u.email = ?
             LIMIT 1'
        );
        $statement->execute([$email]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null The user with role_slug and role_name added. */
    public function findWithRole(int $userId): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT u.*, r.slug AS role_slug, r.role_name
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE u.user_id = ?
             LIMIT 1'
        );
        $statement->execute([$userId]);

        return $statement->fetch() ?: null;
    }

    /**
     * Active seeded demo accounts, for the local login switcher.
     *
     * @return list<array<string, mixed>>
     */
    public function activeDemoUsers(): array
    {
        return $this->pdo()->query(
            "SELECT u.email, u.first_name, u.last_name, r.role_name
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE u.email LIKE '%@agile.test' AND u.status = 'active'
             ORDER BY u.user_id"
        )->fetchAll();
    }

    /** Serializes one user's submissions, so two requests cannot both pass the BR-02 check. */
    public function lockRow(int $userId): void
    {
        $this->pdo()->prepare('SELECT user_id FROM users WHERE user_id = ? FOR UPDATE')->execute([$userId]);
    }

    /** @return list<array<string, mixed>> BR-21: accounts waiting for the MSW Committee Head, oldest first. */
    public function pendingAccounts(): array
    {
        return $this->pdo()->query(
            "SELECT u.user_id, u.first_name, u.last_name, u.email, u.student_no, u.created_at, m.member_no,
                    (SELECT a.application_id FROM membership_applications a
                     LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
                     WHERE COALESCE(a.user_id, ap.user_id) = u.user_id AND a.status = 'approved'
                     ORDER BY a.decided_at DESC LIMIT 1) AS approved_application_id
             FROM users u
             LEFT JOIN members m ON m.user_id = u.user_id
             WHERE u.status = 'pending'
             ORDER BY u.created_at, u.user_id"
        )->fetchAll();
    }

    public function hasMemberRecord(int $userId): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM members WHERE user_id = ?');
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function studentNoExists(string $studentNo): bool
    {
        return $this->findBy(['student_no' => $studentNo]) !== [];
    }

    /**
     * Adds one failure and locks the account when $maxFailures is reached. A lock that has run out is
     * cleared first, so the user starts again with a full set of attempts.
     */
    public function recordFailedLogin(int $userId, int $maxFailures, string $lockedUntil): bool
    {
        $now = date('Y-m-d H:i:s');
        $clear = $this->pdo()->prepare(
            'UPDATE users SET failed_logins = 0, locked_until = NULL WHERE user_id = ? AND locked_until IS NOT NULL AND locked_until <= ?'
        );
        $clear->execute([$userId, $now]);

        $failure = $this->pdo()->prepare('UPDATE users SET failed_logins = LEAST(failed_logins + 1, 255) WHERE user_id = ?');
        $failure->execute([$userId]);

        $lock = $this->pdo()->prepare(
            'UPDATE users SET locked_until = ? WHERE user_id = ? AND failed_logins >= ? AND locked_until IS NULL'
        );
        $lock->execute([$lockedUntil, $userId, $maxFailures]);

        return $lock->rowCount() === 1;
    }

    /** @return list<array<string, mixed>> Active officers and staff, for choosing an interviewer. */
    public function activeInternal(): array
    {
        return $this->pdo()->query(
            "SELECT u.user_id, u.first_name, u.last_name, r.role_name
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE u.status = 'active' AND r.slug IN ('committee_head', 'execomm', 'msw_member', 'msw_head', 'president')
             ORDER BY u.last_name, u.first_name"
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> Active users who may handle welfare cases, with role_slug. */
    public function activeWelfareHandlers(): array
    {
        return $this->pdo()->query(
            "SELECT u.user_id, u.first_name, u.last_name, r.slug AS role_slug, r.role_name
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE u.status = 'active' AND r.slug IN ('msw_head', 'president')
             ORDER BY u.last_name, u.first_name"
        )->fetchAll();
    }

    /**
     * @param array{q?: string, role?: string, status?: string} $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(array $filters, int $page, int $perPage = 20): array
    {
        $where = ["r.slug <> 'guest'"];
        $params = [];

        if (isset($filters['q'])) {
            $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.student_no LIKE ?)";
            array_push($params, "%{$filters['q']}%", "%{$filters['q']}%", "%{$filters['q']}%");
        }

        if (isset($filters['role'])) {
            $where[] = 'r.slug = ?';
            $params[] = $filters['role'];
        }

        if (($filters['status'] ?? '') === 'locked') {
            $where[] = 'u.locked_until > ?';
            $params[] = date('Y-m-d H:i:s');
        } elseif (isset($filters['status'])) {
            $where[] = 'u.status = ?';
            $params[] = $filters['status'];
        }

        $conditions = implode(' AND ', $where);
        $count = $this->pdo()->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id = u.role_id WHERE {$conditions}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $rows = $this->pdo()->prepare(
            "SELECT u.user_id, u.first_name, u.last_name, u.email, u.student_no, u.status, u.last_login_at, r.role_name,
                    (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked
             FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE {$conditions}
             ORDER BY u.last_name, u.first_name
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'lastPage' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return list<array<string, mixed>> Active accounts with a role an officer can have. */
    public function activeForHead(): array
    {
        return $this->pdo()->query(
            "SELECT u.user_id, u.first_name, u.last_name, r.role_name
             FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE u.status = 'active' AND r.slug <> 'guest'
             ORDER BY u.last_name, u.first_name"
        )->fetchAll();
    }

    /** @return list<int> */
    public function activeIdsByRole(string $roleSlug): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT u.user_id
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE r.slug = ? AND u.status = 'active'"
        );
        $statement->execute([$roleSlug]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }
}
