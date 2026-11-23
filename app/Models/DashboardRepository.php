<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Read-only counts for the dashboards. Welfare queries return counts only, never case text. */
final class DashboardRepository
{
    private function pdo(): PDO
    {
        return Database::connection();
    }

    private function count(string $sql, array $params = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, int> first column => count */
    private function pairs(string $sql, array $params = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return array_map(intval(...), $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql, array $params = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function oldest(string $sql, array $params = []): ?string
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    public function oldestDocumentAwaitingVerification(): ?string
    {
        return $this->oldest(
            "SELECT MIN(d.created_at) FROM application_documents d
             JOIN membership_applications a ON a.application_id = d.application_id
             WHERE d.verification_status = 'pending' AND a.status IN ('submitted', 'under_review')"
        );
    }

    public function oldestAppointmentPrepared(): ?string
    {
        return $this->oldest("SELECT MIN(created_at) FROM appointments WHERE status = 'prepared'");
    }

    public function oldestHrRequestSubmitted(): ?string
    {
        return $this->oldest("SELECT MIN(created_at) FROM hr_requests WHERE status = 'submitted'");
    }

    public function oldestContentForApproval(): ?string
    {
        return $this->oldest("SELECT MIN(updated_at) FROM content_posts WHERE status = 'for_approval'");
    }

    public function oldestPendingAccount(): ?string
    {
        return $this->oldest("SELECT MIN(created_at) FROM users WHERE status = 'pending'");
    }

    public function oldestCredentialPending(): ?string
    {
        return $this->oldest("SELECT MIN(issued_at) FROM digital_credentials WHERE status = 'pending_approval' AND revoked_at IS NULL");
    }

    /** BR-20. A null head means what the MSW Committee Head decides. */
    public function oldestApplicationToDecide(?int $headUserId, string $mswCommittee): ?string
    {
        $committeeDecides = '(c.head_user_id IS NOT NULL AND c.name <> ? AND (a.user_id IS NULL OR c.head_user_id <> a.user_id))';
        $where = $headUserId === null ? "NOT COALESCE({$committeeDecides}, FALSE)" : "{$committeeDecides} AND c.head_user_id = ?";

        return $this->oldest(
            "SELECT MIN(a.submitted_at) FROM membership_applications a
             LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             LEFT JOIN positions p ON p.position_id = v.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE a.status = 'under_review' AND {$where}",
            $headUserId === null ? [$mswCommittee] : [$mswCommittee, $headUserId]
        );
    }

    public function memberCount(): int
    {
        return $this->count('SELECT COUNT(*) FROM members');
    }

    public function activeOfficerCount(): int
    {
        return $this->count("SELECT COUNT(*) FROM members WHERE role_status = 'active'");
    }

    /** @return array<string, int> */
    public function membersByCommittee(): array
    {
        return $this->pairs(
            "SELECT c.name, COUNT(m.member_id)
             FROM committees c
             LEFT JOIN positions p ON p.committee_id = c.committee_id
             LEFT JOIN members m ON m.position_id = p.position_id AND m.role_status = 'active'
             GROUP BY c.committee_id, c.name
             ORDER BY c.name"
        );
    }

    /** @return array<string, int> */
    public function applicationsByStatus(): array
    {
        return $this->pairs("SELECT status, COUNT(*) FROM membership_applications WHERE status <> 'draft' GROUP BY status");
    }

    /** @return array<string, int> Y-m => count, from $from on. */
    public function applicationsPerMonth(string $from): array
    {
        return $this->pairs(
            "SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS month, COUNT(*)
             FROM membership_applications
             WHERE submitted_at IS NOT NULL AND submitted_at >= ?
             GROUP BY month",
            [$from]
        );
    }

    public function openVacancyCount(): int
    {
        return $this->count("SELECT COUNT(*) FROM vacancies WHERE status = 'open'");
    }

    public function filledSlotCount(): int
    {
        return $this->count('SELECT COALESCE(SUM(slots_filled), 0) FROM vacancies');
    }

    public function openSlotCount(): int
    {
        return $this->count("SELECT COALESCE(SUM(slots - slots_filled), 0) FROM vacancies WHERE status = 'open'");
    }

    /** @return array<string, int> */
    public function welfareByStatus(): array
    {
        return $this->pairs('SELECT status, COUNT(*) FROM welfare_cases GROUP BY status');
    }

    /** @return array<string, int> */
    public function welfareByCategory(): array
    {
        return $this->pairs(
            'SELECT w.name, COUNT(c.case_id)
             FROM welfare_categories w
             LEFT JOIN welfare_cases c ON c.category_id = w.category_id
             GROUP BY w.category_id, w.name
             ORDER BY w.category_id'
        );
    }

    /** @return array<string, int> Cases not closed yet. */
    public function openWelfareByPriority(): array
    {
        return $this->pairs("SELECT priority, COUNT(*) FROM welfare_cases WHERE status <> 'closed' GROUP BY priority");
    }

    public function overdueWelfareCount(string $cutoff): int
    {
        return $this->count(
            "SELECT COUNT(*) FROM welfare_cases
             WHERE (priority = 'urgent' OR student_marked_urgent = 1 OR category_id = 6)
               AND acknowledged_at IS NULL AND status <> 'closed' AND submitted_at <= ?",
            [$cutoff]
        );
    }

    /** @return array<string, int> */
    public function eligibilityByResult(int $termId): array
    {
        return $this->pairs('SELECT result, COUNT(*) FROM eligibility_screenings WHERE term_id = ? GROUP BY result', [$termId]);
    }

    public function documentsAwaitingVerification(): int
    {
        return $this->count(
            "SELECT COUNT(*) FROM application_documents d
             JOIN membership_applications a ON a.application_id = d.application_id
             WHERE d.verification_status = 'pending' AND a.status IN ('submitted', 'under_review')"
        );
    }

    /** @return list<array<string, mixed>> */
    public function interviewsBetween(string $from, string $to): array
    {
        return $this->rows(
            "SELECT i.application_id, i.scheduled_at, i.mode, CONCAT(COALESCE(u.first_name, ap.first_name), ' ', COALESCE(u.last_name, ap.last_name)) AS applicant_name
             FROM interviews i
             JOIN membership_applications a ON a.application_id = i.application_id
             LEFT JOIN applicants ap ON ap.applicant_id = a.applicant_id
             LEFT JOIN users u ON u.user_id = COALESCE(a.user_id, ap.user_id)
             WHERE i.result = 'pending' AND i.scheduled_at BETWEEN ? AND ?
             ORDER BY i.scheduled_at",
            [$from, $to]
        );
    }

    public function appointmentsPrepared(): int
    {
        return $this->count("SELECT COUNT(*) FROM appointments WHERE status = 'prepared'");
    }

    public function hrRequestsSubmitted(): int
    {
        return $this->count("SELECT COUNT(*) FROM hr_requests WHERE status = 'submitted'");
    }

    public function contentForApproval(): int
    {
        return $this->count("SELECT COUNT(*) FROM content_posts WHERE status = 'for_approval'");
    }

    public function failedEmails(): int
    {
        return $this->count("SELECT COUNT(*) FROM notifications WHERE channel = 'email' AND status = 'failed'");
    }

    /** @return array<string, int> */
    public function hrRequestsByStatusFor(int $userId): array
    {
        return $this->pairs(
            'SELECT r.status, COUNT(*) FROM hr_requests r
             JOIN committees c ON c.committee_id = r.committee_id
             WHERE r.requested_by = ? OR c.head_user_id = ?
             GROUP BY r.status',
            [$userId, $userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function openVacanciesHeadedBy(int $userId): array
    {
        return $this->rows(
            "SELECT v.vacancy_id, v.title, v.slots, v.slots_filled, v.closes_at
             FROM vacancies v
             JOIN positions p ON p.position_id = v.position_id
             JOIN committees c ON c.committee_id = p.committee_id
             WHERE c.head_user_id = ? AND v.status = 'open'
             ORDER BY v.closes_at",
            [$userId]
        );
    }

    /** @return list<array<string, mixed>> Position applications being decided that this person has not evaluated. */
    /** BR-20. A null head counts what the MSW Committee Head decides. */
    public function applicationsToDecide(?int $headUserId, string $mswCommittee): int
    {
        $committeeDecides = '(c.head_user_id IS NOT NULL AND c.name <> ? AND (a.user_id IS NULL OR c.head_user_id <> a.user_id))';
        $where = $headUserId === null ? "NOT COALESCE({$committeeDecides}, FALSE)" : "{$committeeDecides} AND c.head_user_id = ?";
        $statement = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM membership_applications a
             LEFT JOIN vacancies v ON v.vacancy_id = a.vacancy_id
             LEFT JOIN positions p ON p.position_id = v.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE a.status = 'under_review' AND {$where}"
        );
        $statement->execute($headUserId === null ? [$mswCommittee] : [$mswCommittee, $headUserId]);

        return (int) $statement->fetchColumn();
    }

    public function pendingAccountCount(): int
    {
        return (int) $this->pdo()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    }

    /** @return list<array<string, mixed>> Status only, with no text. */
    public function welfareStatusFor(int $userId, int $limit = 5): array
    {
        return $this->rows('SELECT case_id, case_code, status, submitted_at FROM welfare_cases WHERE user_id = ? ORDER BY case_id DESC LIMIT ' . max(1, $limit), [$userId]);
    }

    /** @return list<array<string, mixed>> */
    public function pendingInterviewsFor(int $userId): array
    {
        return $this->rows(
            "SELECT i.scheduled_at, i.mode, i.location
             FROM interviews i
             JOIN membership_applications a ON a.application_id = i.application_id
             WHERE a.user_id = ? AND i.result = 'pending' AND i.scheduled_at >= ?
             ORDER BY i.scheduled_at",
            [$userId, date('Y-m-d H:i:s')]
        );
    }
}
