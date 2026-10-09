<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use PDO;

/**
 * No administrative account can be linked to an approved member without
 * same-email proof, current organizational assignment, password re-authentication,
 * and independent approval. Technical admins can review MSW Head links only.
 */
final class MemberIdentity {
    private const STAFF_ROLES = [
        'msw_head','msw_member','committee_head','deputy_head',
        'executive_officer','source_editor','president',
    ];

    private static function currentAssignment(PDO $pdo,int $memberId,string $role): bool {
        $q=$pdo->prepare("SELECT r.role_category,r.position_title,v.committee_name
            FROM role_assignments r LEFT JOIN vacancies v ON v.id=r.vacancy_id
            WHERE r.member_id=? AND r.ends_at IS NULL");
        $q->execute([$memberId]);
        foreach($q->fetchAll() as $r){
            $category=$r['role_category'];
            $committee=strtolower((string)$r['committee_name']);
            $position=strtolower($r['position_title']);
            if ($role==='msw_head' && $category==='Committee Head'
                && str_contains($committee,'membership') && str_contains($committee,'welfare')) return true;
            if ($role==='msw_member' && $category==='Committee Member'
                && str_contains($committee,'membership') && str_contains($committee,'welfare')) return true;
            if ($role==='committee_head' && $category==='Committee Head') return true;
            if ($role==='deputy_head' && $category==='Deputy Committee Head') return true;
            if ($role==='executive_officer' && $category==='Executive Officer') return true;
            if ($role==='source_editor' && $category==='The Source Code') return true;
            if ($role==='president' && $category==='Executive Officer'
                && str_contains($position,'president')) return true;
        }
        return false;
    }

    /** Show only matching records to the authenticated staff member. */
    public static function candidates(array $actor): array {
        if (!in_array($actor['role'],self::STAFF_ROLES,true)) return [];
        $q=\db()->prepare("SELECT m.id,m.full_name,m.membership_type,m.membership_status
            FROM members m
            WHERE m.email=? AND m.user_id IS NULL AND m.membership_type='appointed'
              AND m.membership_status='active'
            ORDER BY m.id DESC LIMIT 10");
        $q->execute([strtolower((string)$actor['email'])]);
        $pdo=\db();
        return array_values(array_filter($q->fetchAll(),
            fn(array $m)=>self::currentAssignment($pdo,(int)$m['id'],(string)$actor['role'])));
    }

    public static function request(int $memberId,array $actor,string $password): int {
        if ($memberId<1 || !in_array($actor['role'],self::STAFF_ROLES,true)) {
            throw new DomainException('Only authorized organizational staff can request identity linking.');
        }
        $pdo=\db();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
            $q->execute([(int)$actor['id']]);$user=$q->fetch();
            if (!$user || !(bool)$user['is_active'] || $user['role']!==$actor['role'] ||
                !password_verify($password,$user['password_hash'])) {
                throw new DomainException('Account re-authentication unsuccessful.');
            }
            $q=$pdo->prepare('SELECT * FROM members WHERE id=? FOR UPDATE');
            $q->execute([$memberId]);$member=$q->fetch();
            if (!$member || $member['membership_status']!=='active' ||
                $member['membership_type']!=='appointed' || $member['user_id']!==null ||
                !hash_equals(strtolower($member['email']),strtolower($user['email'])) ||
                !self::currentAssignment($pdo,$memberId,$user['role'])) {
                throw new DomainException('The requested member record cannot be linked to this account.');
            }
            $q=$pdo->prepare('SELECT id FROM members WHERE user_id=?');
            $q->execute([(int)$user['id']]);
            if ($q->fetchColumn()) throw new DomainException('This account is already linked to a member.');
            // Release expired pending reservations before claiming one.
            $pdo->prepare("UPDATE identity_link_requests SET status='expired'
                WHERE status='pending' AND expires_at<=NOW()
                  AND (member_id=? OR user_id=?)")->execute([$memberId,(int)$user['id']]);
            $q=$pdo->prepare("INSERT INTO identity_link_requests(member_id,user_id,expires_at)
                VALUES (?,?,DATE_ADD(NOW(),INTERVAL 48 HOUR))");
            $q->execute([$memberId,(int)$user['id']]);
            $id=(int)$pdo->lastInsertId();
            \audit((int)$user['id'],'identity_link.requested','identity_link_request',$id);
            $pdo->commit();
            return $id;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode()==='23000') {
                throw new DomainException('An identity verification request already exists.');
            }
            throw $e;
        } catch (\Throwable $e) {
            $pdo->rollBack();throw $e;
        }
    }

    public static function pendingForReviewer(array $actor): array {
        if (!in_array($actor['role'],['msw_head','admin'],true)) return [];
        $condition=$actor['role']==='admin'?"u.role='msw_head'":"u.role<>'msw_head'";
        $q=\db()->prepare("SELECT r.id,r.member_id,r.user_id,r.requested_at,r.expires_at,
                m.full_name,m.email,u.role
                FROM identity_link_requests r JOIN members m ON m.id=r.member_id
                JOIN users u ON u.id=r.user_id
                WHERE r.status='pending' AND r.expires_at>NOW()
                  AND $condition AND r.user_id<>?
                ORDER BY r.requested_at ASC LIMIT 80");
        $q->execute([(int)$actor['id']]);
        return $q->fetchAll();
    }

    public static function review(int $requestId,array $actor,bool $approve,string $note): void {
        $note=trim($note);
        if ($requestId<1 || mb_strlen($note)<20 || mb_strlen($note)>1000) {
            throw new DomainException('A reason of 20–1,000 characters is required.');
        }
        if (!in_array($actor['role'],['msw_head','admin'],true)) {
            throw new DomainException('Independent reviewer authorization required.');
        }
        $pdo=\db();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT * FROM identity_link_requests WHERE id=? FOR UPDATE');
            $q->execute([$requestId]);$r=$q->fetch();
            if (!$r || $r['status']!=='pending' || strtotime($r['expires_at'])<=time()) {
                throw new DomainException('Link request expired or already decided.');
            }
            if ((int)$r['user_id']===(int)$actor['id']) {
                throw new DomainException('A requester cannot approve their own identity.');
            }
            $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
            $q->execute([(int)$r['user_id']]);$user=$q->fetch();
            $q=$pdo->prepare('SELECT * FROM members WHERE id=? FOR UPDATE');
            $q->execute([(int)$r['member_id']]);$member=$q->fetch();
            if (!$user || !$member || !(bool)$user['is_active'] ||
                $member['user_id']!==null || $member['membership_type']!=='appointed' ||
                $member['membership_status']!=='active' ||
                !hash_equals(strtolower($member['email']),strtolower($user['email'])) ||
                !self::currentAssignment($pdo,(int)$member['id'],$user['role'])) {
                throw new DomainException('Identity evidence is no longer valid.');
            }
            if (($user['role']==='msw_head' && $actor['role']!=='admin') ||
                ($user['role']!=='msw_head' && $actor['role']!=='msw_head')) {
                throw new DomainException('Independent reviewer role does not match this request.');
            }
            $result=$approve?'approved':'rejected';
            if ($approve) {
                $q=$pdo->prepare('UPDATE members SET user_id=? WHERE id=? AND user_id IS NULL');
                $q->execute([(int)$user['id'],(int)$member['id']]);
                if ($q->rowCount()!==1) throw new DomainException('Member was linked by another process.');
                // Avoid sending obsolete activation links to existing staff accounts.
                $pdo->prepare("UPDATE member_invitations SET used_at=NOW()
                    WHERE member_id=? AND used_at IS NULL")->execute([(int)$member['id']]);
                $pdo->prepare("UPDATE notification_outbox o JOIN member_invitations i
                    ON o.event_key=CONCAT('member.invite.',i.id)
                    SET o.status='disabled',o.last_error='Existing account linked'
                    WHERE i.member_id=? AND o.status='queued'")->execute([(int)$member['id']]);
            }
            $q=$pdo->prepare("UPDATE identity_link_requests SET status=?,reviewed_at=NOW(),
                reviewed_by=?,review_note=? WHERE id=? AND status='pending'");
            $q->execute([$result,(int)$actor['id'],$note,$requestId]);
            \audit((int)$actor['id'],'identity_link.'.$result,'identity_link_request',$requestId);
            $pdo->commit();
        } catch (\Throwable $e) {$pdo->rollBack();throw $e;}
    }
}
