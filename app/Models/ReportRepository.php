<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Read-only report queries. Filters are always bound, and welfare returns counts only, with no names or text. */
final class ReportRepository
{
    /**
     * @param array{from?: string, to?: string, committee_id?: int, type?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function membership(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (isset($filters['from'])) {
            $conditions[] = 'm.joined_at >= ?';
            $params[] = $filters['from'];
        }

        if (isset($filters['to'])) {
            $conditions[] = 'm.joined_at <= ?';
            $params[] = $filters['to'];
        }

        if (isset($filters['committee_id'])) {
            $conditions[] = 'p.committee_id = ?';
            $params[] = $filters['committee_id'];
        }

        if (isset($filters['type'])) {
            $conditions[] = 'm.membership_type = ?';
            $params[] = $filters['type'];
        }

        return $this->rows(
            "SELECT m.member_no, CONCAT(COALESCE(u.first_name, ap.first_name), ' ', COALESCE(u.last_name, ap.last_name)) AS member_name,
                    COALESCE(u.student_no, ap.student_no) AS student_no, m.membership_type,
                    COALESCE(r.role_name, 'General Member') AS role_name, p.title AS position_title, c.name AS committee_name, m.role_status, m.status, m.joined_at
             FROM members m
             LEFT JOIN users u ON u.user_id = m.user_id
             LEFT JOIN roles r ON r.role_id = u.role_id
             LEFT JOIN applicants ap ON ap.applicant_id = m.applicant_id
             LEFT JOIN positions p ON p.position_id = m.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE " . implode(' AND ', $conditions) . '
             ORDER BY COALESCE(u.last_name, ap.last_name), COALESCE(u.first_name, ap.first_name), m.member_id',
            $params
        );
    }

    /**
     * @param array{from?: string, to?: string, committee_id?: int, status?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function recruitment(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (isset($filters['from'])) {
            $conditions[] = 'v.opens_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }

        if (isset($filters['to'])) {
            $conditions[] = 'v.opens_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        if (isset($filters['committee_id'])) {
            $conditions[] = 'p.committee_id = ?';
            $params[] = $filters['committee_id'];
        }

        if (isset($filters['status'])) {
            $conditions[] = 'v.status = ?';
            $params[] = $filters['status'];
        }

        return $this->rows(
            "SELECT v.title, c.name AS committee_name, v.status, v.slots, v.slots_filled, v.opens_at, v.closes_at,
                    (SELECT COUNT(*) FROM membership_applications a WHERE a.vacancy_id = v.vacancy_id AND a.status <> 'draft') AS applications,
                    (SELECT COUNT(*) FROM membership_applications a WHERE a.vacancy_id = v.vacancy_id AND a.status = 'approved') AS approved,
                    (SELECT COUNT(*) FROM interviews i JOIN membership_applications a ON a.application_id = i.application_id WHERE a.vacancy_id = v.vacancy_id) AS interviews,
                    (SELECT COUNT(*) FROM appointments ap JOIN membership_applications a ON a.application_id = ap.application_id WHERE a.vacancy_id = v.vacancy_id AND ap.status = 'approved') AS appointments
             FROM vacancies v
             JOIN positions p ON p.position_id = v.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE " . implode(' AND ', $conditions) . '
             ORDER BY v.opens_at DESC, v.vacancy_id DESC',
            $params
        );
    }

    /**
     * Counts per category, status and priority. No case code, name or text is selected.
     *
     * @param array{from?: string, to?: string, category_id?: int} $filters
     * @return list<array{category: string, status: string, priority: string, cases: int}>
     */
    public function welfare(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (isset($filters['from'])) {
            $conditions[] = 'c.submitted_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }

        if (isset($filters['to'])) {
            $conditions[] = 'c.submitted_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        if (isset($filters['category_id'])) {
            $conditions[] = 'c.category_id = ?';
            $params[] = $filters['category_id'];
        }

        return $this->rows(
            'SELECT w.name AS category, c.status, c.priority, COUNT(*) AS cases
             FROM welfare_cases c
             JOIN welfare_categories w ON w.category_id = c.category_id
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY w.category_id, w.name, c.status, c.priority
             ORDER BY w.category_id, c.status, c.priority',
            $params
        );
    }

    /** @return list<array<string, mixed>> Results for one term. Decision remarks are left out. */
    public function eligibility(int $termId): array
    {
        return $this->rows(
            "SELECT m.member_no, CONCAT(u.first_name, ' ', u.last_name) AS officer_name, p.title AS position_title, c.name AS committee_name,
                    s.result, s.has_failed_subject, s.is_officially_enrolled, s.flagged_at, s.verified_at, rv.decision, rv.decided_at
             FROM eligibility_screenings s
             JOIN members m ON m.member_id = s.member_id
             JOIN users u ON u.user_id = m.user_id
             LEFT JOIN positions p ON p.position_id = m.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             LEFT JOIN eligibility_reviews rv ON rv.screening_id = s.screening_id
             WHERE s.term_id = ?
             ORDER BY u.last_name, u.first_name",
            [$termId]
        );
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql, array $params): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
