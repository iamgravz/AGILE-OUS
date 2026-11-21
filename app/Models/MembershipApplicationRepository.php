<?php

declare(strict_types=1);

namespace App\Models;

final class MembershipApplicationRepository extends BaseRepository
{
    protected string $table = 'membership_applications';
    protected string $primaryKey = 'application_id';

    /**
     * What an application is for, shown as vacancy_title everywhere: the position, or "Committee Member, <committee>"
     * for someone applying to join a committee. Null still means General Membership. Needs JOIN_CHOSEN and v.
     */
    public const APPLIED_FOR = "COALESCE(v.title, CONCAT('Committee Member, ', ac.name))";
    public const JOIN_CHOSEN = 'LEFT JOIN committees ac ON ac.committee_id = a.committee_id';

    /** @return array<string, mixed>|null The user's unfinished application. A user has at most one. */
    public function findDraft(int $userId): ?array
    {
        return $this->findBy(['user_id' => $userId, 'status' => 'draft'])[0] ?? null;
    }

    /** @return array<string, mixed>|null The applicant's unfinished application. They have at most one. */
    public function findDraftForApplicant(int $applicantId): ?array
    {
        return $this->findBy(['applicant_id' => $applicantId, 'status' => 'draft'])[0] ?? null;
    }

    /**
     * The application with its vacancy, position and owner details added. owner_user_id is the logged-in applicant,
     * or the account created for an applicant on approval, and is null before that.
     *
     * @return array<string, mixed>|null
     */
    public function findDetailed(int $id): ?array
    {
        // A committee comes from the vacancy's position, or from the committee chosen when applying as a Committee Member.
        $statement = $this->pdo()->prepare(
            'SELECT a.*, ' . self::APPLIED_FOR . ' AS vacancy_title, v.status AS vacancy_status, v.closes_at AS vacancy_closes_at,
                    COALESCE(p.role_type, IF(a.committee_id IS NULL, NULL, \'committee_member\')) AS role_type,
                    COALESCE(a.user_id, ap.user_id) AS owner_user_id, c.name AS committee_name,
                    IF(hu.status = \'active\', c.head_user_id, NULL) AS committee_head_id,
                    CONCAT(hu.first_name, \' \', hu.last_name) AS committee_head_name
             FROM membership_applications a
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             LEFT JOIN positions p ON p.position_id = v.position_id
             ' . self::JOIN_CHOSEN . '
             LEFT JOIN committees c ON c.committee_id = COALESCE(p.committee_id, a.committee_id)
             LEFT JOIN users hu ON hu.user_id = c.head_user_id
             WHERE a.application_id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM membership_applications WHERE application_id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> Newest first, without drafts' tracking codes needing special handling. */
    public function forUser(int $userId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT a.*, ' . self::APPLIED_FOR . ' AS vacancy_title
             FROM membership_applications a
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             ' . self::JOIN_CHOSEN . '
             WHERE a.user_id = ? OR ap.user_id = ?
             ORDER BY a.application_id DESC'
        );
        $statement->execute([$userId, $userId]);

        return $statement->fetchAll();
    }

    /** Applications staff still have to act on, submitted before this date. */
    public function countPendingSince(string $before): int
    {
        $statement = $this->pdo()->prepare("SELECT COUNT(*) FROM membership_applications WHERE status IN ('submitted', 'under_review') AND submitted_at < ?");
        $statement->execute([$before]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> Each For Revision application, with the remarks from when it was returned. */
    public function forRevisionWithRemarks(): array
    {
        return $this->pdo()->query(
            "SELECT a.*, (SELECT h.remarks FROM application_status_history h
                          WHERE h.application_id = a.application_id AND h.new_status = 'for_revision'
                          ORDER BY h.id DESC LIMIT 1) AS revision_remarks
             FROM membership_applications a
             WHERE a.status = 'for_revision'"
        )->fetchAll();
    }

    /**
     * BR-02: the user's active application for this vacancy, where null means general membership.
     *
     * @return array<string, mixed>|null
     */
    public function findActive(int $userId, ?int $vacancyId, int $exceptId): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT * FROM membership_applications
             WHERE user_id = ? AND application_id <> ?
               AND status IN ('submitted', 'under_review', 'for_revision', 'waitlisted', 'approved')
               AND " . ($vacancyId === null ? 'vacancy_id IS NULL' : 'vacancy_id = ?') . ' LIMIT 1'
        );
        $statement->execute($vacancyId === null ? [$userId, $exceptId] : [$userId, $exceptId, $vacancyId]);

        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null The user's other committee application still waiting for a decision. */
    public function findPendingCommittee(int $userId, int $exceptId): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT * FROM membership_applications
             WHERE user_id = ? AND application_id <> ? AND committee_id IS NOT NULL
               AND status IN ('submitted', 'under_review', 'for_revision', 'waitlisted') LIMIT 1"
        );
        $statement->execute([$userId, $exceptId]);

        return $statement->fetch() ?: null;
    }

    /**
     * @param array{status?: string, vacancy_id?: int} $filters
     * @return list<array<string, mixed>> Applications to positions in the committees this person heads.
     */
    public function forCommitteeHead(int $userId, array $filters): array
    {
        $conditions = ['c.head_user_id = ?', "a.status <> 'draft'"];
        $params = [$userId];

        if (isset($filters['status'])) {
            $conditions[] = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (isset($filters['vacancy_id'])) {
            $conditions[] = 'a.vacancy_id = ?';
            $params[] = $filters['vacancy_id'];
        }

        // Position applications in their committees, and people (with or without an account) applying to join as a Committee Member.
        $statement = $this->pdo()->prepare(
            'SELECT a.application_id, a.tracking_code, a.status, a.submitted_at, a.vacancy_id, a.committee_id, ' . self::APPLIED_FOR . ' AS vacancy_title,
                    COALESCE(u.first_name, ap.first_name) AS first_name, COALESCE(u.last_name, ap.last_name) AS last_name,
                    (SELECT COUNT(*) FROM evaluations e WHERE e.application_id = a.application_id) AS evaluation_count,
                    (SELECT AVG(e.score) FROM evaluations e WHERE e.application_id = a.application_id) AS average_score
             FROM membership_applications a
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             LEFT JOIN users u ON u.user_id = COALESCE(a.user_id, ap.user_id)
             LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             LEFT JOIN positions p ON p.position_id = v.position_id
             ' . self::JOIN_CHOSEN . '
             JOIN committees c ON c.committee_id = COALESCE(p.committee_id, a.committee_id)
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY a.submitted_at DESC, a.application_id DESC'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> A member who has not registered yet: what they applied with. */
    public function forApplicant(int $applicantId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT a.*, ' . self::APPLIED_FOR . ' AS vacancy_title
             FROM membership_applications a
             LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             ' . self::JOIN_CHOSEN . '
             WHERE a.applicant_id = ?
             ORDER BY a.application_id DESC'
        );
        $statement->execute([$applicantId]);

        return $statement->fetchAll();
    }

    /**
     * BR-02: one active general membership application per email or student number, whether it was filed with an
     * account or without one.
     *
     * @return array<string, mixed>|null With owner_email added.
     */
    public function findActiveGeneral(string $email, ?string $studentNo, int $exceptId): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT a.*, COALESCE(ap.email, u.email) AS owner_email
             FROM membership_applications a
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             LEFT JOIN users u ON u.user_id = a.user_id
             WHERE a.application_id <> ? AND a.vacancy_id IS NULL
               AND a.status IN ('submitted', 'under_review', 'for_revision', 'waitlisted', 'approved')
               AND (COALESCE(ap.email, u.email) = ? OR COALESCE(ap.student_no, u.student_no) = ?)
             LIMIT 1"
        );
        $statement->execute([$exceptId, $email, $studentNo]);

        return $statement->fetch() ?: null;
    }

    /**
     * The staff list. Drafts are private to the applicant, so they never appear.
     *
     * @param array{status?: string, type?: string, vacancy_id?: int, from?: string, to?: string, q?: string, reviewed_only?: bool} $filters
     * @param string $sort A key of $columns below. Anything else falls back to the newest first.
     * @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int}
     */
    public function searchForStaff(array $filters, string $sort, string $direction, int $page, int $perPage = 20): array
    {
        $columns = [
            'submitted_at' => 'a.submitted_at',
            'status' => 'a.status',
            'tracking_code' => 'a.tracking_code',
            'applicant' => 'COALESCE(u.last_name, ap.last_name)',
        ];
        $conditions = ["a.status <> 'draft'"];
        $params = [];

        if (isset($filters['status'])) {
            $conditions[] = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (($filters['type'] ?? null) === 'general') {
            $conditions[] = 'a.vacancy_id IS NULL AND a.committee_id IS NULL';
        } elseif (($filters['type'] ?? null) === 'committee') {
            $conditions[] = 'a.committee_id IS NOT NULL';
        } elseif (isset($filters['vacancy_id'])) {
            $conditions[] = 'a.vacancy_id = ?';
            $params[] = $filters['vacancy_id'];
        }

        if (isset($filters['from'])) {
            $conditions[] = 'a.submitted_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }

        if (isset($filters['to'])) {
            $conditions[] = 'a.submitted_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        if (isset($filters['q'])) {
            $like = '%' . strtr($filters['q'], ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
            $conditions[] = "(CONCAT(COALESCE(u.first_name, ap.first_name), ' ', COALESCE(u.last_name, ap.last_name)) LIKE ? ESCAPE '|' OR COALESCE(u.email, ap.email) LIKE ? ESCAPE '|' OR a.tracking_code LIKE ? ESCAPE '|')";
            array_push($params, $like, $like, $like);
        }

        // Committee heads review approved applications, those already evaluated, and position applications they can evaluate (BR-05).
        if (($filters['reviewed_only'] ?? false) === true) {
            $conditions[] = "(a.status = 'approved'
                OR ((a.vacancy_id IS NOT NULL OR a.committee_id IS NOT NULL) AND a.status IN ('submitted', 'under_review', 'for_revision', 'waitlisted'))
                OR EXISTS (SELECT 1 FROM evaluations e WHERE e.application_id = a.application_id))";
        }

        $where = implode(' AND ', $conditions);
        $from = 'FROM membership_applications a LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id LEFT JOIN users u ON u.user_id = COALESCE(a.user_id, ap.user_id) LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id ' . self::JOIN_CHOSEN;
        $count = $this->pdo()->prepare("SELECT COUNT(*) {$from} WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;
        $order = ($columns[$sort] ?? 'a.submitted_at') . ($direction === 'asc' ? ' ASC' : ' DESC');

        // $order comes only from the fixed list above. $perPage and $offset are integers.
        $rows = $this->pdo()->prepare(
            "SELECT a.application_id, a.tracking_code, a.status, a.submitted_at, a.vacancy_id, a.committee_id, " . self::APPLIED_FOR . " AS vacancy_title,
                    COALESCE(u.first_name, ap.first_name) AS first_name, COALESCE(u.last_name, ap.last_name) AS last_name, COALESCE(u.email, ap.email) AS email
             {$from} WHERE {$where} ORDER BY {$order}, a.application_id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'page' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage, 'total' => $total];
    }

    /** @return list<array<string, mixed>> Vacancies that have applications, for the list filter. */
    public function vacanciesWithApplications(): array
    {
        return $this->pdo()->query(
            "SELECT DISTINCT v.vacancy_id, v.title
             FROM membership_applications a
             JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             WHERE a.status <> 'draft'
             ORDER BY v.title"
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> Applications received for one vacancy, newest first. Drafts are not received yet. */
    public function forVacancy(int $vacancyId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT a.application_id, a.tracking_code, a.status, a.submitted_at, u.first_name, u.last_name
             FROM membership_applications a
             JOIN users u ON u.user_id = a.user_id
             WHERE a.vacancy_id = ? AND a.status <> 'draft'
             ORDER BY a.submitted_at DESC, a.application_id DESC"
        );
        $statement->execute([$vacancyId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> What a committee head may read for one of their vacancies, with evaluation totals. */
    public function reviewableForVacancy(int $vacancyId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT a.application_id, a.tracking_code, a.status, a.submitted_at, u.first_name, u.last_name,
                    COUNT(e.evaluation_id) AS evaluation_count, AVG(e.score) AS average_score
             FROM membership_applications a
             JOIN users u ON u.user_id = a.user_id
             LEFT JOIN evaluations e ON e.application_id = a.application_id
             WHERE a.vacancy_id = ? AND a.status <> 'draft'
             GROUP BY a.application_id, a.tracking_code, a.status, a.submitted_at, u.first_name, u.last_name
             HAVING a.status IN ('approved', 'submitted', 'under_review', 'for_revision', 'waitlisted') OR evaluation_count > 0
             ORDER BY a.submitted_at DESC, a.application_id DESC"
        );
        $statement->execute([$vacancyId]);

        return $statement->fetchAll();
    }

    public function hasEvaluation(int $applicationId): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM evaluations WHERE application_id = ?');
        $statement->execute([$applicationId]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return list<array<string, mixed>> Oldest first, with who made each change, for staff. */
    public function historyWithActors(int $applicationId): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT h.new_status, h.remarks, h.changed_at, CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM application_status_history h
             LEFT JOIN users u ON u.user_id = h.changed_by
             WHERE h.application_id = ?
             ORDER BY h.changed_at, h.id"
        );
        $statement->execute([$applicationId]);

        return $statement->fetchAll();
    }

    public function trackingCodeExists(string $code): bool
    {
        return $this->findBy(['tracking_code' => $code]) !== [];
    }

    public function addHistory(int $applicationId, ?string $from, string $to, ?string $remarks, ?int $changedBy): void
    {
        $this->pdo()->prepare(
            'INSERT INTO application_status_history (application_id, old_status, new_status, remarks, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$applicationId, $from, $to, $remarks, $changedBy, date('Y-m-d H:i:s')]);
    }

    /** @return list<array<string, mixed>> Oldest first, with remarks, for the applicant's own timeline. */
    public function fullHistory(int $applicationId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT new_status, remarks, changed_at FROM application_status_history WHERE application_id = ? ORDER BY changed_at, id'
        );
        $statement->execute([$applicationId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null A submitted application, with only what public tracking needs. */
    public function findTrackable(string $trackingCode): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT a.application_id, a.applicant_id, ap.user_id AS applicant_user_id, a.status, a.submitted_at, COALESCE(u.email, ap.email) AS owner_email
             FROM membership_applications a
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             LEFT JOIN users u ON u.user_id = COALESCE(a.user_id, ap.user_id)
             WHERE a.tracking_code = ? AND a.status <> 'draft' AND a.submitted_at IS NOT NULL
             LIMIT 1"
        );
        $statement->execute([$trackingCode]);

        return $statement->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> Status and time only. Reviewers' remarks and names stay private. */
    public function statusHistory(int $applicationId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT new_status, changed_at FROM application_status_history WHERE application_id = ? ORDER BY changed_at, id'
        );
        $statement->execute([$applicationId]);

        return $statement->fetchAll();
    }
}
