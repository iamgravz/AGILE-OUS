<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
final class Welfare {
    public const CATEGORIES = [
      'Academic','Financial & Resources','Well-being/Personal Support',
      'Skills & Self-Improvement','Accessibility','Safety/Conduct','Community Concerns','Others'
    ];
    private const TRANSITIONS = [
       'submitted'=>['triaged'],
       'triaged'=>['in_progress','referred'],
       'in_progress'=>['referred','resolved'],
       'referred'=>['in_progress','resolved'],
       'resolved'=>['closed','in_progress'],
       'closed'=>[]
    ];
    public static function submit(array $fields): array {
        $name=trim((string)($fields['reporter_name']??''));
        $email=strtolower(trim((string)($fields['reporter_email']??'')));
        $category=(string)($fields['category']??'');
        $summary=trim((string)($fields['summary']??''));
        $details=trim((string)($fields['details']??''));
        if (mb_strlen($name)<3||mb_strlen($name)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)||
            !in_array($category,self::CATEGORIES,true)||mb_strlen($summary)<8||mb_strlen($summary)>255||
            mb_strlen($details)<20||mb_strlen($details)>10000||($fields['privacy_consent']??'')!=='yes') {
            throw new DomainException('Review the submitted details, welfare category and privacy acknowledgement.');
        }
        $ref='WF-'.strtoupper(bin2hex(random_bytes(7)));
        $token=bin2hex(random_bytes(24));
        $pdo=\db();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('INSERT INTO welfare_cases
            (reference_code,tracking_hash,reporter_name,reporter_email,category,summary,details)
             VALUES(?,?,?,?,?,?,?)');
            $q->execute([$ref,hash('sha256',$token),$name,$email,$category,$summary,$details]);
            \audit(null,'welfare.submitted','welfare_case',(int)$pdo->lastInsertId());
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
        return ['reference'=>$ref,'tracking_token'=>$token];
    }
    public static function publicStatus(string $reference,string $token): ?array {
        if (!preg_match('/^WF-[A-F0-9]{14}$/',$reference)||!preg_match('/^[a-f0-9]{48}$/',$token)) return null;
        $q=\db()->prepare('SELECT status,tracking_hash,created_at FROM welfare_cases WHERE reference_code=?');
        $q->execute([$reference]);$row=$q->fetch();
        if (!$row||!hash_equals($row['tracking_hash'],hash('sha256',$token))) return null;
        return ['status'=>$row['status'],'created_at'=>$row['created_at']];
    }
    public static function findForActor(int $id,array $actor): ?array {
        if (!in_array($actor['role'],['msw_head','msw_member'],true)) throw new DomainException('Unauthorized welfare access.');
        $q=\db()->prepare('SELECT * FROM welfare_cases WHERE id=?');
        $q->execute([$id]);$row=$q->fetch();
        if (!$row) return null;
        if ($actor['role']!=='msw_head'&&(int)($row['assigned_to']??0)!==(int)$actor['id'])
            throw new DomainException('This confidential case is not assigned to you.');
        return $row;
    }
    public static function assign(int $id,array $actor,?int $userId,string $priority): void {
        if ($actor['role']!=='msw_head') throw new DomainException('Only the MSW Head can assign welfare cases.');
        if (!in_array($priority,['low','normal','high','urgent'],true)) throw new DomainException('Invalid priority.');
        $pdo=\db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT status FROM welfare_cases WHERE id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch();
            if (!$row||$row['status']==='closed') throw new DomainException('This case is unavailable for assignment.');
            if ($userId!==null) {
                $q=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='msw_member' AND is_active=1");
                $q->execute([$userId]);
                if (!$q->fetchColumn()) throw new DomainException('Assignee must be an active MSW Member.');
            }
            $pdo->prepare('UPDATE welfare_cases SET assigned_to=?,priority=? WHERE id=?')
                ->execute([$userId,$priority,$id]);
            \audit((int)$actor['id'],'welfare.assigned','welfare_case',$id);
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
    public static function update(int $id,array $actor,string $newStatus,string $note): void {
        $note=trim($note);
        if (mb_strlen($note)<10||mb_strlen($note)>5000) throw new DomainException('An internal case note of 10–5,000 characters is required.');
        $pdo=\db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM welfare_cases WHERE id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch();
            if (!$row) throw new DomainException('Case not found.');
            if ($actor['role']!=='msw_head'&&
                ($actor['role']!=='msw_member'||(int)($row['assigned_to']??0)!==(int)$actor['id']))
                throw new DomainException('Case access is restricted.');
            if (!in_array($newStatus,self::TRANSITIONS[$row['status']]??[],true))
                throw new DomainException('Invalid case status transition.');
            if (in_array($newStatus,['resolved','closed'],true)&&$actor['role']!=='msw_head')
                throw new DomainException('Only the MSW Head may resolve or close a confidential case.');
            $pdo->prepare('UPDATE welfare_cases SET status=? WHERE id=?')->execute([$newStatus,$id]);
            $pdo->prepare('INSERT INTO welfare_updates(case_id,actor_id,old_status,new_status,private_note) VALUES(?,?,?,?,?)')
                ->execute([$id,(int)$actor['id'],$row['status'],$newStatus,$note]);
            \audit((int)$actor['id'],'welfare.status_changed','welfare_case',$id);
            $pdo->commit();
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
    public static function followup(int $id,array $actor,string $dueAt,string $note): int {
        self::findForActor($id,$actor);
        $when=\DateTimeImmutable::createFromFormat('!Y-m-d H:i',str_replace('T',' ',$dueAt));
        if (!$when||$when->format('Y-m-d H:i')!==str_replace('T',' ',$dueAt)||
            $when<new \DateTimeImmutable()||$when>new \DateTimeImmutable('+365 days'))
            throw new DomainException('Follow-up must be in the next 365 days.');
        $note=trim($note);
        if (mb_strlen($note)<10||mb_strlen($note)>1000) throw new DomainException('Follow-up instructions are required.');
        $assignedTo=(int)$actor['id'];
        $q=\db()->prepare('INSERT INTO welfare_followups(case_id,assigned_to,due_at,note) VALUES(?,?,?,?)');
        $q->execute([$id,$assignedTo,$when->format('Y-m-d H:i:s'),$note]);
        return (int)\db()->lastInsertId();
    }
    public static function refer(int $id,array $actor,string $target,string $note): void {
        self::findForActor($id,$actor);
        $target=trim($target);$note=trim($note);
        if (mb_strlen($target)<3||mb_strlen($target)>200||mb_strlen($note)<10||mb_strlen($note)>5000)
            throw new DomainException('Provide the authorized referral office and reason.');
        $q=\db()->prepare('INSERT INTO welfare_referrals(case_id,referred_by,target_office,referral_note) VALUES (?,?,?,?)');
        $q->execute([$id,(int)$actor['id'],$target,$note]);
        \audit((int)$actor['id'],'welfare.referred','welfare_case',$id);
    }
}
