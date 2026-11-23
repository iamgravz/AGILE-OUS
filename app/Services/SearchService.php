<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WelfareCaseRepository;

/**
 * The top bar search. It only asks the services the user could open anyway, so their own permission and record
 * checks apply, and it can never show more than the user's lists do (FR-ADM-07).
 */
final class SearchService
{
    public const MIN_LENGTH = 2;
    public const MAX_LENGTH = 100;

    private const PER_MODULE = 5;

    /** The modules this user may search. An empty list means no search box. */
    public static function modules(): array
    {
        return array_keys(array_filter([
            'applications' => can('application.view'),
            'members' => can('member.view'),
            'vacancies' => can('vacancy.manage'),
            'content' => can('content.draft'),
            'welfare' => can('welfare.view'),
        ]));
    }

    /** @return list<array{module: string, total: int, all_url: string|null, items: list<array{title: string, detail: string, status: string, url: string}>}> */
    public function search(string $query): array
    {
        if (self::modules() === []) {
            throw new \App\Core\ForbiddenException('search.modules');
        }

        $query = mb_substr(trim($query), 0, self::MAX_LENGTH);

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [];

        foreach (self::modules() as $module) {
            $groups[] = $this->{$module}($query);
        }

        return $groups;
    }

    private function applications(string $query): array
    {
        $result = (new MembershipService())->listForStaff(['q' => $query], 'submitted_at', 'desc', 1);

        return $this->group('Applications', $result['total'], route('applications') . '?q=' . urlencode($query), array_map(static fn (array $row): array => [
            'title' => $row['first_name'] . ' ' . $row['last_name'],
            'detail' => $row['tracking_code'] . ', ' . ($row['vacancy_title'] ?? 'General Membership'),
            'status' => $row['status'],
            'url' => route('applications.show', ['id' => (int) $row['application_id']]),
        ], $result['data']));
    }

    private function members(string $query): array
    {
        $result = (new MemberRecordsService())->list(['q' => $query], 1);

        return $this->group('Members', $result['total'], route('members') . '?q=' . urlencode($query), array_map(static fn (array $row): array => [
            'title' => $row['first_name'] . ' ' . $row['last_name'],
            'detail' => $row['member_no'] . ', ' . MembershipService::roleLabel($row),
            'status' => 'active',
            'url' => route('members.show', ['id' => (int) $row['member_id']]),
        ], $result['data']));
    }

    private function vacancies(string $query): array
    {
        $result = (new RecruitmentService())->listVacancies(['q' => $query], 1);

        return $this->group('Vacancies', $result['total'], route('vacancies') . '?q=' . urlencode($query), array_map(static fn (array $row): array => [
            'title' => $row['title'],
            'detail' => $row['committee_name'] ?? 'Executive',
            'status' => $row['status'],
            'url' => route('vacancies.show', ['id' => (int) $row['vacancy_id']]),
        ], $result['data']));
    }

    private function content(string $query): array
    {
        $result = (new CmsService())->list(['q' => $query], 1);

        return $this->group('Content', $result['total'], route('cms') . '?q=' . urlencode($query), array_map(static fn (array $row): array => [
            'title' => $row['title'],
            'detail' => ucfirst($row['type']),
            'status' => $row['status'],
            'url' => route('cms.show', ['id' => (int) $row['post_id']]),
        ], $result['data']));
    }

    /** By case code only. The subject and everything else about the case stay out of the results. */
    private function welfare(string $query): array
    {
        authorize('welfare.view');

        $rows = (new WelfareCaseRepository())->searchByCode($query, self::PER_MODULE);

        return $this->group('Welfare cases', count($rows), null, array_map(static fn (array $row): array => [
            'title' => $row['case_code'],
            'detail' => '',
            'status' => $row['status'],
            'url' => route('welfare.show', ['id' => (int) $row['case_id']]),
        ], $rows));
    }

    /** @param list<array<string, string>> $items */
    private function group(string $module, int $total, ?string $allUrl, array $items): array
    {
        return ['module' => $module, 'total' => $total, 'all_url' => $allUrl, 'items' => array_slice($items, 0, self::PER_MODULE)];
    }
}
