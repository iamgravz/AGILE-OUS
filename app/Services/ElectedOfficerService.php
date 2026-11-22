<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use DomainException;

/**
 * Elected officers (Constitution and By-Laws, Article VI): the Executive Board and the Year-Level Representatives are
 * elected, never applied for, so the MSW Committee Head (admin.users) records the winners here. Each seat gets an
 * account (or the person's existing one), a member record and the position, which gives the role in
 * config/role_mapping.php. Ending a term stays with appointments and role expiry.
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
     * @param array{first_name: string, last_name: string, email: string, student_no: string, contact_no: string, reason: string} $person
     * @return int The account's user_id.
     * @throws DomainException
     */
    public function record(int $positionId, array $person): int
    {
        authorize('admin.users');

        $seat = null;

        foreach ($this->seats() as $row) {
            if ($row['position_id'] === $positionId) {
                $seat = $row;
            }
        }

        if ($seat === null) {
            throw new DomainException('Please choose an Executive Board or Year-Level Representative position.');
        }

        if ($seat['open'] === 0) {
            throw new DomainException($seat['title'] . ' is already held by ' . implode(', ', $seat['holders']) . '. End that term first (Members, change role) before recording the new officer.');
        }

        $reason = trim($person['reason']);

        if ($reason === '') {
            throw new DomainException('Please write the election record, for example the election date.');
        }

        return Database::transaction(function () use ($seat, $person, $reason): int {
            $membership = new MembershipService();
            $existing = $this->users->findByEmail(mb_strtolower(trim($person['email'])));

            if ($existing === null) {
                $userId = (new AuthService())->createAccount($person['email'], $person['first_name'], $person['last_name'], 'general_member', $person['student_no']);

                if ($person['contact_no'] !== '') {
                    $this->users->update($userId, ['contact_no' => $person['contact_no']]);
                }
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

            $member = $membership->ensureMember($userId, $reason);
            $membership->changeRole((int) $member['member_id'], $seat['position_id'], $reason, (int) current_user_id());
            (new CredentialService())->issueFor((int) $member['member_id']);
            AuditService::log('officer.elected_recorded', 'member', (int) $member['member_id'], ['position_id' => $seat['position_id'], 'new_account' => $existing === null]);

            return $userId;
        });
    }
}
