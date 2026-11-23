<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AcademicTermRepository;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use DomainException;

/**
 * Elected officers (Constitution and By-Laws, Article VI): the Executive Board and the Year-Level Representatives are
 * elected, never applied for, so the MSW Committee Head (admin.users) records the winners here. Each seat gets an
 * account (or the person's existing one), a member record and the position, which gives the role in
 * config/role_mapping.php. Ending a term stays with appointments and role expiry.
 *
 * Article VI checks, from config/elections.php: the minimum year level (Section 2), a representative's own year level,
 * the MSW Committee Head's confirmation of full load and clean grades, and the consecutive-term limit (Section 4),
 * counted from officer_terms.
 */
final class ElectedOfficerService
{
    public const COUNCIL = 'Year-Level Council';

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly MemberRepository $members = new MemberRepository(),
    ) {
    }

    /**
     * Every elected seat with who holds it now. Executive positions have one seat each.
     *
     * @return list<array{position_id: int, title: string, group: string, slots: int, holders: list<string>, open: int}>
     */
    public function seats(): array
    {
        authorize('admin.users');

        return $this->allSeats();
    }

    /** One elected seat, or null when the position is not an Executive Board or Year-Level Council one. For candidacies. */
    public function seat(int $positionId): ?array
    {
        foreach ($this->allSeats() as $row) {
            if ($row['position_id'] === $positionId) {
                return $row;
            }
        }

        return null;
    }

    /** @return list<array{position_id: int, title: string, group: string, slots: int, holders: list<string>, open: int}> */
    public function allSeats(): array
    {
        $rows = Database::connection()->prepare(
            "SELECT p.position_id, p.title, p.target_slots, c.name AS committee_name,
                    GROUP_CONCAT(CONCAT(COALESCE(u.first_name, a.first_name), ' ', COALESCE(u.last_name, a.last_name)) ORDER BY m.member_id SEPARATOR '\n') AS holders
             FROM positions p
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             LEFT JOIN members m ON m.position_id = p.position_id AND m.role_status = 'active'
             LEFT JOIN users u ON u.user_id = m.user_id
             LEFT JOIN applicants a ON a.applicant_id = m.applicant_id
             WHERE (p.committee_id IS NULL AND p.role_type = 'officer') OR c.name = ?
             GROUP BY p.position_id, p.title, p.target_slots, c.name
             ORDER BY c.name IS NOT NULL, FIELD(p.title, ?, ?, ?, ?, ?, ?, ?) = 0, FIELD(p.title, ?, ?, ?, ?, ?, ?, ?), p.title"
        );
        // Article IV order; any other executive title follows.
        $order = ['President', 'Vice President for Internal Affairs', 'Vice President for External Affairs', 'General Secretary', 'Assistant Secretary', 'Treasurer', 'Auditor'];
        $rows->execute([self::COUNCIL, ...$order, ...$order]);
        $seats = [];

        foreach ($rows->fetchAll() as $row) {
            $holders = $row['holders'] === null ? [] : explode("\n", (string) $row['holders']);
            $slots = $row['committee_name'] === null ? 1 : max(1, (int) ($row['target_slots'] ?? 1));
            $seats[] = [
                'position_id' => (int) $row['position_id'],
                'title' => (string) $row['title'],
                'group' => $row['committee_name'] === null ? 'Executive Board' : 'Year-Level Representatives',
                'slots' => $slots,
                'holders' => $holders,
                'open' => max(0, $slots - count($holders)),
            ];
        }

        return $seats;
    }

    /**
     * Records an election winner. A new email gets a new account and the password link; an email that already has an
     * account (a member who won) keeps it and only gains the position.
     *
     * @param array{first_name: string, last_name: string, email: string, student_no: string, contact_no: string, reason: string, year_level: string, section: string, academic_year: string, confirmations: list<string>} $person
     * @return int The account's user_id.
     * @throws DomainException
     */
    public function record(int $positionId, array $person): int
    {
        authorize('admin.users');

        $seat = $this->seat($positionId);

        if ($seat === null) {
            throw new DomainException('Please choose an Executive Board or Year-Level Representative position.');
        }

        // Re-election: the holder wins the same seat for another year. Only the new term is recorded.
        $existing = $this->users->findByEmail(mb_strtolower(trim($person['email'])));
        $holding = $existing === null ? null : $this->members->findByUser((int) $existing['user_id']);
        $reelected = $holding !== null && (int) $holding['position_id'] === $seat['position_id'] && $holding['role_status'] === 'active';

        if ($seat['open'] === 0 && !$reelected) {
            throw new DomainException($seat['title'] . ' is already held by ' . implode(', ', $seat['holders']) . '. End that term first (Members, change role) before recording the new officer.');
        }

        $reason = trim($person['reason']);

        if ($reason === '') {
            throw new DomainException('Please write the election record, for example the election date.');
        }

        $academicYear = trim($person['academic_year']);
        $this->assertQualified($seat, $person);
        $this->assertWithinTermLimit($seat, $person, $academicYear);

        return Database::transaction(function () use ($seat, $person, $reason, $academicYear, $existing, $holding, $reelected): int {
            $membership = new MembershipService();

            if ($reelected) {
                $this->recordTerm((int) $holding['member_id'], $seat['position_id'], $academicYear, $person['year_level']);
                $this->members->addHistory((int) $holding['member_id'], $seat['title'], $seat['title'], 'Re-elected: ' . $reason, (int) current_user_id());
                AuditService::log('officer.reelected_recorded', 'member', (int) $holding['member_id'], ['position_id' => $seat['position_id'], 'academic_year' => $academicYear]);

                return (int) $existing['user_id'];
            }

            if ($existing === null) {
                $userId = (new AuthService())->createAccount($person['email'], $person['first_name'], $person['last_name'], 'general_member', $person['student_no']);

                $this->users->update($userId, ['contact_no' => $person['contact_no'], 'year_level' => $person['year_level'], 'section' => $person['section']]);
            } else {
                $userId = (int) $existing['user_id'];

                if ($existing['status'] !== 'active') {
                    throw new DomainException('This email belongs to an account that is ' . $existing['status'] . '. Enable or approve it in Users first.');
                }

                $current = $this->members->findByUser($userId);

                if ($current !== null && $current['position_id'] !== null) {
                    throw new DomainException('This person already holds a position. One office per person (Article VI, Section 4-A): end that role first.');
                }
            }

            if ($existing !== null) {
                $this->users->update($userId, ['contact_no' => $person['contact_no'], 'year_level' => $person['year_level'], 'section' => $person['section']]);
            }

            $member = $membership->ensureMember($userId, $reason);
            $membership->changeRole((int) $member['member_id'], $seat['position_id'], $reason, (int) current_user_id());
            (new CredentialService())->issueFor((int) $member['member_id']);
            $this->recordTerm((int) $member['member_id'], $seat['position_id'], $academicYear, $person['year_level']);
            AuditService::log('officer.elected_recorded', 'member', (int) $member['member_id'], ['position_id' => $seat['position_id'], 'new_account' => $existing === null]);

            return $userId;
        });
    }

    private function recordTerm(int $memberId, int $positionId, string $academicYear, string $yearLevel): void
    {
        Database::connection()->prepare('INSERT INTO officer_terms (member_id, position_id, academic_year, year_level, recorded_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$memberId, $positionId, $academicYear, $yearLevel, current_user_id()]);
    }

    /** The academic year a new term is recorded for: the current term's, or the school year running now. */
    public function defaultAcademicYear(): string
    {
        $term = (new AcademicTermRepository())->current();

        if ($term !== null) {
            return (string) $term['academic_year'];
        }

        $start = (int) date('n') >= 8 ? (int) date('Y') : (int) date('Y') - 1;

        return $start . '-' . ($start + 1);
    }

    /** The lowest year level a seat needs (1 to 4), from config/elections.php. */
    public static function minimumYear(array $seat): int
    {
        if ($seat['group'] !== 'Executive Board') {
            return 1;
        }

        $levels = config('elections.min_year_level');

        return (int) ($levels[$seat['title']] ?? $levels['default']);
    }

    /** @param array<string, mixed> $seat @param array<string, mixed> $person */
    private function assertQualified(array $seat, array $person): void
    {
        $this->assertYearLevel($seat, (string) $person['year_level']);

        $missing = array_diff(array_keys(config('elections.confirmations')), $person['confirmations']);

        if ($missing !== []) {
            throw new DomainException('Please confirm the Article VI requirements: ' . implode(' ', array_map(static fn (string $key): string => config('elections.confirmations')[$key], $missing)));
        }
    }

    /**
     * Article VI Section 2 and Article V Section 3: the minimum year level, and a representative's own year.
     *
     * @param array<string, mixed> $seat
     * @throws DomainException
     */
    public function assertYearLevel(array $seat, string $yearLevel): void
    {
        $year = (int) $yearLevel;

        if ($year < 1) {
            throw new DomainException("Please choose the officer's year level.");
        }

        if ($year < self::minimumYear($seat)) {
            throw new DomainException(sprintf('%s needs a student in at least %s year (Article VI, Section 2).', $seat['title'], self::ordinal(self::minimumYear($seat))));
        }

        if ($seat['group'] !== 'Executive Board' && config('elections.representative_same_year') && (int) $seat['title'] !== $year) {
            throw new DomainException(sprintf('The %s must be in %s year.', $seat['title'], self::ordinal((int) $seat['title'])));
        }
    }

    /**
     * Article VI Section 4: no more than the configured consecutive terms in one position. $person needs email and student_no.
     *
     * @throws DomainException
     */
    public function assertWithinTermLimit(array $seat, array $person, string $academicYear): void
    {
        if (preg_match('/^(\d{4})-(\d{4})$/', $academicYear, $match) !== 1 || (int) $match[2] !== (int) $match[1] + 1) {
            throw new DomainException('Please give the academic year as 2026-2027.');
        }

        $statement = Database::connection()->prepare(
            'SELECT DISTINCT t.academic_year FROM officer_terms t
             JOIN members m ON m.member_id = t.member_id
             LEFT JOIN users u ON u.user_id = m.user_id
             WHERE t.position_id = ? AND (u.email = ? OR (CHAR_LENGTH(?) > 0 AND u.student_no = ?))'
        );
        $studentNo = trim($person['student_no']);
        $statement->execute([$seat['position_id'], mb_strtolower(trim($person['email'])), $studentNo, $studentNo]);
        $years = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if (in_array($academicYear, $years, true)) {
            throw new DomainException("This person is already recorded as {$seat['title']} for {$academicYear}.");
        }

        $limit = (int) config('elections.max_consecutive_terms');
        $start = (int) $match[1];
        $consecutive = 0;

        while (in_array(($start - $consecutive - 1) . '-' . ($start - $consecutive), $years, true)) {
            $consecutive++;
        }

        if ($consecutive >= $limit) {
            throw new DomainException(sprintf('This person has served %d consecutive terms as %s, the most Article VI, Section 4 allows.', $consecutive, $seat['title']));
        }
    }

    private static function ordinal(int $year): string
    {
        return $year . ([1 => 'st', 2 => 'nd', 3 => 'rd'][$year] ?? 'th');
    }
}
