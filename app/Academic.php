<?php
declare(strict_types=1);
namespace Agile;

use DomainException;

final class Academic {
    private const COVERED=['Committee Member','Deputy Committee Head','Committee Head','Executive Officer','The Source Code'];
    public static function initiate(int $termId,array $actor): int {
        if ($actor['role']!=='msw_head') throw new DomainException('Only MSW Head may initiate academic verification.');
        $q=\db()->prepare("SELECT state FROM academic_terms WHERE id=?");$q->execute([$termId]);
        if ($q->fetchColumn()!=='active') throw new DomainException('The academic term must be active.');
        $pdo=\db();$created=0;
        $q=$pdo->query("SELECT DISTINCT m.id FROM members m
            JOIN role_assignments r ON r.member_id=m.id AND r.ends_at IS NULL
            WHERE m.membership_status='active' AND m.membership_type='appointed'
              AND r.role_category IN ('Committee Member','Deputy Committee Head','Committee Head','Executive Officer','The Source Code')");
        $ins=$pdo->prepare('INSERT IGNORE INTO academic_verifications(term_id,member_id) VALUES (?,?)');
        foreach ($q->fetchAll() as $row) {
            $ins->execute([$termId,(int)$row['id']]);$created+=$ins->rowCount();
        }
        \audit((int)$actor['id'],'academic.cycle_initiated','academic_term',$termId);
        return $created;
    }
    /** Advisory rule screening only; requires approved, versioned policy. */
    public static function screen(int $verificationId,array $actor,array $grades): array {
        if ($actor['role']!=='msw_head') throw new DomainException('Academic records are MSW Head restricted.');
        if (count($grades)<1||count($grades)>100) throw new DomainException('Grade entry count is invalid.');
        $items=[];
        foreach($grades as $entry) {
            if (!is_array($entry)) throw new DomainException('Invalid grade record.');
            $course=trim((string)($entry['course']??''));
            $grade=strtoupper(trim((string)($entry['grade']??'')));
            if (mb_strlen($course)<2||mb_strlen($course)>140||!preg_match('/^[A-Z0-9.+\/-]{1,12}$/',$grade))
                throw new DomainException('Invalid course or grade value.');
            $items[]=['course'=>$course,'grade'=>$grade];
        }
        $pdo=\db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM academic_verifications WHERE id=? FOR UPDATE');
            $q->execute([$verificationId]);$check=$q->fetch();
            if (!$check||$check['verified_result']!=='pending') throw new DomainException('Verification not found or already reviewed.');
            $q=$pdo->query('SELECT id,criteria_json FROM eligibility_policies WHERE is_approved=1 ORDER BY id DESC LIMIT 1');
            $policy=$q->fetch();
            if (!$policy) throw new DomainException('An authorized approved bylaw policy is required before grade screening.');
            $criteria=json_decode($policy['criteria_json'],true);
            if (!is_array($criteria)||!isset($criteria['flag_codes'])||!is_array($criteria['flag_codes']))
                throw new DomainException('Approved policy is incomplete.');
            $codes=array_map('strtoupper',$criteria['flag_codes']);
            $found=[];
            foreach($items as $item) if(in_array($item['grade'],$codes,true))$found[]=$item['course'];
            $flag=$found?'review_required':'no_flags';
            $pdo->prepare('UPDATE academic_verifications SET policy_id=?,declared_grades_json=?,automated_flag=? WHERE id=?')
                ->execute([(int)$policy['id'],json_encode($items,JSON_THROW_ON_ERROR),$flag,$verificationId]);
            \audit((int)$actor['id'],'academic.screened','academic_verification',$verificationId);
            $pdo->commit();
            return ['flag'=>$flag,'flagged_count'=>count($found)];
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
    public static function verify(int $id,array $actor,string $result,string $note): void {
        if ($actor['role']!=='msw_head') throw new DomainException('Only MSW Head verifies academic outcomes.');
        if (!in_array($result,['eligible','ineligible','needs_more_information'],true)||
            mb_strlen(trim($note))<20||mb_strlen($note)>1000) throw new DomainException('Document a supported human-reviewed result.');
        $q=\db()->prepare("UPDATE academic_verifications SET verified_result=?,reviewer_id=?,reviewer_note=?,reviewed_at=NOW()
          WHERE id=? AND verified_result='pending' AND policy_id IS NOT NULL");
        $q->execute([$result,(int)$actor['id'],trim($note),$id]);
        if($q->rowCount()!==1)throw new DomainException('Verification must first be screened under an approved policy.');
        \audit((int)$actor['id'],'academic.human_verified','academic_verification',$id);
    }
    /** A separate authorized step; never triggered automatically by a grade flag. */
    public static function transitionToGeneral(int $verificationId,array $actor,string $reason): void {
        if ($actor['role']!=='msw_head') throw new DomainException('Only MSW Head may initiate an approved role transition.');
        if (\envValue('ACADEMIC_ROLE_TRANSITIONS_ENABLED','false')!=='true') {
            throw new DomainException('Academic role changes are disabled until the final bylaws and authorization are approved.');
        }
        if (mb_strlen(trim($reason))<25||mb_strlen($reason)>1000)throw new DomainException('Document the organizational decision and due process.');
        $pdo=\db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT v.member_id,v.verified_result,m.user_id
                FROM academic_verifications v
                JOIN members m ON m.id=v.member_id
                WHERE v.id=? FOR UPDATE');
            $q->execute([$verificationId]);$row=$q->fetch();
            if (!$row||$row['verified_result']!=='ineligible')throw new DomainException('Human-confirmed ineligibility is required.');
            $q=$pdo->prepare('SELECT * FROM role_assignments WHERE member_id=? AND ends_at IS NULL FOR UPDATE');
            $q->execute([(int)$row['member_id']]);$roles=$q->fetchAll();
            if (!$roles) throw new DomainException('Member has no active organizational role.');
            foreach($roles as $role){
                $pdo->prepare('UPDATE role_assignments SET ends_at=NOW(),reason=? WHERE id=?')->execute([trim($reason),(int)$role['id']]);
                if (!empty($role['vacancy_id'])) {
                    $pdo->prepare("UPDATE vacancies SET filled=GREATEST(filled-1,0),status='published' WHERE id=?")
                        ->execute([(int)$role['vacancy_id']]);
                }
            }
            $pdo->prepare("UPDATE members SET membership_type='general' WHERE id=?")->execute([(int)$row['member_id']]);
            // Invalidate organizational privileges for the explicitly linked member account
            // inside the same transaction. Auth::user reloads the role on each request.
            if ($row['user_id']!==null) {
                $pdo->prepare("UPDATE users SET role='member'
                   WHERE id=? AND role IN ('msw_head','msw_member','committee_head',
                     'deputy_head','executive_officer','source_editor','president')")
                  ->execute([(int)$row['user_id']]);
            }
            \audit((int)$actor['id'],'academic.role_to_general','member',(int)$row['member_id']);
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
}
