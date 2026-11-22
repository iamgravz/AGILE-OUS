<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CommitteeRepository;
use App\Models\ContentPostRepository;
use App\Models\SettingRepository;
use App\Models\VacancyRepository;

/** Everything here is public, so it only ever reads published posts and open vacancies (BR-12). */
final class PortalService
{
    public const LIST_SIZE = 12;

    public function __construct(
        private readonly ContentPostRepository $posts = new ContentPostRepository(),
        private readonly VacancyRepository $vacancies = new VacancyRepository(),
        private readonly CommitteeRepository $committees = new CommitteeRepository(),
        private readonly SettingRepository $settings = new SettingRepository(),
    ) {
    }

    /** Article IV order for the Executive Board on the About page. */
    private const EXECUTIVE_ORDER = ['President', 'Vice President for Internal Affairs', 'Vice President for External Affairs', 'General Secretary', 'Assistant Secretary', 'Treasurer', 'Auditor'];

    /** Committees that are not standing committees (Article V): shown apart. */
    private const NOT_STANDING = ['The Source Code', ElectedOfficerService::COUNCIL];

    /** @return array<string, mixed> */
    public function about(): array
    {
        return $this->organization() + [
            'committees' => $this->committees->allByName(),
            'facts' => $this->facts(),
            'officers' => $this->officerGroups(),
        ];
    }

    /** @return array{members: int, committees: int, officers: int, open: int} The numbers in the public heroes. */
    public function facts(): array
    {
        $db = Database::connection();
        $marks = implode(',', array_fill(0, count(self::NOT_STANDING), '?'));
        $committees = $db->prepare("SELECT COUNT(*) FROM committees WHERE name NOT IN ({$marks})");
        $committees->execute(self::NOT_STANDING);
        $open = array_sum(array_map(static fn (array $p): int => $p['remaining'] ?? 1, (new MembershipService())->openPositions()));

        return [
            'members' => (int) $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn(),
            'committees' => (int) $committees->fetchColumn(),
            'officers' => (int) $db->query("SELECT COUNT(*) FROM members WHERE position_id IS NOT NULL AND role_status = 'active'")->fetchColumn(),
            'open' => $open,
        ];
    }

    /**
     * The officers the public sees, by group: the Executive Board in Article IV order, the Year-Level Council, and the
     * committee heads. Names and positions only.
     *
     * @return array{executive: list<array<string, string>>, council: list<array<string, string>>, heads: list<array<string, string>>}
     */
    public function officerGroups(): array
    {
        $rows = Database::connection()->query(
            "SELECT COALESCE(u.first_name, a.first_name) AS first_name, COALESCE(u.last_name, a.last_name) AS last_name,
                    p.title, p.role_type, c.name AS committee_name
             FROM members m
             JOIN positions p ON p.position_id = m.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             LEFT JOIN users u ON u.user_id = m.user_id
             LEFT JOIN applicants a ON a.applicant_id = m.applicant_id
             WHERE m.role_status = 'active' AND (p.committee_id IS NULL OR c.name = '" . ElectedOfficerService::COUNCIL . "' OR p.role_type = 'committee_head')
             ORDER BY p.position_id"
        )->fetchAll();
        $groups = ['executive' => [], 'council' => [], 'heads' => []];

        foreach ($rows as $row) {
            $person = ['name' => $row['first_name'] . ' ' . $row['last_name'], 'title' => (string) $row['title'], 'committee' => (string) ($row['committee_name'] ?? '')];

            if ($row['committee_name'] === null) {
                $groups['executive'][] = $person;
            } elseif ($row['committee_name'] === ElectedOfficerService::COUNCIL) {
                $groups['council'][] = $person;
            } else {
                $groups['heads'][] = $person;
            }
        }

        $rank = array_flip(self::EXECUTIVE_ORDER);
        usort($groups['executive'], static fn (array $a, array $b): int => ($rank[$a['title']] ?? 99) <=> ($rank[$b['title']] ?? 99));
        usort($groups['heads'], static fn (array $a, array $b): int => strcmp($a['committee'], $b['committee']));

        return $groups;
    }

    /**
     * The seven standing committees, then The Source Code apart: what each does, who leads it, how many are in it, and
     * how many slots are open for application now.
     *
     * @return array{standing: list<array<string, mixed>>, sourceCode: array<string, mixed>|null}
     */
    public function committeeCards(): array
    {
        $cards = array_map(fn (array $row): array => $this->committeeCard($row), Database::connection()->query('SELECT committee_id, name, description FROM committees ORDER BY name')->fetchAll());
        $standing = array_values(array_filter($cards, static fn (array $c): bool => !in_array($c['name'], self::NOT_STANDING, true)));
        $sourceCode = array_values(array_filter($cards, static fn (array $c): bool => $c['name'] === 'The Source Code'))[0] ?? null;

        return ['standing' => $standing, 'sourceCode' => $sourceCode];
    }

    /** @return array<string, mixed>|null One committee's page: its card and the positions open in it. */
    public function committee(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT committee_id, name, description FROM committees WHERE committee_id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        if ($row === false || $row['name'] === ElectedOfficerService::COUNCIL) {
            return null;
        }

        return $this->committeeCard($row);
    }

    /** @return array{data: list<array<string, mixed>>, page: int, perPage: int, lastPage: int, total: int} */
    public function posts(string $type, string $search, int $page, ?string $when = null): array
    {
        return $this->posts->publishedList($type, $search, $page, self::LIST_SIZE, $when);
    }

    /** @return array<string, mixed>|null */
    public function post(string $slug): ?array
    {
        return $this->posts->findPublishedBySlug($slug);
    }

    /** @return list<array<string, mixed>> */
    public function openPositions(): array
    {
        return $this->vacancies->openList();
    }

    /** @return array<string, mixed>|null */
    public function openPosition(int $id): ?array
    {
        return $this->vacancies->findOpen($id);
    }

    public function guestWelfareEnabled(): bool
    {
        return $this->settings->value('welfare.guest_submission', '1') === '1';
    }

    /** @param array<string, mixed> $row */
    private function committeeCard(array $row): array
    {
        $id = (int) $row['committee_id'];
        $db = Database::connection();
        $leaders = $db->prepare(
            "SELECT p.role_type, CONCAT(COALESCE(u.first_name, a.first_name), ' ', COALESCE(u.last_name, a.last_name)) AS name
             FROM members m JOIN positions p ON p.position_id = m.position_id
             LEFT JOIN users u ON u.user_id = m.user_id LEFT JOIN applicants a ON a.applicant_id = m.applicant_id
             WHERE p.committee_id = ? AND m.role_status = 'active' AND p.role_type IN ('committee_head', 'deputy', 'source_code')
             ORDER BY p.position_id"
        );
        $leaders->execute([$id]);
        $people = $leaders->fetchAll();
        $count = $db->prepare(
            "SELECT (SELECT COUNT(*) FROM members m JOIN positions p ON p.position_id = m.position_id WHERE p.committee_id = ? AND m.role_status = 'active')
                  + (SELECT COUNT(*) FROM member_committees WHERE committee_id = ?)"
        );
        $count->execute([$id, $id]);
        $open = array_values(array_filter((new MembershipService())->openPositions(), static fn (array $p): bool => $p['committee_id'] === $id));
        $head = array_values(array_filter($people, static fn (array $p): bool => $p['role_type'] === 'committee_head'))[0]['name'] ?? null;

        return [
            'committee_id' => $id,
            'name' => (string) $row['name'],
            'description' => (string) ($row['description'] ?? ''),
            // The Source Code is led by its Editor-in-Chief, the first of its staff positions.
            'head' => $head ?? ($row['name'] === 'The Source Code' ? ($people[0]['name'] ?? null) : null),
            'deputy' => array_values(array_filter($people, static fn (array $p): bool => $p['role_type'] === 'deputy'))[0]['name'] ?? null,
            'members' => (int) $count->fetchColumn(),
            'open' => $open,
            'slots' => array_sum(array_map(static fn (array $p): int => $p['remaining'] ?? 1, $open)),
        ];
    }

    /** @return array{mission: string, vision: string, objectives: string} One item per line where there are several (Article II). */
    private function organization(): array
    {
        return [
            'mission' => (string) $this->settings->value('org.mission', ''),
            'vision' => (string) $this->settings->value('org.vision', ''),
            'objectives' => (string) $this->settings->value('org.objectives', ''),
        ];
    }
}
