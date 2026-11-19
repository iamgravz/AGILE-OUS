<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\View;
use App\Models\CredentialRepository;
use App\Models\ApplicantRepository;
use App\Models\MemberRepository;
use App\Models\NotificationRepository;
use App\Models\UserRepository;
use DomainException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use InvalidArgumentException;
use RuntimeException;

/**
 * Digital ID and certificate of membership. PDFs live in storage/credentials and leave only through FileController.
 * A new one waits for the MSW Committee Head, and members see only approved ones that are not revoked (BR-19).
 */
final class CredentialService
{
    public const TYPES = ['digital_id' => 'Digital ID', 'certificate' => 'Certificate of Membership'];

    public const REASON_NEW_MEMBER = 'new_member';
    public const REASON_ROLE_CHANGE = 'role_change';
    public const REASON_REISSUE = 'reissue';
    public const REASONS = [self::REASON_NEW_MEMBER => 'New member', self::REASON_ROLE_CHANGE => 'Role change', self::REASON_REISSUE => 'Reissue'];

    public const STATE_VALID = 'valid';
    public const STATE_NOT_ISSUED = 'not_issued';
    public const STATE_REVOKED = 'revoked';

    /** Section 8. Approved to revoked is revoked_at, set on the row. */
    private const TRANSITIONS = [
        'pending_approval' => ['approved', 'returned'],
        'returned' => ['pending_approval'],
        'approved' => [],
    ];

    private const SERIAL_PREFIXES = ['digital_id' => 'ID', 'certificate' => 'CERT'];
    private const VIEWS = ['digital_id' => 'credentials/id_card', 'certificate' => 'credentials/certificate'];

    // In points, width first. CR80 is 85.6 x 53.98 mm. A4 landscape is 297 x 210 mm.
    private const PAPER = ['digital_id' => [0, 0, 242.65, 153.01], 'certificate' => [0, 0, 841.89, 595.28]];

    // Above section 11's example, because one person may check several credentials at an event.
    private const VERIFY_MAX_ATTEMPTS = 30;
    private const VERIFY_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly CredentialRepository $credentials = new CredentialRepository(),
        private readonly MemberRepository $members = new MemberRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    /** Issues whichever credentials the member has no valid copy of. A member applying for a position keeps theirs. */
    public function issueFor(int $memberId): void
    {
        authorize_any('application.decide', 'account.approve', 'credential.reissue');

        Database::transaction(function () use ($memberId): void {
            $member = $this->lockMember($memberId);

            foreach (array_keys(self::TYPES) as $type) {
                if ($this->credentials->findActive($memberId, $type) === null) {
                    $this->issue($member, $type, self::REASON_NEW_MEMBER);
                }
            }
        });
    }

    /**
     * New credentials, waiting for approval. A role change or expiry revokes the old ones at once, because the role
     * printed on them is wrong. A manual reissue leaves the approved one valid until its replacement is approved.
     */
    public function reissue(int $memberId, string $reason, string $kind = self::REASON_ROLE_CHANGE): void
    {
        authorize_any('credential.reissue', 'appointment.approve', 'eligibility.manage', 'eligibility.decide_msw_head', 'admin.settings');

        if (trim($reason) === '') {
            throw new DomainException('A reason is required.');
        }

        Database::transaction(function () use ($memberId, $reason, $kind): void {
            $member = $this->lockMember($memberId);

            if ($kind === self::REASON_REISSUE) {
                $this->issueReplacements($member, $reason);

                return;
            }

            $revoked = $this->credentials->revokeActive($memberId);

            AuditService::log('credential.reissued', 'member', $memberId, ['reason' => mb_substr(trim($reason), 0, 255), 'revoked' => $revoked]);

            foreach (array_keys(self::TYPES) as $type) {
                $this->issue($member, $type, $kind);
            }
        });
    }

    /**
     * MSW Committee Head only. Regenerates each PDF with the approver and date, revokes what it replaces, and emails each
     * member once. Anything no longer pending is skipped.
     *
     * @param list<int> $ids
     * @return int How many were approved.
     */
    public function approve(array $ids, int $actorId): int
    {
        authorize('credential.approve');

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            throw new DomainException('Please select at least one to approve.');
        }

        $oldFiles = [];

        $count = Database::transaction(function () use ($ids, $actorId, &$oldFiles): int {
            $approver = (new UserRepository())->find($actorId);
            $name = $approver['first_name'] . ' ' . $approver['last_name'];
            $now = date('Y-m-d H:i:s');
            $membersDone = [];
            $count = 0;

            foreach ($ids as $id) {
                $row = $this->credentials->findForUpdate($id);

                if ($row === null || $row['revoked_at'] !== null || $row['status'] !== 'pending_approval') {
                    continue;
                }

                $memberId = (int) $row['member_id'];
                $member = $this->lockMember($memberId);
                $pdf = $this->render($row['type'], $member, $row['serial_no'], $row['qr_token'], $now, $name);
                $this->assertTransition($row['status'], 'approved');
                $this->credentials->update($id, [
                    'status' => 'approved',
                    'file_path' => $this->save($pdf),
                    'issued_at' => $now,
                    'approved_by' => $actorId,
                    'approved_at' => $now,
                    'review_remarks' => null,
                ]);
                $oldFiles[] = $row['file_path'];

                if ($row['replaces_credential_id'] !== null) {
                    $this->credentials->update((int) $row['replaces_credential_id'], ['revoked_at' => $now]);
                }

                AuditService::log('credential.approved', 'digital_credential', $id, ['member_id' => $memberId, 'type' => $row['type'], 'actor_id' => $actorId]);
                $membersDone[$memberId] = $member;
                $count++;
            }

            foreach ($membersDone as $member) {
                $this->notifyReady($member);
            }

            AuditService::log('credential.bulk_approve', null, null, ['count' => $count, 'actor_id' => $actorId]);

            return $count;
        });

        $this->removeFiles($oldFiles);

        return $count;
    }

    /** @return int How many were approved. */
    public function approveAllPending(int $actorId): int
    {
        authorize('credential.approve');

        $ids = $this->credentials->pendingIds();

        return $ids === [] ? 0 : $this->approve($ids, $actorId);
    }

    /** MSW Committee Head only. The remarks stay internal: the member sees the same message as while it is pending. */
    public function returnForCorrection(int $id, string $remarks, int $actorId): void
    {
        authorize('credential.approve');

        if (trim($remarks) === '') {
            throw new DomainException('Please write why it is being returned.');
        }

        Database::transaction(function () use ($id, $remarks, $actorId): void {
            $row = $this->credentials->findForUpdate($id);

            if ($row === null || $row['revoked_at'] !== null) {
                throw new DomainException('This credential is no longer available.');
            }

            $this->assertTransition($row['status'], 'returned');
            $this->credentials->update($id, ['status' => 'returned', 'review_remarks' => trim($remarks)]);
            AuditService::log('credential.returned', 'digital_credential', $id, ['member_id' => (int) $row['member_id'], 'remarks' => mb_substr(trim($remarks), 0, 255), 'actor_id' => $actorId]);
        });
    }

    /** MSW Committee Head only, after fixing the cause. A new file from the member's current details, waiting for approval. */
    public function regenerate(int $id, int $actorId): void
    {
        authorize('credential.approve');

        $oldFile = Database::transaction(function () use ($id, $actorId): string {
            $row = $this->credentials->findForUpdate($id);

            if ($row === null || $row['revoked_at'] !== null) {
                throw new DomainException('This credential is no longer available.');
            }

            $this->assertTransition($row['status'], 'pending_approval');
            $now = date('Y-m-d H:i:s');
            $pdf = $this->render($row['type'], $this->lockMember((int) $row['member_id']), $row['serial_no'], $row['qr_token'], $now);
            $this->credentials->update($id, ['status' => 'pending_approval', 'file_path' => $this->save($pdf), 'issued_at' => $now, 'review_remarks' => null]);
            AuditService::log('credential.regenerated', 'digital_credential', $id, ['member_id' => (int) $row['member_id'], 'actor_id' => $actorId]);

            return $row['file_path'];
        });

        $this->removeFiles([$oldFile]);
    }

    /**
     * @param array{type?: string, reason?: string} $filters
     * @return array{pending: list<array<string, mixed>>, returned: list<array<string, mixed>>}
     */
    public function forReview(array $filters): array
    {
        authorize('credential.approve');

        return ['pending' => $this->credentials->forReview('pending_approval', $filters), 'returned' => $this->credentials->forReview('returned', $filters)];
    }

    public function pendingCount(): int
    {
        return $this->credentials->pendingCount();
    }

    /**
     * For jobs/credential_digest.php. Nobody is logged in, so this checks nothing. Once a day per person.
     *
     * @return int How many digests were queued.
     */
    public function sendDigest(): int
    {
        $count = $this->credentials->pendingCount();

        if ($count === 0) {
            return 0;
        }

        $notifications = new NotificationService();
        $sent = 0;

        foreach ((new UserRepository())->activeIdsByRole('msw_head') as $userId) {
            if (!(new NotificationRepository())->sentToday((int) $userId, 'CREDENTIALS_FOR_APPROVAL')) {
                $notifications->notify((int) $userId, 'CREDENTIALS_FOR_APPROVAL', ['count' => $count, 'link' => '/credentials/approvals'], null, ['email']);
                $sent++;
            }
        }

        return $sent;
    }

    /** @param array<string, mixed> $member */
    private function issueReplacements(array $member, string $reason): void
    {
        $memberId = (int) $member['member_id'];
        $active = array_filter($this->credentials->forMember($memberId), static fn (array $c): bool => $c['revoked_at'] === null);
        $now = date('Y-m-d H:i:s');

        AuditService::log('credential.reissued', 'member', $memberId, ['reason' => mb_substr(trim($reason), 0, 255), 'kept_until_approved' => true]);

        foreach (array_keys(self::TYPES) as $type) {
            $replaces = null;

            foreach ($active as $credential) {
                if ($credential['type'] !== $type) {
                    continue;
                }

                if ($credential['status'] === 'approved') {
                    $replaces = (int) $credential['credential_id'];
                } else {
                    // An older copy still under review is superseded by this one.
                    $this->credentials->update((int) $credential['credential_id'], ['revoked_at' => $now]);
                }
            }

            $this->issue($member, $type, self::REASON_REISSUE, $replaces);
        }
    }

    /**
     * Someone who has not registered yet is told to create their account, since that is how they will see these.
     *
     * @param array<string, mixed> $member A row from MemberRepository::findDetailed().
     */
    private function notifyReady(array $member): void
    {
        $notifications = new NotificationService();

        if ($member['user_id'] !== null) {
            $notifications->notify((int) $member['user_id'], 'CREDENTIAL_READY', ['link_text' => 'Download them from your member profile'], '/account');

            return;
        }

        $applicant = (new ApplicantRepository())->find((int) $member['applicant_id']);
        $notifications->notifyEmail($applicant['email'], 'CREDENTIAL_READY', ['name' => $applicant['first_name'], 'link_text' => 'Create your account to download them', 'link' => '/register']);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    private function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new DomainException("A credential cannot move from {$from} to {$to}.");
        }
    }

    /** @param list<string> $filePaths Files replaced by a committed change. A rollback must not lose them, so this runs after. */
    private function removeFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            $path = self::path($filePath);

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** Counts the attempt, and returns true when this address has checked too many credentials. */
    public function verifyBlocked(string $ip): bool
    {
        return $this->limiter->hit("verify:{$ip}", self::VERIFY_MAX_ATTEMPTS, self::VERIFY_WINDOW_MINUTES);
    }

    /**
     * The public verification result. Holds only what the page may show.
     *
     * @return array{valid: bool, state: string, revoked_at: ?string, name?: string, member_no?: string, role?: string, issued_at?: string}|null
     */
    public function verify(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        $credential = $this->credentials->findByToken($token);

        if ($credential === null) {
            return null;
        }

        // A credential under review says nothing about the person, because it does not count yet.
        if ($credential['revoked_at'] === null && $credential['status'] !== 'approved') {
            return ['valid' => false, 'state' => self::STATE_NOT_ISSUED, 'revoked_at' => null];
        }

        $member = $this->members->findDetailed((int) $credential['member_id']);

        return [
            'valid' => $credential['revoked_at'] === null,
            'state' => $credential['revoked_at'] === null ? self::STATE_VALID : self::STATE_REVOKED,
            'revoked_at' => $credential['revoked_at'],
            'name' => $member['first_name'] . ' ' . $member['last_name'],
            'member_no' => $member['member_no'],
            'role' => MembershipService::roleLabel($member),
            'issued_at' => $credential['issued_at'],
        ];
    }

    /** The absolute path of a stored credential. Refuses anything that would leave storage/credentials. */
    public static function path(string $filePath): string
    {
        if (preg_match('#^[0-9]{4}/[a-f0-9]{32}\.pdf$#', $filePath) !== 1) {
            throw new InvalidArgumentException('Invalid credential file path.');
        }

        return Config::basePath('storage/credentials/' . $filePath);
    }

    /** @return array<string, mixed> */
    private function lockMember(int $memberId): array
    {
        $this->members->lockRow($memberId);

        return $this->members->findDetailed($memberId) ?? throw new DomainException('This member no longer exists.');
    }

    /** @param array<string, mixed> $member */
    private function issue(array $member, string $type, string $reason, ?int $replaces = null): void
    {
        $memberId = (int) $member['member_id'];
        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $sequence = $this->credentials->countForMember($memberId, $type) + 1;
        $serial = sprintf('%s-%s-%02d', self::SERIAL_PREFIXES[$type], $member['member_no'], $sequence);

        $pdf = $this->render($type, $member, $serial, $token, $now);
        $filePath = $this->save($pdf);

        $id = $this->credentials->insert([
            'member_id' => $memberId,
            'type' => $type,
            'serial_no' => $serial,
            'qr_token' => $token,
            'file_path' => $filePath,
            'status' => 'pending_approval',
            'reason' => $reason,
            'replaces_credential_id' => $replaces,
            'issued_at' => $now,
        ]);
        AuditService::log('credential.issued', 'digital_credential', $id, ['member_id' => $memberId, 'type' => $type, 'serial_no' => $serial]);
    }

    /** @param array<string, mixed> $member */
    private function render(string $type, array $member, string $serial, string $token, string $issuedAt, ?string $approvedBy = null): string
    {
        $html = View::render(self::VIEWS[$type], [
            'member' => $member,
            'name' => $member['first_name'] . ' ' . $member['last_name'],
            'role' => MembershipService::roleLabel($member),
            'serial' => $serial,
            'issuedAt' => $issuedAt,
            'approval' => $approvedBy === null ? 'Pending approval. Not valid yet.' : 'Approved by ' . $approvedBy . ', ' . date('F j, Y', strtotime($issuedAt)),
            'logo' => $this->imageData(Config::basePath('public/assets/img/logo.png')),
            'photo' => $member['photo_path'] === null ? null : $this->imageData($this->files->path($member['photo_path'])),
            'qr' => $this->qrData(url('/verify/' . $token)),
        ]);

        // Every image is embedded, so the PDF engine has no reason to reach the network or the disk.
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(Config::basePath('storage/cache'));
        $options->setTempDir(Config::basePath('storage/cache'));
        $options->setFontCache(Config::basePath('storage/cache'));
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper(self::PAPER[$type]);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function save(string $pdf): string
    {
        $filePath = date('Y') . '/' . bin2hex(random_bytes(16)) . '.pdf';
        $path = self::path($filePath);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the credentials folder.');
        }

        if (file_put_contents($path, $pdf, LOCK_EX) === false) {
            throw new RuntimeException('Could not save a credential.');
        }

        // The database cannot undo a saved file, so a failed approval or role change removes it here.
        Database::onRollback(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return $filePath;
    }

    private function imageData(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $mimeType = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return 'data:' . $mimeType . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    private function qrData(string $url): string
    {
        return (new PngWriter())->write(new QrCode(data: $url, size: 300, margin: 8))->getDataUri();
    }
}
