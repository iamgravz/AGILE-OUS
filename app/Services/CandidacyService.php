<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MemberRepository;
use App\Models\SettingRepository;
use App\Models\UserRepository;
use DomainException;

/**
 * Candidacies for the Executive Board and the Year-Level Council (Constitution and By-Laws, Article VI). A member files
 * while filing is open, with their COR and previous semester copy of grades; the year-level minimum and the term limit
 * are checked at filing. The MSW Committee Head (admin.users) qualifies or disqualifies each one after checking the
 * documents, and after the election marks the winners elected, which records them through ElectedOfficerService.
 */
final class CandidacyService
{
    public const FILING_SETTING = 'elections.filing_open';
    public const STATUSES = ['filed' => 'Filed', 'qualified' => 'Qualified', 'disqualified' => 'Disqualified', 'withdrawn' => 'Withdrawn', 'elected' => 'Elected'];
    public const STATEMENT_MAX = 2000;

    public function __construct(
        private readonly ElectedOfficerService $officers = new ElectedOfficerService(),
        private readonly SettingRepository $settings = new SettingRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function filingOpen(): bool
    {
        return $this->settings->value(self::FILING_SETTING, '0') === '1';
    }

    public function setFilingOpen(bool $open): void
    {
        authorize('admin.users');

        $this->settings->set(self::FILING_SETTING, $open ? '1' : '0', (int) current_user_id());
        AuditService::log($open ? 'candidacy.filing_opened' : 'candidacy.filing_closed');
    }

    /** @return list<array<string, mixed>> The seats a member can run for. */
    public function seats(): array
    {
        return $this->officers->allSeats();
    }

    public function academicYear(): string
    {
        return $this->officers->defaultAcademicYear();
    }

    /** Only active members run (Article VI); the account must be a member's. */
    public function canFile(): bool
    {
        $userId = current_user_id();

        return $userId !== null && (new MemberRepository())->findByUser($userId) !== null;
    }

    /**
     * @param array{position_id: string, year_level: string, section: string, contact_no: string, statement: string} $data
     * @param array<string, array<string, mixed>> $files cor and grades, from $_FILES
     * @return int The new candidacy's id.
     * @throws DomainException|FileRejectedException
     */
    public function file(array $data, array $files): int
    {
        authorize('candidacy.file');
        $userId = (int) current_user_id();

        if (!$this->filingOpen()) {
            throw new DomainException('Filing of candidacy is closed. The MSW Committee announces when it opens.');
        }

        if (!$this->canFile()) {
            throw new DomainException('Only AGILE members can run. Join AGILE first, then file when the next election opens.');
        }

        $seat = $this->officers->seat((int) $data['position_id']) ?? throw new DomainException('Please choose an Executive Board or Year-Level Representative position.');
        $user = $this->users->find($userId) ?? throw new DomainException('This account no longer exists.');
        $academicYear = $this->academicYear();

        if ($this->activeFor($userId, $academicYear) !== null) {
            throw new DomainException("You already filed a candidacy for {$academicYear}. Withdraw it first to run for another position.");
        }

        $this->officers->assertYearLevel($seat, $data['year_level']);
        $this->officers->assertWithinTermLimit($seat, ['email' => (string) $user['email'], 'student_no' => (string) ($user['student_no'] ?? '')], $academicYear);

        foreach (['cor' => 'your current COR', 'grades' => 'your previous semester copy of grades'] as $kind => $label) {
            if (!isset($files[$kind]) || ($files[$kind]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new FileRejectedException("Please upload {$label}.");
            }
        }

        $storage = new FileStorageService();
        $storage->validate($files['cor']);
        $storage->validate($files['grades']);
        $cor = $storage->store($files['cor'], 'candidacies');
        $grades = $storage->store($files['grades'], 'candidacies');

        return Database::transaction(function () use ($userId, $seat, $academicYear, $data, $cor, $grades): int {
            $this->users->update($userId, ['year_level' => $data['year_level'], 'section' => $data['section'], 'contact_no' => $data['contact_no']]);
            Database::connection()->prepare(
                'INSERT INTO election_candidacies (user_id, position_id, academic_year, year_level, section, contact_no, statement, cor_path, grades_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$userId, $seat['position_id'], $academicYear, $data['year_level'], $data['section'], $data['contact_no'],
                trim($data['statement']) === '' ? null : trim($data['statement']), $cor['stored_path'], $grades['stored_path']]);
            $id = (int) Database::connection()->lastInsertId();
            AuditService::log('candidacy.filed', 'election_candidacy', $id, ['position_id' => $seat['position_id'], 'academic_year' => $academicYear]);

            return $id;
        });
    }

    /** @return list<array<string, mixed>> The signed-in member's candidacies, newest first. */
    public function mine(): array
    {
        return $this->query('c.user_id = ?', [(int) current_user_id()]);
    }

    public function withdraw(int $id): void
    {
        $row = $this->find($id);

        if ($row === null || (int) $row['user_id'] !== current_user_id()) {
            throw new DomainException('This candidacy is not yours.');
        }

        if (!in_array($row['status'], ['filed', 'qualified'], true)) {
            throw new DomainException('This candidacy can no longer be withdrawn.');
        }

        $this->setStatus($id, 'withdrawn', 'Withdrawn by the candidate.', false);
    }

    /**
     * @param array{year?: string, status?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters): array
    {
        authorize('admin.users');

        $where = ['1 = 1'];
        $params = [];

        if (($filters['year'] ?? '') !== '') {
            $where[] = 'c.academic_year = ?';
            $params[] = $filters['year'];
        }

        if (isset(self::STATUSES[$filters['status'] ?? ''])) {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        }

        return $this->query(implode(' AND ', $where), $params);
    }

    /** The MSW Committee Head's decision after checking the COR and grades. Disqualifying needs the reason. */
    public function decide(int $id, string $decision, string $remarks): void
    {
        authorize('admin.users');

        if (!in_array($decision, ['qualified', 'disqualified'], true)) {
            throw new DomainException('Please choose Qualified or Disqualified.');
        }

        if ($decision === 'disqualified' && trim($remarks) === '') {
            throw new DomainException('Please write why the candidate is disqualified. They see this.');
        }

        $row = $this->find($id) ?? throw new DomainException('This candidacy no longer exists.');

        if (!in_array($row['status'], ['filed', 'qualified', 'disqualified'], true)) {
            throw new DomainException('This candidacy is ' . strtolower(self::STATUSES[$row['status']]) . ' and can no longer be decided.');
        }

        $this->setStatus($id, $decision, trim($remarks), true);
    }

    /**
     * A qualified candidate won: they get the seat through Elected officers (account, member record, role, term).
     *
     * @return int The officer's user_id.
     */
    public function markElected(int $id, string $electionRecord): int
    {
        authorize('admin.users');

        $row = $this->find($id) ?? throw new DomainException('This candidacy no longer exists.');

        if ($row['status'] !== 'qualified') {
            throw new DomainException('Only a qualified candidate can be marked elected.');
        }

        return Database::transaction(function () use ($row, $id, $electionRecord): int {
            $userId = $this->officers->record((int) $row['position_id'], [
                'first_name' => (string) $row['first_name'],
                'last_name' => (string) $row['last_name'],
                'email' => (string) $row['email'],
                'student_no' => (string) ($row['student_no'] ?? ''),
                'contact_no' => (string) $row['contact_no'],
                'year_level' => (string) $row['year_level'],
                'section' => (string) $row['section'],
                'academic_year' => (string) $row['academic_year'],
                // Checked when the candidacy was qualified.
                'confirmations' => array_keys(config('elections.confirmations')),
                'reason' => trim($electionRecord) !== '' ? trim($electionRecord) : "Elected, AY {$row['academic_year']} (candidacy #{$id})",
            ]);
            $this->setStatus($id, 'elected', trim($electionRecord), true);

            return $userId;
        });
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->query('c.candidacy_id = ?', [$id])[0] ?? null;
    }

    /** The owner or the MSW Committee Head may open a candidacy's COR and grades. */
    public function canView(array $row): bool
    {
        return (int) $row['user_id'] === current_user_id() || can('admin.users');
    }

    /** @return array<string, mixed>|null A candidacy that still counts for this academic year. */
    private function activeFor(int $userId, string $academicYear): ?array
    {
        return $this->query("c.user_id = ? AND c.academic_year = ? AND c.status <> 'withdrawn'", [$userId, $academicYear])[0] ?? null;
    }

    private function setStatus(int $id, string $status, string $remarks, bool $byStaff): void
    {
        Database::connection()->prepare('UPDATE election_candidacies SET status = ?, remarks = ?, decided_by = ?, decided_at = ? WHERE candidacy_id = ?')
            ->execute([$status, $remarks === '' ? null : mb_substr($remarks, 0, 255), $byStaff ? current_user_id() : null, date('Y-m-d H:i:s'), $id]);
        AuditService::log('candidacy.' . $status, 'election_candidacy', $id);
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function query(string $where, array $params): array
    {
        $statement = Database::connection()->prepare(
            "SELECT c.*, p.title AS position_title, IF(p.committee_id IS NULL, 'Executive Board', 'Year-Level Representatives') AS position_group,
                    u.first_name, u.last_name, u.email, u.student_no
             FROM election_candidacies c
             JOIN positions p ON p.position_id = c.position_id
             JOIN users u ON u.user_id = c.user_id
             WHERE {$where}
             ORDER BY c.academic_year DESC, p.committee_id IS NOT NULL, p.position_id, c.candidacy_id DESC"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
