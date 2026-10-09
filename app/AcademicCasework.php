<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use PDO;

/**
 * Confidential human-led casework around unratified AGILE draft bylaws.
 * Preview results never alter official verified_result or membership roles.
 */
final class AcademicCasework {
    private const REQUEST_TYPES=['correction','appeal','additional_information'];
    private const FINAL_STATES=['resolved','rejected'];

    public static function requireHead(array $actor): void {
        if (($actor['role']??'')!=='msw_head') {
            throw new DomainException('Only the MSW Head can review confidential academic records.');
        }
    }

    private static function linkedMember(int $memberId,array $actor): void {
        if ($memberId<1 || !isset($actor['id']) ||
            !in_array((string)($actor['role']??''),[
                'member','msw_head','msw_member','committee_head','deputy_head',
                'executive_officer','source_editor','president'
            ],true)) {
            throw new DomainException('An authenticated linked member account is required.');
        }
        $q=\db()->prepare("SELECT id FROM members
            WHERE id=? AND user_id=? AND membership_status='active'");
        $q->execute([$memberId,(int)$actor['id']]);
        if (!$q->fetchColumn()) {
            throw new DomainException('This academic record is not linked to your account.');
        }
    }

    public static function ownTerms(array $actor): array {
        $q=\db()->prepare("SELECT v.id,v.member_id,t.label,t.deadline_on,t.state,
                v.verified_result,v.created_at,
                (SELECT JSON_UNQUOTE(JSON_EXTRACT(p.outcome_json,'$.screening_flag'))
                   FROM academic_provisional_previews p WHERE p.verification_id=v.id
                   ORDER BY p.id DESC LIMIT 1) AS provisional_flag
            FROM academic_verifications v
            JOIN members m ON m.id=v.member_id
            JOIN academic_terms t ON t.id=v.term_id
            WHERE m.user_id=? AND m.membership_status='active'
            ORDER BY v.id DESC LIMIT 50");
        $q->execute([(int)($actor['id']??0)]);
        $rows=$q->fetchAll();
        foreach ($rows as $row) self::linkedMember((int)$row['member_id'],$actor);
        return $rows;
    }

    public static function ownRequests(int $verificationId,array $actor): array {
        $memberId=self::getMemberId($verificationId);
        self::linkedMember($memberId,$actor);
        $q=\db()->prepare('SELECT id,request_type,request_text,status,resolution_note,created_at,updated_at
            FROM academic_review_requests
            WHERE verification_id=? AND member_id=? ORDER BY id DESC LIMIT 30');
        $q->execute([$verificationId,$memberId]);
        return $q->fetchAll();
    }

    private static function getMemberId(int $verificationId): int {
        $q=\db()->prepare('SELECT member_id FROM academic_verifications WHERE id=?');
        $q->execute([$verificationId]);
        $id=$q->fetchColumn();
        if (!$id) throw new DomainException('Verification record not found.');
        return (int)$id;
    }

    private static function assignedRole(PDO $pdo,int $memberId): array {
        $q=$pdo->prepare("SELECT role_category,position_title FROM role_assignments
            WHERE member_id=? AND ends_at IS NULL ORDER BY id DESC");
        $q->execute([$memberId]);
        $rows=$q->fetchAll();
        foreach (DraftBylawsPolicy::COVERED_ROLES as $covered) {
            foreach ($rows as $role) {
                if ($role['role_category']===$covered) return $role;
            }
        }
        throw new DomainException('This student has no current covered organizational assignment.');
    }

    /**
     * Recalculate a preliminary preview from actual member's recorded role.
     * The caller cannot forge the role_category/executive_position.
     */
    public static function preview(int $verificationId,array $actor,array $input): array {
        self::requireHead($actor);
        if ($verificationId<1) throw new DomainException('Select a valid academic verification record.');
        $pdo=\db();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT v.member_id,v.term_id,t.state
                FROM academic_verifications v
                JOIN academic_terms t ON t.id=v.term_id
                WHERE v.id=? FOR UPDATE');
            $q->execute([$verificationId]);
            $verification=$q->fetch();
            if (!$verification) throw new DomainException('Verification record not found.');
            if ($verification['state']==='closed') throw new DomainException('Closed academic terms cannot receive new previews.');

            $role=self::assignedRole($pdo,(int)$verification['member_id']);
            $payload=[
                'role_category'=>$role['role_category'],
                'executive_position'=>$role['role_category']==='Executive Officer'
                    ? $role['position_title'] : '',
                'year_level'=>$input['year_level']??null,
                'current_bsit_ous_enrollment'=>$input['current_bsit_ous_enrollment']??null,
                'full_academic_load'=>$input['full_academic_load']??null,
                'full_history_supplied'=>$input['full_history_supplied']??false,
                'grade_records'=>$input['grade_records']??[],
            ];
            if (!is_array($payload['grade_records']) || count($payload['grade_records'])>200) {
                throw new DomainException('Up to 200 manually reviewed course marks may be entered.');
            }
            $outcome=DraftBylawsPolicy::preview($payload);
            // This table is restricted case evidence, NOT an approved policy outcome.
            $q=$pdo->prepare('INSERT INTO academic_provisional_previews
                (verification_id,policy_version,input_json,outcome_json,reviewed_by)
                VALUES (?,?,?,?,?)');
            $q->execute([$verificationId,DraftBylawsPolicy::VERSION,
                json_encode($payload,JSON_THROW_ON_ERROR),
                json_encode($outcome,JSON_THROW_ON_ERROR),(int)$actor['id']]);
            \audit((int)$actor['id'],'academic.draft_preview','academic_verification',$verificationId);
            $pdo->commit();
            return $outcome;
        } catch (\Throwable $e) {$pdo->rollBack();throw $e;}
    }

    public static function latestPreview(int $verificationId,array $actor): ?array {
        self::requireHead($actor);
        $q=\db()->prepare('SELECT p.*,u.display_name AS reviewer
            FROM academic_provisional_previews p
            JOIN users u ON u.id=p.reviewed_by
            WHERE p.verification_id=? ORDER BY p.id DESC LIMIT 1');
        $q->execute([$verificationId]);
        return $q->fetch()?:null;
    }

    public static function submitRequest(int $verificationId,array $actor,string $type,string $reason): int {
        $reason=trim($reason);
        if (!in_array($type,self::REQUEST_TYPES,true) ||
            mb_strlen($reason)<20||mb_strlen($reason)>3000) {
            throw new DomainException('Choose a correction, appeal or information request and provide 20–3,000 characters.');
        }
        $pdo=\db();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT member_id FROM academic_verifications WHERE id=? FOR UPDATE');
            $q->execute([$verificationId]);$memberId=$q->fetchColumn();
            if (!$memberId) throw new DomainException('Verification not found.');
            self::linkedMember((int)$memberId,$actor);
            // The parent row is locked so concurrent submissions serialize.
            $q=$pdo->prepare("SELECT id FROM academic_review_requests
                WHERE verification_id=? AND member_id=? AND status IN ('submitted','in_review')
                LIMIT 1");
            $q->execute([$verificationId,(int)$memberId]);
            if ($q->fetchColumn()) {
                throw new DomainException('An academic review request is already pending for this term.');
            }
            $q=$pdo->prepare('INSERT INTO academic_review_requests
                (verification_id,member_id,submitted_by,request_type,request_text)
                VALUES (?,?,?,?,?)');
            $q->execute([$verificationId,(int)$memberId,(int)$actor['id'],$type,$reason]);
            $id=(int)$pdo->lastInsertId();
            $q=$pdo->prepare("INSERT INTO academic_review_events
                (review_request_id,actor_user_id,event_type,event_note) VALUES (?,?,?,?)");
            $q->execute([$id,(int)$actor['id'],'submitted',$reason]);
            \audit((int)$actor['id'],'academic.review_submitted','academic_review_request',$id);
            $pdo->commit();
            return $id;
        }catch(\Throwable $e) {$pdo->rollBack();throw $e;}
    }

    /**
     * Own-case file access is always checked against the authenticated,
     * explicitly linked member account. MSW Head has documented case oversight.
     */
    public static function authorizeEvidence(int $requestId,array $actor,bool $write): array {
        if ($requestId<1) throw new DomainException('Invalid academic review request.');
        $q=\db()->prepare('SELECT r.id,r.member_id,r.status,r.verification_id,
                   m.user_id,m.membership_status
            FROM academic_review_requests r
            JOIN members m ON m.id=r.member_id
            WHERE r.id=?');
        $q->execute([$requestId]);$request=$q->fetch();
        if(!$request) throw new DomainException('Academic review request unavailable.');
        if(($actor['role']??'')==='msw_head') return $request;
        self::linkedMember((int)$request['member_id'],$actor);
        if($write && !in_array($request['status'],['submitted','in_review'],true)) {
            throw new DomainException('Evidence cannot be added after this review is closed.');
        }
        return $request;
    }

    public static function inbox(array $actor,int $limit=80): array {
        self::requireHead($actor);
        $limit=max(1,min($limit,100));
        $q=\db()->prepare("SELECT r.id,r.verification_id,r.member_id,r.request_type,r.status,
            r.request_text,r.created_at,r.updated_at,
            m.full_name,t.label AS term_label
            FROM academic_review_requests r
            JOIN members m ON m.id=r.member_id
            JOIN academic_verifications v ON v.id=r.verification_id
            JOIN academic_terms t ON t.id=v.term_id
            ORDER BY CASE r.status WHEN 'submitted' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END,
                     r.created_at DESC LIMIT ?");
        $q->bindValue(1,$limit,PDO::PARAM_INT);$q->execute();
        return $q->fetchAll();
    }

    public static function events(int $requestId,array $actor): array {
        self::requireHead($actor);
        $q=\db()->prepare('SELECT e.event_type,e.event_note,e.created_at,u.display_name
            FROM academic_review_events e JOIN users u ON u.id=e.actor_user_id
            WHERE e.review_request_id=? ORDER BY e.id ASC LIMIT 100');
        $q->execute([$requestId]);
        return $q->fetchAll();
    }

    public static function resolve(int $requestId,array $actor,string $target,string $response): void {
        self::requireHead($actor);
        $response=trim($response);
        if (!in_array($target,['in_review','resolved','rejected'],true) ||
            mb_strlen($response)<20||mb_strlen($response)>3000) {
            throw new DomainException('Provide a supported review state and documented response of 20–3,000 characters.');
        }
        $pdo=\db();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT * FROM academic_review_requests WHERE id=? FOR UPDATE');
            $q->execute([$requestId]);$row=$q->fetch();
            if (!$row)throw new DomainException('Review request not found.');
            $allowed=$row['status']==='submitted'?['in_review','resolved','rejected']:
                ($row['status']==='in_review'?['resolved','rejected']:[]);
            if (!in_array($target,$allowed,true)) {
                throw new DomainException('This review request cannot be transitioned to that status.');
            }
            $q=$pdo->prepare('UPDATE academic_review_requests
                SET status=?,resolution_note=?,reviewed_by=?,reviewed_at=NOW()
                WHERE id=?');
            $q->execute([$target,$response,(int)$actor['id'],$requestId]);
            $q=$pdo->prepare('INSERT INTO academic_review_events
                (review_request_id,actor_user_id,event_type,event_note) VALUES (?,?,?,?)');
            $q->execute([$requestId,(int)$actor['id'],$target,$response]);
            \audit((int)$actor['id'],'academic.review_'.$target,'academic_review_request',$requestId);
            // Resolving an appeal does NOT alter approved academic eligibility or any role.
            $pdo->commit();
        }catch(\Throwable $e) {$pdo->rollBack();throw $e;}
    }

    /** Aggregate and scoped dashboard for the MSW Head only. */
    public static function dashboard(array $actor): array {
        self::requireHead($actor);
        $terms=\db()->query("SELECT t.id,t.label,t.state,t.deadline_on,
            COUNT(v.id) AS total_checks,
            SUM(CASE WHEN v.verified_result='pending' THEN 1 ELSE 0 END) AS pending_checks,
            SUM(CASE WHEN v.verified_result='ineligible' THEN 1 ELSE 0 END) AS confirmed_ineligible,
            (SELECT COUNT(*) FROM academic_review_requests r
               JOIN academic_verifications vr ON vr.id=r.verification_id
               WHERE vr.term_id=t.id AND r.status IN ('submitted','in_review')) AS open_requests
            FROM academic_terms t LEFT JOIN academic_verifications v ON v.term_id=t.id
            GROUP BY t.id,t.label,t.state,t.deadline_on
            ORDER BY t.id DESC LIMIT 50")->fetchAll();
        return $terms;
    }
}
