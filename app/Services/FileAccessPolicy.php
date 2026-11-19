<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ForbiddenException;
use App\Models\ApplicationDocumentRepository;
use App\Models\CredentialRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\UserRepository;

/**
 * Decides who may open each kind of stored file. Each method checks permission and ownership, then
 * returns the file to send, or null when no such file exists. Unbuilt types deny everyone, and the
 * module's step replaces the stub by asking its own service.
 *
 * @phpstan-type FileRecord array{stored_path: string, download_name: string, inline: bool, log: bool}
 */
final class FileAccessPolicy
{
    public const TYPES = ['avatar', 'application_document', 'welfare_attachment', 'eligibility_document', 'credential'];

    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    /**
     * $id is the user id. People may see their own photo, and staff who manage member records may see others.
     *
     * @return FileRecord|null
     */
    public function avatar(int $id): ?array
    {
        $isOwner = current_user_id() === $id;

        if (!$isOwner && !can('member.view') && !can('admin.users')) {
            throw new ForbiddenException('file.avatar');
        }

        $photo = $this->users->find($id)['photo_path'] ?? null;

        if ($photo === null) {
            return null;
        }

        // The top bar requests your own photo on every page, which would bury the audit trail.
        return ['stored_path' => $photo, 'download_name' => 'avatar.jpg', 'inline' => true, 'log' => !$isOwner];
    }

    /**
     * Someone without an account, for the one application they proved is theirs. Everyone else gets a 403.
     *
     * @return FileRecord|null
     */
    public function applicantDocument(int $id): ?array
    {
        $access = ApplicantAccessService::current();
        $document = (new ApplicationDocumentRepository())->find($id);

        if ($access === null || current_user_id() !== null || $document === null || (int) $document['application_id'] !== $access['application_id']) {
            throw new ForbiddenException('file.applicant_document');
        }

        return ['stored_path' => $document['stored_path'], 'download_name' => $document['original_name'], 'inline' => false, 'log' => true];
    }

    /**
     * The owner, MSW Committee members and the MSW Committee Head. Anyone else gets a 403 whether or not it exists.
     *
     * @return FileRecord|null
     */
    public function application_document(int $id): ?array
    {
        $document = (new ApplicationDocumentRepository())->findWithOwner($id);
        $isOwner = $document !== null && (int) $document['owner_id'] === current_user_id();
        $membership = new MembershipService();
        $application = $document === null ? null : (new MembershipApplicationRepository())->findDetailed((int) $document['application_id']);
        $isCommitteeHead = $application !== null && $membership->isOwnCommitteeHead($application);

        if (!$isOwner && !can('document.verify') && !$isCommitteeHead) {
            throw new ForbiddenException('file.application_document');
        }

        if ($document === null) {
            return null;
        }

        return ['stored_path' => $document['stored_path'], 'download_name' => $document['original_name'], 'inline' => false, 'log' => true, 'previewable' => $isCommitteeHead];
    }

    /**
     * The submitting user, and the MSW Committee Head and President. A guest has no account, so cannot download.
     *
     * @return array{contents: string, mime_type: string, download_name: string, inline: bool, log: bool}|null
     */
    public function welfare_attachment(int $id): ?array
    {
        return (new WelfareService())->openAttachment($id);
    }

    /**
     * The owner and the screening's reviewer.
     *
     * @return array{stored_path: string, download_name: string, inline: bool, log: bool, previewable: bool}|null
     */
    public function eligibility_document(int $id): ?array
    {
        return (new EligibilityService())->openDocument($id);
    }

    /**
     * The owner only, and only the current copy, because a revoked PDF still says it is valid.
     * Anyone else gets a 403 whether or not it exists.
     *
     * @return array{absolute_path: string, download_name: string, inline: bool, log: bool}|null
     */
    public function credential(int $id): ?array
    {
        $credential = (new CredentialRepository())->findWithOwner($id);

        $mayReview = can('credential.approve');
        $isOwner = $credential !== null && $credential['owner_id'] !== null && (int) $credential['owner_id'] === current_user_id();
        $isReady = $credential !== null && $credential['status'] === 'approved' && $credential['revoked_at'] === null;

        // BR-19: the owner gets approved, unrevoked copies. The MSW Committee Head previews any status.
        if ($credential === null ? !$mayReview : !$mayReview && !($isOwner && $isReady)) {
            throw new ForbiddenException('file.credential');
        }

        if ($credential === null) {
            return null;
        }

        return [
            'previewable' => $mayReview,
            'absolute_path' => CredentialService::path($credential['file_path']),
            'download_name' => $credential['serial_no'] . '.pdf',
            'inline' => false,
            'log' => true,
        ];
    }
}
