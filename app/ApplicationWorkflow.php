<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use PDO;
use RuntimeException;

/**
 * Interview/document verification and membership application status workflow.
 * Only authorized MSW roles can mutate; completed decisions are immutable.
 */
final class ApplicationWorkflow {
    public const TRANSITIONS = [
        'submitted' => ['screening'],
        'screening' => ['for_interview', 'for_verification', 'rejected', 'waitlisted'],
        'for_interview' => ['for_verification', 'rejected', 'waitlisted'],
        'for_verification' => ['approved', 'rejected', 'waitlisted'],
        'waitlisted' => ['for_interview', 'for_verification', 'rejected'],
        'approved' => [],
        'rejected' => [],
    ];
    private const FINAL_DECISIONS = ['approved','rejected','waitlisted'];

    public static function find(int $id): ?array {
        $q = \db()->prepare('SELECT * FROM membership_applications WHERE id = ? LIMIT 1');
        $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    public static function findForActor(int $id, array $actor): ?array {
        $row = self::find($id);
        if (!$row) { return null; }
        if ($actor['role'] === 'msw_head') { return $row; }
        if ($actor['role'] === 'msw_member' && (int)($row['assigned_to'] ?? 0) === (int)$actor['id']) {
            return $row;
        }
        throw new DomainException('This application is not assigned to your account.');
    }

    public static function assignReviewer(int $id, array $actor, ?int $reviewerId): void {
        if ($actor['role'] !== 'msw_head') { throw new DomainException('Only the MSW Head may assign applications.'); }
        $pdo = \db();
        $pdo->beginTransaction();
        try {
            $application = self::locked($pdo, $id);
            if (in_array($application['status'], ['approved', 'rejected'], true)) {
                throw new DomainException('Cannot reassign a finalized application.');
            }
            if ($reviewerId !== null) {
                $q = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'msw_member' AND is_active = 1 LIMIT 1");
                $q->execute([$reviewerId]);
                if (!$q->fetchColumn()) { throw new DomainException('Reviewer must be an active MSW Member.'); }
            }
            $q = $pdo->prepare('UPDATE membership_applications SET assigned_to = ? WHERE id = ?');
            $q->execute([$reviewerId, $id]);
            \audit((int)$actor['id'], 'application.reassigned', 'membership_application', $id);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function availableTargets(string $status, string $role): array {
        if (!in_array($role, ['msw_head','msw_member'], true)) { return []; }
        $targets = self::TRANSITIONS[$status] ?? [];
        if ($role !== 'msw_head') {
            $targets = array_values(array_diff($targets, self::FINAL_DECISIONS));
        }
        return $targets;
    }

    public static function updateVerification(int $id, array $actor, bool $interview, bool $documents, string $note): void {
        if ($actor['role'] !== 'msw_head') { throw new DomainException('Only the MSW Head can verify final interview/document prerequisites.'); }
        if (mb_strlen(trim($note)) < 10 || mb_strlen($note) > 1000) { throw new DomainException('Verification notes must be 10–1,000 characters.'); }
        $pdo = \db();
        $pdo->beginTransaction();
        try {
            $row = self::locked($pdo, $id);
            if (in_array($row['status'], ['approved','rejected'], true)) { throw new DomainException('Finalized applications cannot be changed.'); }
            $q = $pdo->prepare('UPDATE membership_applications SET interview_completed = ?, documents_verified = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
            $q->execute([(int)$interview, (int)$documents, (int)$actor['id'], $id]);
            $history = $pdo->prepare('INSERT INTO application_verification_history
                (application_id,actor_user_id,interview_completed,documents_verified,note)
                VALUES (?,?,?,?,?)');
            $history->execute([$id,(int)$actor['id'],(int)$interview,(int)$documents,trim($note)]);
            \audit((int)$actor['id'], 'verification.updated', 'membership_application', $id);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function changeStatus(int $id, array $actor, string $newStatus, string $note): void {
        if (!in_array($actor['role'], ['msw_head','msw_member'], true)) {
            throw new DomainException('You cannot change application status.');
        }
        $note = trim($note);
        if (mb_strlen($note) < 10 || mb_strlen($note) > 1000) { throw new DomainException('Review note must be 10–1,000 characters.'); }
        $pdo = \db();
        $pdo->beginTransaction();
        try {
            $row = self::locked($pdo, $id);
            if ($actor['role'] === 'msw_member' && (int)($row['assigned_to'] ?? 0) !== (int)$actor['id']) {
                throw new DomainException('This application is not assigned to your account.');
            }
            $oldStatus = $row['status'];
            if (!in_array($newStatus, self::availableTargets($oldStatus, $actor['role']), true)) {
                throw new DomainException('This status transition is not authorized.');
            }
            if ($newStatus === 'approved' && (!(bool)$row['interview_completed'] || !(bool)$row['documents_verified'])) {
                throw new DomainException('Approval requires a completed interview and verified documents.');
            }
            $q = $pdo->prepare('UPDATE membership_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = ?');
            $q->execute([$newStatus,(int)$actor['id'],$id,$oldStatus]);
            if ($q->rowCount() !== 1) { throw new RuntimeException('Application changed during review.'); }
            if ($newStatus === 'approved') {
                Recruitment::finalizeApprovedMembership($pdo,$row,$actor);
            }
            $q = $pdo->prepare('INSERT INTO application_status_history (application_id, actor_user_id, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)');
            $q->execute([$id,(int)$actor['id'],$oldStatus,$newStatus,$note]);
            \audit((int)$actor['id'], 'application.status_changed', 'membership_application', $id);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function locked(PDO $pdo, int $id): array {
        $q = $pdo->prepare('SELECT * FROM membership_applications WHERE id = ? FOR UPDATE');
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row) { throw new DomainException('Application not found.'); }
        return $row;
    }
}
