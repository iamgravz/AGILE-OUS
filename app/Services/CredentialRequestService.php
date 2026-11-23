<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CredentialRepository;
use App\Models\MemberRepository;
use App\Models\UserRepository;
use DomainException;
use RuntimeException;

/**
 * ID and certificate requests (Nov 23, 2026). A member asks for their digital ID or certificate of membership; the
 * MSW Committee Head makes it from AGILE's own template and uploads it here, or declines with the reason. An upload
 * becomes the member's current, approved credential of that type, and replaces any earlier one.
 */
final class CredentialRequestService
{
    public const STATUSES = ['requested' => 'Requested', 'issued' => 'Ready', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];
    private const SERIAL_PREFIXES = ['digital_id' => 'ID', 'certificate' => 'CERT'];

    public function __construct(
        private readonly CredentialRepository $credentials = new CredentialRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    /** The signed-in member's member_id, or null for an account that is not a member. */
    public function ownMemberId(): ?int
    {
        $userId = current_user_id();
        $member = $userId === null ? null : (new MemberRepository())->findByUser($userId);

        return $member === null ? null : (int) $member['member_id'];
    }

    /** @return list<array<string, mixed>> The signed-in member's requests, newest first. */
    public function mine(): array
    {
        $memberId = $this->ownMemberId();

        return $memberId === null ? [] : $this->query('r.member_id = ?', [$memberId]);
    }

    /** The picture for the ID: a half-body professional photo, JPG or PNG. */
    public const PHOTO_TYPES = ['jpg', 'jpeg', 'png'];

    /**
     * @param array<string, mixed>|null $photo from $_FILES; required for the digital ID
     * @throws DomainException|FileRejectedException
     */
    public function request(string $type, string $note, ?array $photo = null): int
    {
        $memberId = $this->ownMemberId() ?? throw new DomainException('Only AGILE members can request an ID or certificate.');

        if (!isset(CredentialService::TYPES[$type])) {
            throw new DomainException('Please choose the digital ID or the certificate.');
        }

        if ($this->query("r.member_id = ? AND r.type = ? AND r.status = 'requested'", [$memberId, $type]) !== []) {
            throw new DomainException('You already asked for this. The MSW Committee will upload it soon.');
        }

        $hasPhoto = $photo !== null && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($type === 'digital_id' && !$hasPhoto) {
            throw new FileRejectedException('Please upload a half-body professional picture for your ID.');
        }

        $storage = new FileStorageService();
        $photoPath = null;

        if ($hasPhoto) {
            $storage->validate($photo, self::PHOTO_TYPES);
            $photoPath = $storage->store($photo, 'id-photos', self::PHOTO_TYPES)['stored_path'];
        }

        return Database::transaction(function () use ($memberId, $type, $note, $photoPath): int {
            Database::connection()->prepare('INSERT INTO credential_requests (member_id, type, note, photo_path) VALUES (?, ?, ?, ?)')
                ->execute([$memberId, $type, trim($note) === '' ? null : mb_substr(trim($note), 0, 255), $photoPath]);
            $id = (int) Database::connection()->lastInsertId();
            AuditService::log('credential.requested', 'credential_request', $id, ['type' => $type]);

            // The MSW Committee Head hears about it in the app.
            foreach ((new UserRepository())->activeIdsByRole('msw_head') as $headId) {
                $this->notifications->notify((int) $headId, 'CREDENTIAL_REQUESTED', ['type' => CredentialService::TYPES[$type], 'link' => '/credentials/requests'], '/credentials/requests', ['in_app']);
            }

            return $id;
        });
    }

    public function cancel(int $id): void
    {
        $row = $this->find($id);

        if ($row === null || (int) $row['member_id'] !== $this->ownMemberId()) {
            throw new DomainException('This request is not yours.');
        }

        if ($row['status'] !== 'requested') {
            throw new DomainException('This request was already handled.');
        }

        $this->close($id, 'cancelled', null, null);
    }

    /** @return list<array<string, mixed>> */
    public function list(string $status): array
    {
        authorize('credential.approve');

        return isset(self::STATUSES[$status]) ? $this->query('r.status = ?', [$status]) : $this->query('1 = 1', []);
    }

    public function openCount(): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM credential_requests WHERE status = 'requested'")->fetchColumn();
    }

    /**
     * The MSW Committee Head uploads the file made from AGILE's template. It becomes the member's approved credential of
     * that type; an earlier one of the same type stops being current.
     *
     * @param array<string, mixed> $file from $_FILES
     * @throws DomainException|FileRejectedException
     */
    public function upload(int $id, array $file): void
    {
        authorize('credential.approve');

        $row = $this->find($id) ?? throw new DomainException('This request no longer exists.');

        if ($row['status'] !== 'requested') {
            throw new DomainException('This request was already handled.');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new FileRejectedException('Please choose the file to upload.');
        }

        $checked = (new FileStorageService())->validate($file, config('credentials.upload_types'));
        $filePath = date('Y') . '/' . bin2hex(random_bytes(16)) . '.' . $checked['extension'];
        $path = CredentialService::path($filePath);

        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0750, true) && !is_dir(dirname($path))) {
            throw new RuntimeException('Could not create the credentials folder.');
        }

        if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }

        // The database cannot undo a saved file, so a failed save moves it out of the way again (never deletes).
        Database::onRollback(static function () use ($path): void {
            if (is_file($path)) {
                @rename($path, $path . '.unsaved');
            }
        });

        Database::transaction(function () use ($row, $id, $filePath): void {
            $memberId = (int) $row['member_id'];
            $type = (string) $row['type'];
            $now = date('Y-m-d H:i:s');
            $previous = $this->credentials->findActive($memberId, $type);

            if ($previous !== null) {
                $this->credentials->update((int) $previous['credential_id'], ['revoked_at' => $now]);
            }

            $sequence = $this->credentials->countForMember($memberId, $type) + 1;
            $credentialId = $this->credentials->insert([
                'member_id' => $memberId,
                'type' => $type,
                'serial_no' => sprintf('%s-%s-%02d', self::SERIAL_PREFIXES[$type], $row['member_no'], $sequence),
                'qr_token' => bin2hex(random_bytes(32)),
                'file_path' => $filePath,
                'status' => 'approved',
                'reason' => $previous === null ? CredentialService::REASON_NEW_MEMBER : CredentialService::REASON_REISSUE,
                'replaces_credential_id' => $previous === null ? null : (int) $previous['credential_id'],
                'issued_at' => $now,
                'approved_by' => current_user_id(),
                'approved_at' => $now,
            ]);
            $this->close($id, 'issued', $credentialId, null);

            if ($row['user_id'] !== null) {
                $this->notifications->notify((int) $row['user_id'], 'CREDENTIAL_UPLOADED', ['type' => CredentialService::TYPES[$type], 'link_text' => 'Download it from My Account', 'link' => '/account'], '/account');
            }
        });
    }

    public function decline(int $id, string $remarks): void
    {
        authorize('credential.approve');

        if (trim($remarks) === '') {
            throw new DomainException('Please write why. The member sees this.');
        }

        $row = $this->find($id) ?? throw new DomainException('This request no longer exists.');

        if ($row['status'] !== 'requested') {
            throw new DomainException('This request was already handled.');
        }

        Database::transaction(function () use ($row, $id, $remarks): void {
            $this->close($id, 'declined', null, trim($remarks));

            if ($row['user_id'] !== null) {
                $this->notifications->notify((int) $row['user_id'], 'CREDENTIAL_DECLINED', ['type' => CredentialService::TYPES[$row['type']], 'remarks' => trim($remarks), 'link' => '/account'], '/account');
            }
        });
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->query('r.request_id = ?', [$id])[0] ?? null;
    }

    private function close(int $id, string $status, ?int $credentialId, ?string $remarks): void
    {
        Database::connection()->prepare('UPDATE credential_requests SET status = ?, credential_id = ?, remarks = ?, handled_by = ?, handled_at = ? WHERE request_id = ?')
            ->execute([$status, $credentialId, $remarks === null ? null : mb_substr($remarks, 0, 255), current_user_id(), date('Y-m-d H:i:s'), $id]);
        AuditService::log('credential.request_' . $status, 'credential_request', $id);
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function query(string $where, array $params): array
    {
        $statement = Database::connection()->prepare(
            "SELECT r.*, m.member_no, m.user_id, COALESCE(u.first_name, a.first_name) AS first_name, COALESCE(u.last_name, a.last_name) AS last_name,
                    COALESCE(u.email, a.email) AS email, p.title AS position_title, c.name AS committee_name
             FROM credential_requests r
             JOIN members m ON m.member_id = r.member_id
             LEFT JOIN users u ON u.user_id = m.user_id
             LEFT JOIN applicants a ON a.applicant_id = m.applicant_id
             LEFT JOIN positions p ON p.position_id = m.position_id
             LEFT JOIN committees c ON c.committee_id = p.committee_id
             WHERE {$where}
             ORDER BY r.status = 'requested' DESC, r.created_at DESC, r.request_id DESC"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
