<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MemberRepository;

/**
 * The member verification page (/verify/member/<token>): the member's current digital ID and that they are an active
 * member. The QR is part of AGILE's own ID template, so the system no longer hands out QR images;
 * the page stays for a token set on a member. The token is random and says nothing about the member.
 */
final class MemberVerificationService
{
    public function __construct(private readonly MemberRepository $members = new MemberRepository())
    {
    }

    /** The member's token, made on first use. */
    public function token(int $memberId): string
    {
        $token = Database::connection()->prepare('SELECT verify_token FROM members WHERE member_id = ?');
        $token->execute([$memberId]);
        $value = $token->fetchColumn();

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $value = bin2hex(random_bytes(32));
        Database::connection()->prepare('UPDATE members SET verify_token = ? WHERE member_id = ? AND verify_token IS NULL')->execute([$value, $memberId]);

        // Another request may have made one first; theirs counts.
        $token->execute([$memberId]);

        return (string) $token->fetchColumn();
    }

    public function url(int $memberId): string
    {
        return url('/verify/member/' . $this->token($memberId));
    }

    /**
     * What the public page may show: the member, whether they are active, their role, and their current ID.
     *
     * @return array{name: string, member_no: string, active: bool, role: string, committees: list<string>, joined_at: string, id: array{credential_id: int, serial_no: string, issued_at: string, is_image: bool}|null}|null
     */
    public function verify(string $token): ?array
    {
        $memberId = $this->memberIdFor($token);

        if ($memberId === null) {
            return null;
        }

        $member = $this->members->findDetailed($memberId);

        if ($member === null) {
            return null;
        }

        $current = $this->currentId($memberId);
        $committees = array_column((new MemberCommitteeService())->extras($memberId), 'name');

        return [
            'name' => $member['first_name'] . ' ' . $member['last_name'],
            'member_no' => (string) $member['member_no'],
            // A member record exists only while the membership is active (members.status is always 'active').
            'active' => $member['status'] === 'active',
            'role' => MembershipService::roleLabel($member),
            'committees' => $committees,
            'joined_at' => (string) $member['joined_at'],
            'id' => $current === null ? null : [
                'credential_id' => (int) $current['credential_id'],
                'serial_no' => (string) $current['serial_no'],
                'issued_at' => (string) $current['issued_at'],
                'is_image' => preg_match('/\.(jpg|jpeg|png)$/', (string) $current['file_path']) === 1,
            ],
        ];
    }

    /** The absolute path of the member's current ID, for the public page, or null. */
    public function currentIdPath(string $token): ?string
    {
        $memberId = $this->memberIdFor($token);
        $current = $memberId === null ? null : $this->currentId($memberId);

        return $current === null ? null : CredentialService::path((string) $current['file_path']);
    }

    private function memberIdFor(string $token): ?int
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT member_id FROM members WHERE verify_token = ?');
        $statement->execute([$token]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return array<string, mixed>|null The approved, current digital ID. */
    private function currentId(int $memberId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT credential_id, serial_no, issued_at, file_path FROM digital_credentials
             WHERE member_id = ? AND type = 'digital_id' AND status = 'approved' AND revoked_at IS NULL
             ORDER BY issued_at DESC, credential_id DESC LIMIT 1"
        );
        $statement->execute([$memberId]);

        return $statement->fetch() ?: null;
    }
}
