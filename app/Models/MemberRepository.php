<?php

declare(strict_types=1);

namespace App\Models;

final class MemberRepository extends BaseRepository
{
    // A member who has not registered has no user row, so their details come from the application (BR-18).
    private const DETAILED_FROM = 'FROM members m
             LEFT JOIN users u ON u.user_id = m.user_id
             LEFT JOIN roles r ON r.role_id = u.role_id
             LEFT JOIN applicants ap ON ap.applicant_id = m.applicant_id
             LEFT JOIN positions p ON p.position_id = m.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id';

    protected string $table = 'members';
    protected string $primaryKey = 'member_id';

    public function activeCount(): int
    {
        return (int) $this->pdo()->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findByUser(int $userId): ?array
    {
        return $this->findBy(['user_id' => $userId])[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function findByApplicant(int $applicantId): ?array
    {
        return $this->findBy(['applicant_id' => $applicantId])[0] ?? null;
    }

    /** @return array<string, mixed>|null An approved applicant who has no account yet, with their application details. */
    public function findUnregisteredByStudentNo(string $studentNo): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT m.member_id, m.member_no, ap.email, ap.first_name
             FROM members m
             JOIN applicants ap ON ap.applicant_id = m.applicant_id
             WHERE ap.student_no = ? AND m.user_id IS NULL AND ap.user_id IS NULL
             LIMIT 1'
        );
        $statement->execute([$studentNo]);

        return $statement->fetch() ?: null;
    }

    /**
     * Approved applicants still not registered, at the first reminder after 7 days and the second after 14.
     *
     * @return list<array<string, mixed>> With how many reminders each will have had.
     */
    public function dueForRegistrationReminder(string $today): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT m.member_id, m.member_no, m.joined_at, ap.email, ap.first_name, ap.student_no,
                    IF(m.joined_at <= DATE_SUB(?, INTERVAL 14 DAY), 2, 1) AS reminders_after
             FROM members m
             JOIN applicants ap ON ap.applicant_id = m.applicant_id
             WHERE m.user_id IS NULL AND m.registration_reminders < IF(m.joined_at <= DATE_SUB(?, INTERVAL 14 DAY), 2, 1)
               AND m.joined_at <= DATE_SUB(?, INTERVAL 7 DAY)
             ORDER BY m.member_id'
        );
        $statement->execute([$today, $today, $today]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null The member with name, role, position and committee added. */
    public function findDetailed(int $memberId): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT m.*, COALESCE(u.first_name, ap.first_name) AS first_name, COALESCE(u.last_name, ap.last_name) AS last_name,
                    COALESCE(u.email, ap.email) AS email, COALESCE(u.student_no, ap.student_no) AS student_no, u.photo_path,
                    COALESCE(r.slug, 'general_member') AS role_slug, COALESCE(r.role_name, 'General Member') AS role_name,
                    p.title AS position_title, p.role_type, p.committee_id, c.name AS committee_name
             " . self::DETAILED_FROM . '
             WHERE m.member_id = ?'
        );
        $statement->execute([$memberId]);

        return $statement->fetch() ?: null;
    }

    /** Holds the member's row until the transaction ends, so two changes to one member cannot overlap. */
    public function lockRow(int $memberId): void
    {
        $this->pdo()->prepare('SELECT member_id FROM members WHERE member_id = ? FOR UPDATE')->execute([$memberId]);
    }

    /**
     * @param array{q?: string, type?: string, committee_id?: int, role_status?: string, registration?: string} $filters
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function search(array $filters, int $page, int $perPage = 20): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (isset($filters['q'])) {
            $like = '%' . strtr($filters['q'], ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
            $conditions[] = "(CONCAT(COALESCE(u.first_name, ap.first_name), ' ', COALESCE(u.last_name, ap.last_name)) LIKE ? ESCAPE '|' OR m.member_no LIKE ? ESCAPE '|' OR COALESCE(u.student_no, ap.student_no) LIKE ? ESCAPE '|')";
            array_push($params, $like, $like, $like);
        }

        if (isset($filters['type'])) {
            $conditions[] = 'm.membership_type = ?';
            $params[] = $filters['type'];
        }

        if (isset($filters['committee_id'])) {
            // Their main position's committee, or their second one (member_committees).
            $conditions[] = '(p.committee_id = ? OR EXISTS (SELECT 1 FROM member_committees mc WHERE mc.member_id = m.member_id AND mc.committee_id = ?))';
            $params[] = $filters['committee_id'];
            $params[] = $filters['committee_id'];
        }

        if (($filters['registration'] ?? null) === 'unregistered') {
            $conditions[] = 'm.user_id IS NULL';
        }

        if (($filters['role_status'] ?? null) === 'none') {
            $conditions[] = 'm.role_status IS NULL';
        } elseif (isset($filters['role_status'])) {
            $conditions[] = 'm.role_status = ?';
            $params[] = $filters['role_status'];
        }

        $where = implode(' AND ', $conditions);
        $count = $this->pdo()->prepare('SELECT COUNT(*) ' . self::DETAILED_FROM . " WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers.
        $rows = $this->pdo()->prepare(
            "SELECT m.member_id, m.member_no, m.membership_type, m.role_status, m.joined_at,
                    COALESCE(u.first_name, ap.first_name) AS first_name, COALESCE(u.last_name, ap.last_name) AS last_name,
                    COALESCE(u.student_no, ap.student_no) AS student_no, COALESCE(r.role_name, 'General Member') AS role_name,
                    p.title AS position_title, c.name AS committee_name
             " . self::DETAILED_FROM . "
             WHERE {$where} ORDER BY last_name, first_name, m.member_id LIMIT {$perPage} OFFSET {$offset}"
        );
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }

    /** The next AGILE-{year}-{0001}. Locks the year's rows, so two approvals cannot take one number. Use in a transaction. */
    public function nextMemberNo(int $year): string
    {
        $prefix = "AGILE-{$year}-";
        $statement = $this->pdo()->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(member_no, ?) AS UNSIGNED)), 0) FROM members WHERE member_no LIKE ? FOR UPDATE'
        );
        $statement->execute([strlen($prefix) + 1, $prefix . '%']);

        return sprintf('%s%04d', $prefix, (int) $statement->fetchColumn() + 1);
    }

    public function addHistory(int $memberId, ?string $oldRole, string $newRole, string $reason, int $changedBy): void
    {
        $this->pdo()->prepare(
            'INSERT INTO membership_status_history (member_id, old_role, new_role, reason, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$memberId, $oldRole === null ? null : mb_substr($oldRole, 0, 60), mb_substr($newRole, 0, 60), mb_substr($reason, 0, 255), $changedBy, date('Y-m-d H:i:s')]);
    }

    /** @return list<array<string, mixed>> Oldest first, with who made each change. */
    public function history(int $memberId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT h.old_role, h.new_role, h.reason, h.changed_at, CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM membership_status_history h
             JOIN users u ON u.user_id = h.changed_by
             WHERE h.member_id = ?
             ORDER BY h.changed_at, h.history_id"
        );
        $statement->execute([$memberId]);

        return $statement->fetchAll();
    }
}
